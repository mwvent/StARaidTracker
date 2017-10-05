<?php

// for debug - remove when done
ini_set('display_errors', 'On');
error_reporting(E_ALL);

// include the backend
define('APPROOTDIR',dirname(__FILE__) . '/api/');
define('APPLIBDIR',APPROOTDIR . 'lib/');
define('APPDATADIR',APPROOTDIR . 'data/');
require(APPLIBDIR . "raiddata.php");

// check a parameter has been supplied
function hasParm($keyName): bool {
	return isset( $_GET[$keyName] );
}

// check if an iphone/pad is being used
function isIOS(): bool {
	if(strstr($_SERVER['HTTP_USER_AGENT'],'iPhone')) {
		return true;
	}
	if(strstr($_SERVER['HTTP_USER_AGENT'],'iPad')) {
		return true;
	}
	return false;
}


if( !hasParm("placename") ) {
	die( "Invalid Parms" );
}

// lookup gym - throw fatal error on none
$raidData = new RaidData();
$gyms = $raidData->getPogoGyms()->getGymsByName($_GET["placename"]);
if( sizeof( $gyms ) < 1 ) {
	die("Cannot find " . $_GET["placename"]);
}

// get url encoded values ready
$gym = array_values($gyms)[0];
$lat = urlencode( $gym["lat"] );
$lng = urlencode( $gym["long"] );
$name = urlencode( $gym["name"] );

// Generate URL...
if ( isIOS() ) {
	// ... for IOS
	$parms = [ 
		"daddr=" . $lat. "," . $lng,
		"dirflg=d",
		"t=h"
	];
	$url = "http://maps.apple.com/?" . implode("&", $parms);
} else {
	// ... for everything else
	$parms = [ 
		"q=" . $lat . "," . $lng
	];
	$url = "http://maps.google.com/?" .  implode("&", $parms);
}

// debug+usage logging
$date = new DateTime();
$date = $date->format("ymd h:i:s");
$logmsg = $date . " " . $_SERVER['REMOTE_ADDR'] . " " .
			"sending user to " . $url . " for gym " . $_GET["placename"];
error_log( $logmsg . PHP_EOL, 3, "/home/www/pogosta/findgym/api/data/rw/accesslog");

// On your way happy PoGo player
header('Location: ' . $url);
die();
