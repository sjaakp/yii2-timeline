<?php
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 06-12-2014.
 *
 * Timeline Widget for Yii 2.0
 *
 * Widget integrating Simile Timeline in Yii 2.0 PHP Framework.
 *
 * Timeline widget renders a Simile Timeline, displaying Event data from a DataProvider.
 *
 * http://www.simile-widgets.org/timeline/
 * https://code.google.com/p/simile-widgets/source/browse/#svn%2Ftimeline%2Ftags%2F2.3.1%2Fsrc%2Fwebapp%2Fapi%2Fscripts
 *
 */

/*
 * date parameters can be:
 * - a string, recognized by Javascript Date, that is in RFC2822 or ISO-8601 format;
 *      among them MySQL date and datetime fields
 * - a PHP DateTime object
 * - an array [ year, month, day?, hour?, minute?, second?, millisecond? ]
 *      notice: month is zero-based, so January == 0
 * - an integer: Unix date value (seconds since 1-1-1970)
 */

namespace sjaakp\timeline;

use yii\base\Widget;
use yii\base\InvalidConfigException;
use yii\web\View;
use yii\web\JsExpression;
use yii\helpers\Html;
use yii\helpers\Json;
use \DateTime;

class Timeline extends Widget {

    // Values for band intervalUnit; from simile/ajax/date-time.js
    const MILLISECOND    = 0;
    const SECOND         = 1;
    const MINUTE         = 2;
    const HOUR           = 3;
    const DAY            = 4;
    const WEEK           = 5;
    const MONTH          = 6;
    const YEAR           = 7;
    const DECADE         = 8;
    const CENTURY        = 9;
    const MILLENNIUM     = 10;

    // useful to retrieve alternative pin icons; v. 1.0: changed to https
    const IMG_PREFIX = 'https://api.simile-widgets.org/timeline/2.3.1/images/';

    /**
     * @var \yii\data\DataProviderInterface the data provider for the timeline. This property is required.
     */
    public $dataProvider;

    /**
     * @var array key => value pairs of {timeline attribute name} => {model attribute name}. Required.
     */
    public $attributes;

    /**
     * @var int | string | false
     * Height of the timeline.
     * - int        height in pixels
     * - string     valid CSS height (f.i. in ems)
     * - false      height is not set; caution: the height MUST be set by some other means (CSS), otherwise
     *              the timeline will not appear.
     */
    public $height = 200;

    /**
     * @var array
     * HTML options of the timeline container.
     * Use this if you want to explicitly set the ID.
     */
    public $htmlOptions = [];

    public $timeZone;
    
    public $start;
    public $end;
    public $center;

    protected $bands = [];
    protected $syncs = [];

    public function init()  {
        if (! $this->dataProvider) {
            throw new InvalidConfigException('The "dataProvider" property must be set.');
        }
        if (! $this->attributes) {
            throw new InvalidConfigException('The "attributes" property must be set.');
        }

        if (isset($this->htmlOptions['id'])) {
            $this->setId($this->htmlOptions['id']);
        }
        else $this->htmlOptions['id'] = $this->getId();

        if ($this->height !== false) {
            $style = '';
            if (isset($this->htmlOptions['style']))
                $style = $this->htmlOptions['style'];
            $h = $this->height;
            if (is_integer($h)) $h .= 'px';
            $this->htmlOptions['style'] = $style . "height:$h;";
        }

        if (! $this->timeZone)  {
            $dt = new DateTime();
            $tz = $dt->getTimeZone();
            $this->timeZone = $tz->getOffset($dt) / 3600;
        }
    }

    public function run()   {   // v. 1.0 changed to https
        $view = $this->getView();
        $view->registerJsFile('https://api.simile-widgets.org/timeline/2.3.1/timeline-api.js', [
            'position' => View::POS_HEAD        // important; see: https://code.google.com/p/simile-widgets/issues/detail?id=258
        ]);

        $tData = array_map(function($model) {
            /** @var $model \yii\base\Model */
            $modelAtts = array_filter($model->getAttributes(array_values($this->attributes)), function($att) {
                return ! empty($att);
            });
            $v = [];
            foreach($this->attributes as $tname => $mname)  {
                if (isset($modelAtts[$mname])) $v[$tname] = $modelAtts[$mname];
            }
            $v = $this->handleDates($v, ['start', 'end', 'latestStart', 'earliestEnd']);
            if (isset($v['end'])) $v['durationEvent'] = true;
            $jv = Json::encode($v);
            return new JsExpression("new Timeline.DefaultEventSource.Event($jv)");
        }, $this->dataProvider->getModels());

        $jData = Json::encode($tData);

        $id = $this->getId();

        $jSyncs = Json::encode($this->syncs);
        $jBands = Json::encode($this->bands);

        $js = "var {$id}m=Timeline.ClassicTheme.create(),{$id}s=new Timeline.DefaultEventSource();{$id}s.addMany($jData);";

        if ($this->start)   {
            $start = $this->dateToJs($this->start);
            $js .= "{$id}m.timeline_start=$start;";
        }

        if ($this->end)   {
            $end = $this->dateToJs($this->end);
            $js .= "{$id}m.timeline_stop=$end;";
        }

        $js .= "var {$id}r=null,{$id}t=Timeline.create(document.getElementById('$id'),jQuery.extend(true,$jBands,$jSyncs));{$id}t.finishedEventLoading();
jQuery(window).resize(function(){if(!{$id}r){ {$id}r=setTimeout(function(){ {$id}r=null;{$id}t.layout();},500);}});";

        $view->registerJs($js);

        echo Html::tag('div', '', $this->htmlOptions);
    }

    /**
     * @param $options array
     * - width
     * - layout     the only sensible value is 'overview'; 'detailed' will also work, but seems to be very buggy
     *              all other values default to 'compact'
     * - intervalUnit
     * - intervalPixels
     * - syncWith   zero-based index of band to sync this band to, or false
     *              default: the index of the previous band
     * - highlight
     * - timeZone   if not set, will be set to $this->timeZone
     * @return $this
     */
    public function band($options, $zones = null)  {
        $sync = [];
        $create = '';
        if (is_array($options)) {
            if (count($this->bands))    {
                if (isset($options['syncWith']))    {
                    if ($options['syncWith'] !== false) $sync['syncWith'] = $options['syncWith'];
                    unset($options['syncWith']);
                }
                else $sync['syncWith'] = count($this->bands) - 1;
                if (isset($options['highlight']))    {
                    $sync['highlight'] = $options['highlight'];
                    unset($options['highlight']);
                }
                else $sync['highlight'] = true;
            }
            else $sync = new JsExpression('{}');
            if (!array_key_exists('eventSource', $options)) {
                $options['eventSource'] = new JsExpression($this->getId() . 's');
            }
            if (!array_key_exists('theme', $options)) {
                $options['theme'] = new JsExpression($this->getId() . 'm');
            }
            if ($this->center) $options['date'] = $this->dateToJs($this->center);
            if (! isset($options['timeZone']) && $this->timeZone) $options['timeZone'] = $this->timeZone;
            if (is_array($zones)) {
                $create = 'HotZone';
                $options['zones'] = array_map([$this, 'handleDates'], $zones);
            }
        }
        $this->syncs[] = $sync;

        $opts = Json::encode($options);
        $this->bands[] = new JsExpression("Timeline.create{$create}BandInfo($opts)");

        return $this;
    }

    protected function handleDates($v, $attNames = ['start', 'end']) {
        foreach($attNames as $dateAttr)  {
            if (array_key_exists($dateAttr, $v))    {
                $v[$dateAttr] = $this->dateToJs($v[$dateAttr]);
            }
        }
        return $v;
    }
    
    protected function dateToJs($val) {
        if (is_string($val)) $val = strtotime($val);  // new, convert in the next step; thanks to arjenmeijer.
        if (is_a($val, 'DateTime')) {
            /** @var $val DateTime */
            $val = $val->format('U') . '000';  // Javascript Date has value in milliseconds
        }
        else if (is_array($val)) $val = implode(',', $val);
        else if (is_numeric($val)) $val .= '000';
        return new JsExpression("new Date($val)");
    }
}
