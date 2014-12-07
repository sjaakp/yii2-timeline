YII2 Timeline
=============

#### Widget integrating Simile Timeline in Yii 2.0 PHP Framework. ####

Timeline widget renders a Javascript Simile Timeline,  version 2.3.1. The Event data for the timeline are provided by a Yii DataProvider (any object implementing [yii\data\DataProviderInterface](http://www.yiiframework.com/doc-2.0/yii-data-dataproviderinterface.html)).

A demonstration of Timeline widget is here.

A little more about the Simile Timeline plus some demo's can be found here: [http://www.simile-widgets.org/timeline/](http://www.simile-widgets.org/timeline/). **Caution:** the information is very sketchy, and often contradictory or plainly wrong. Simile Timeline's code is more than eight years old and seems to be abandoned for a long time. It isn't even completed: lots and lots of more or less documented features simply are unimplemented. Nevertheless, the Simile Timeline is a great concept and the core code appears to be running quite well. **Timeline** widget only uses the well proven parts of Simile Timeline and doesn't touch the many loose ends.

## Installation ##

The preferred way to install **Timeline** is through [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

`"sjaakp/yii2-timeline": "*"` 

Or run:

`$ php composer.phar require sjaakp/yii2-timeline "*"` 

You can manually install **Timeline** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-timeline/archive/master.zip).

## Using Timeline ##

The code to use **Timeline** in a View is something like this:

    ... other View code ...
	<?php
		// define Timeline
		$t = Timeline::begin([
			'dataProvider' => $provider,
			'attributes' => [
				'start' => 'startDate',
				... more attributes ...
			]
			... more Timeline options ...
		]);

		// define main Band
		$t->band([
			'width' => '60%',
			'intervalUnit' => Timeline::MONTH,
			'intervalPixels' => 100
			// layout not set, use default
		]);

		// define secundary Band
		$t->band([
			'width' => '40%',
			'intervalUnit' => Timeline::YEAR,
			'intervalPixels' => 120,
			'layout' => 'overview'
		]);

		// complete definition
		Timeline::end();
	?>
	... more View code ...

The method `band()` is chainable, so this can also be written like:

    <?php
		Timeline::begin([
			'dataProvider' => $provider,
			'attributes' => [
				'start' => 'startDate',
				... more attributes ...
			]
			... more Timeline options ...
		])->band([
			'width' => '60%',
			'intervalUnit' => Timeline::MONTH,
			'intervalPixels' => 100
		])->band([
			'width' => '40%',
			'intervalUnit' => Timeline::YEAR,
			'intervalPixels' => 120,
			'layout' => 'overview'
		])->end();
	?>

#### options ####

Timeline has the following options:

- **dataProvider**: the DataProvider for Timeline. Must be set.
- **attributes**: array with key => value pairs of {timeline attribute name} => {model attribute name}. This is used to 'translate' the model attribute names to Timeline attribute names. Required.
- **height**: height of Timeline. Default: 200. Can have these values:
 - `integer` height in pixels
 - `string` valid CSS height (f.i. in ems)
 - `false` height is not set; caution: the height MUST be set by some other means (CSS), otherwise Timeline will not appear.
- **start** (optional): start date
- **end** (optional): end date
- **center** (optional): initial position of Timeline
- **htmlOptions** (optional): array of HTML options for the Timeline container. Use this if you want to explicitly set the ID. 

## Bands ##

**Timeline** consists of one or more Bands. They each display the Events in a different time resolution.

A Band is defined by the Timeline method `band()`.

    public function band( $options, $zones = null )

#### options ####

`$options` is an array with the following keys:

- **width**: the part of Timeline occupied by this band, as a percentage or another CSS3 dimension (yes, you're right: 'height' would be a more proper name, but this is how Simile Timiline defines it),
- **layout**: the only sensible value is 'overview'; 'detailed' will also work, but seems to be very buggy; all other values (including none) default to 'compact', which is the layout of the main band
- **intervalUnit**: the time unit that divides the horizontal scale of the Band. The value should be one of the following unit constants (yes, Timeline has an astonishing range!):
	- `Timeline::MILLISECOND`
	- `Timeline::SECOND`
	- `Timeline::MINUTE`
	- `Timeline::HOUR`
	- `Timeline::DAY`
	- `Timeline::WEEK`
	- `Timeline::MONTH`
	- `Timeline::YEAR`
	- `Timeline::DECADE`
	- `Timeline::CENTURY`
	- `Timeline::MILLENNIUM`
- **intervalPixels**: the width of one division on the horizontal scale in pixels

#### zones ####

Optionally, each Timeline Band can have one or more Zones, parts where the horizontal resolution differs from the rest of the Band.

`$zones` is `null`, or an array with the following keys:

- **start**: beginning date of the Zone
- **end**: ending
- **magnify**: the multiplication factor of the resolution
- **unit**: time unit of the zone; should be one of the unit constants
- **multiple** (optional): modifies the horizontal scale division to multiples of the unit 

## Events ##

**Timeline** displays Events: Models or ActiveRecords characterized by a moment in time.

The Timeline::attributes property holds the translation from Model attribute names to Timeline attribute names.
  
A few attributes are essential for **Timeline**. The Timeline names are:

- **start**: the point in time where the Event is situated
- **text**: the text displayed on main Timeline

Events come in two types:

#### Instant Events ####

These are the basic Events, having just one point in time. **Timeline** displays them as little pin icons. Only the above attributes are required.

#### Duration Events ####

These have a certain duration. **Timeline** displays them as a piece of blue 'tape'. Apart from the above, also required is:

- **end**: the point in time where, well, the Event ends.
   
Duration Events also have some optional attributes, making the Event *Imprecise*:

- **latestStart**
- **earliestEnd**

The imprecise part of a Duration Event is displayed as faded blue tape.

#### Optional Event attributes ####

Some of the other Event attributes are:

- **caption**: the text of the tooltip
- **description**: the text appearing in the pop-up 'bubble'.
- **icon**: the URL of the pin icon (only for Instant Events.
- **color**
- **textColor**
- **link**: modifies the heading of the pop-up bubble to a link with this href.

For the daring, there are [even more](http://simile-widgets.org/wiki/Timeline_EventSources#Additional_Event_Attributes) Event attributes. 

## Dates ##

**Timeline** understands a lot of date formats (in the options and in the Event data). Every date can be provided in one of the following formats:

- a `string`, recognized by [Javascript Date](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date), that is in RFC2822 or ISO-8601 format; among them MySQL `date` and `datetime` fields
- a PHP `DateTime` object
- an `array`, recognized by Javascript Date: `[ year, month, day?, hour?, minute?, second?, millisecond? ]`. Notice: month is zero-based, so January == 0, May == 4
- an `integer`: Unix time stamp (seconds since the Unix Epoch, 1-1-1970, return value of PHP `time()`)
