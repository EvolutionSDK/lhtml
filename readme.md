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