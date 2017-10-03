<?php

function hasParm($keyName) {
	return isset( $_GET[$keyName] );
}

function isIOS() {
	if(strstr($_SERVER['HTTP_USER_AGENT'],'iPhone')) {
		return true;
	}
	if(strstr($_SERVER['HTTP_USER_AGENT'],'iPad')) {
		return true;
	}
	return false;
}




if( !hasParm("placename") | !hasParm("lng") | !hasParm("lat") ) {
	die( "Invalid Parms" );
}

$placename = $_GET["placename"];
$lat = $_GET["lat"];
$lng = $_GET["lng"];

if ( isIOS() ) {
	$url = "http://maps.apple.com/?ll=" .
			urlencode($lat) . 
			"," . 
			urlencode($lng);
} else {
	$url = "http://maps.google.com/?q=" .
			urlencode($lat) . 
			"," . 
			urlencode($lng);
}

header('Location: ' . $url);
die();
