<?php
namespace main;

require_once("../src/Tonic.php");

use Tonic\Tonic;

// Set the local timezone
Tonic::$local_tz = "America/Mexico_city";
Tonic::$context_aware = true;
// This variables will be available in all the templates
Tonic::setGlobals(array(
	"now" => @date_create(),
	"context" => array(
		"post" => $_POST,
		"get" => $_GET
	)
));
// Create a custom modifier
Tonic::extendModifier("myModifier",function($input, $prepend, $append = ""){
    // $input will hold the current variable value, it's mandatory that your lambda
    // function has an input receiver, all other arguments are optional
    // We can perform input validations
    if(empty($prepend)) {
        throw new \InvalidArgumentException("prepend is required");
    }
    return $prepend . $input . $append;
});

$tpl = new Tonic("demo.html");

// Uncomment the following 2 lines to enable caching
//$tpl->enable_content_cache = true;
//$tpl->cache_dir = './cache/';
// Assign a variable to the template
$tpl->user_role = "member";

// Another method to assign variables:
$tpl->assign("currency","USD");

// Assign arrays to the template
$tpl->user = array(
	"name" => "Ricardo",
	"last_name" => "Gamba",
	"email" => "rgamba@gmail.com",
	"extra" => "This is a large description of the user"
);

// Assign a more complex array
$tpl->users = array(
	array(
		"name" => "rocio 'lavin'",
		"email" => "rlavin@gmail.com",
		"role" => "admin"
	),
	array(
		"name" => "roberto lopez",
		"email" => "rlopex@gmail.com",
		"role" => "member"
	),
	array(
		"name" => "rodrigo gomez",
		"email" => "rgomez@gmail.com",
		"role" => "member"
	)
);

$tpl->number = 10;

$tpl->js = '{"name" : "Ricardo", "last_name": "Gamba"}';
$tpl->array = array(
	"name" => "Ricardo",
	"last_name" => "Gamba"
);
$tpl->js_text = "Ricardo";
$tpl->ilegal_js = "javascript: alert('Hello');";

// Render the template
echo $tpl->render();
