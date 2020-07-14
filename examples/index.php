<?php
	require "../src/Tonic.php";
	
	error_reporting(E_ALL);
	echo "Loading Tonic...";
	$tonic = new \NitricWare\Tonic();
	
	$array = array("Pineapple", "Lychee", "Mango");
	
	$tonic->assign("variable", "value");
	
	$tonic->assign("array", $array);
	
	$tonic->assign("integer", 6);
	
	$tonic->load("main.html");
	
	echo "Template Render starts here: ";
	
	echo $tonic->render();