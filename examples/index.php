<?php
	require "../src/Tonic.php";
	
	error_reporting("E_ALL");
	
	$tonic = new \NitricWare\Tonic();
	
	$array = array("Pineapple", "Lychee", "Mango");
	
	$tonic->assign("array", $array);
	
	$tonic->load("main.html");
	
	echo $tonic->render();