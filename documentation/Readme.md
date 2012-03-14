LHTML Bundle
============
Logical HTML is a templating engine designed for making website themes with a format that just about any webdevloper, designer, and computer specialist knows. Only accepting strict HTML and properly formatted tags, which promotes healthy, productive coding habits. While also opening a small programatic layer. Consider this the C and V in "MCV" architecture. However we also do have a controller bundle that we recomend you use with LHTML.

The LHTML Variable
==================
LHTML variables use the curly brackets `{ }` to determine variables. There are two types of variables. Local scope variables, and global variables. Global variables are signified by having a colon preceding the whole variable. For example to access the global "e" stack from within LHTML you would use `{:e}`.

## LHTML Variables Traverse Arrays, and Objects

One of the benefits of LHTML over other template parsing engines is the ability for LHTML to traverse arrays and objects. For example lets say I have the variable `{member}` which is an array formatted like so.

```php
<?php

$member = array(
	'first_name' => 'George',
	'last_name' => 'Hampton',
	'phone' => '+1 (800) 555-2345'
);
```

Then `{member.first_name}` would return "George". The same things works with objects for example.

```php
<?php

$member = new StdClass();
$member->first_name = 'George';
$member->last_name = 'Hampton';
$member->phone = '+1 (800) 555-2345';
```

Then `{member.first_name}` would still return "George".

## LHTML Can process functions

One of the major beauties about LHTML is it's ability to process functions. For example lets say I have the following class assigned to the `{member}` variable.

```php
<?php

class Member {
	
	public $data = array(
		'first_name' => 'George',
		'last_name' => 'Hampton',
		'phone' => '+1 (800) 555-2345'
	);

	public function name($part = 'full') {
		if($part == 'full') return $this->data['first_name'].' '.$this->data['last_name'];
		if($part == 'first') return $this->data['first_name'];
		if($part == 'last') return $this->data['last_name'];
	}

}
$member = new Member;
```

Then `{member.name}` would return "George Hampton", and `{member.name(first)}` would return "George" and so on.

LHTML can process an infinite level are variables so as long as something exists in the stack LHTML should be able to load it with little problem. **Notice:** a variable will be tried before a function so if you have a function with the same name as a variable (yes `__get()` calls count as variables) then you must write the function with the parenthesis for it to be handled as a function. **Notice:** when using a LHTML function and are passing arguments make sure you don't place a space between the arguments, and quotes are currently not being parsed either (@todo).

	{var.function(arg1, arg2, arg3)}		# Does not work
	{var.function(arg1,'string')}			# Does not work (@todo: Fix)
	{var.function(arg1,arg2,arg3)}			# Works! :D

THE LHTML TAG
=============
LHTML has it's own set of tags that are used to process data while outputting your code. This can be used to run foreach loops, and if conditions on variables within your output.

## Using a LHTML Tag

Any HTML tag can be used within LHTML, but what makes LHTML special is it's ability to run progromatic functions on the contents of a tag; and providing special attributes to do the same. Lets start with looping through arrays and sourcing.

### Sourcing a variable

Sometimes you have a really long variable that is part of a multidimensional array that you want accessible through out your work but you don't want to have to type out `{var.something.apples.organes.member.first_name}` every time you want to access the contents of member. Well LHTML has a solution to this problem using something called sourcing. Sourcing is done using the `:load` attribute, and works as follows.

	<div class="memberDetails" :load="var.somthing.apples.oranges.member as member">
		<p>{member.first_name} {member.last_name}</p>
	</div>

Now within the contents of `<div class="memberDetails">` I can access `{var.someting.apples.organes.member}` as `{member}` simplifing my code and making templating easier for me. However the scope of active so `{member}` wont work in any parent of `<div class="memberDetails">`

### Loop sourcing a variable

Just like sourcing variables (above) LHTML also supports loop sourcing which could be compared to that of a foreach loop, it gets used exatly the same way (with the addition of the `:iterate` tag)

	<div class="memberDetails" :load="var.somthing.apples.oranges.members as member" :iterate="self">
		<p>{member.first_name} {member.last_name}</p>
	</div>

Now with the above function if `{var.somthing.apples.oranges.members}` is a multidimensional array then it would look through it and print out the div for every member in the array.

### LHTML If Tag

The If tag in LHTML allows data to be shows or hidden based on the if condition specified. The most basic form of the If tag is `var == var1` an example of this would be

	<:if cond="{var1} == 'apples'">
		<p>If var1 was equal to 'apples' this would show</p>
	</:if>

The cond attribute accepts any php conditional operator; such as.

	<				# Less Than
	>				# Greater Than
	==				# Equal To
	!=				# Not Equal To
	<=				# Less Than or Equal To
	>=				# Greater Than or Equal To
	===				# Strict Equal To
	!==				# Strict Not Equal To
	instanceof		# Instance of Object (This works because cond parses the variables)

There are other attributes that can be run on `<:if>`.
	
#### <:if count="{var}">

The if count var count the number of keys in an array, or the amount of times an object can be traversed.

Greater Than:
	
	<:if count="{var}" gt="2">
	</:if>

Less Than:
	
	<:if count="{var}" lt="2">
	</:if>

#### <:if var="{var}" equals="apples">

The if var equals function just checks to see if {var} equals 'apples'. This could also be done with the standard `cond` attribute by running `cond="{var} == 'apples'"`.

	<:if var="{var}" equals="apples">
	</if>

#### <:if var="{var}" not="apples">

The if var not function just checks to see if {var} does not equal 'apples'. This could also be done with the standard `cond` attribute by running `cond="{var} != 'apples'"`.

	<:if var="{var}" not="apples">
	</if>

#### <:if num="{var}">

The if num function checkts to see if a number is greater or less than the provided number.

Greater Than:
	
	<:if num="{var}" gt="2">
	</:if>

Less Than:
	
	<:if num="{var}" lt="2">
	</:if>

#### <:if empty="{var}">

Checks to see if a variable is empty

	<:if empty="{var}">
	</:if>

#### <:if not_empty="{var}">

Checks to see if a variable is not empty

	<:if not_empty="{var}">
	</:if>

#### <:if browser="">

Checks to see if a particular browser if something. Currently only supports Internet Explorer detection.

	<:if browser="IE">
	</:if>

#### <:if time="2012-02-10 02:20:00">

Checks to see if the time provided matches the actual time. The format it checks against is customizable using the php `date()` variables. The default is `Y-m-d H:i:s` You can also specify an offset for timezones (since E3 prefers GMT time) such as `offset="-5"` which would be Eastern Standard Time.

Is October:

	<:if time="10" df="m">
	</:if>

#### Else Tag

Along with the If condition you can also specify an else condition.

	<:if cond="1 == 2">
		Contents wont show
		<:else>
			Contents Will Show
		</:else>
	</:if>

### LHTML Select Tag

The LHTML Select Tag is a replacement for the standard select tag which provides a some extra features like easier pre-selection, and some other cool extensions.

	<:select selected="hairpin" name="blah">
		<option value="loop">Loop</option>
		<option value="hairpin">Hairpin</option>
		<option value="cloverleaf">Clover Leaf</option>
	</:select>

In this example the center option would be selected. There are some other built in functions like month and year selection which can be activated by `type="month"` or `type="year"` the `selected` attribute still works with these extensions.

The LHTML Select tag also throws the event `_on_lhtmlNodeSelect%type%` (where '%type%' is the type attribute value) when using the `type` attribute that can be caught in any bundle. This can be used for various things like state, country selection, or a custom selection generated by your app.

### YouTube Tag

The LHTML YouTube tag takes YouTube URSs and IDs and embeds the video right into your page.

	<:youtube src="http://youtu.be/xm6PGh2fvTg" width="550" height="330" />

### Switch Tag

The LHTML Switch that is more or less a PHP `switch()` function, and works as follows.

	<:switch var="avacado">
		<switch vars="apples,oranges">
			This was activated by 'apples' or 'oranges'.
		</switch>
		<switch vars="artichoke,default">
			This was activated by 'articoke' or if no other result was found.
		</switch>
		<switch vars="avacado">
			This was activated by 'avacado'.
		</switch>
	</:switch>

### SSL Tag

This tag will either turn on or off SSL for your site. If your site does not support SSL this will not work. This tag does nothing in Development Mode.

	<:ssl />		# Forces SSL
	<:ssl:on />		# Forces SSL
	<:ssl:off />	# Forces Standard HTTP

### Include Tag

This tag includes another LHTML file into the current file (file is relative to the location of the current file).

	<:include file="subFolder/include.lhtml" />