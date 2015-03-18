<?php
require_once("../Tonic.php");
$tpl = new Tonic("demo.html");
$tpl->user_role = "member";
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
// This will be present in all the templates
$tpl->setGlobals(array(
	"now" => @date("Y-m-d H:i:s")
));

echo $tpl->render();