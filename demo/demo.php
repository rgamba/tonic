<?php
error_reporting(E_ALL);
require_once("../Tonic.php");
Tonic::$local_tz = "America/Mexico_city";
// This will be present in all the templates
Tonic::setGlobals(array(
	"now" => @date_create()
));
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
$tpl->users = array(
	array(
		"name" => "rocio lavin",
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
// Render the template
echo $tpl->render();
