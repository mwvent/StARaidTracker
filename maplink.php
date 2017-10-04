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
	return false;
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

// nice neat vars
$gym = array_values($gyms)[0];
$lat = $gym["lat"];
$lng = $gym["long"];
$name = $gym["name"];

// Generate URL
if ( isIOS() ) {
	// For IOS
	$parms = [ 
		"ll=" . urlencode( $lat ) . "," . urlencode( $lng )
	];
	$url = "http://maps.apple.com/?" . implode("&", $parms);
} else {
	// Everything else
	$parms = [ 
		"q=" . urlencode( $lat ) . "," . urlencode( $lng )
	];
	$url = "http://maps.google.com/?" .  implode("&", $parms);
}

// debug+usage logging
$date = new DateTime();
$date = $date->format("ymd h:i:s");
$logmsg = $date . " " . $_SERVER['REMOTE_ADDR'] . " " .
			"sending user to " . $url . " for gym " . $_GET["placename"];
error_log( $logmsg . PHP_EOL, 3, "/home/www/pogosta/findgym/api/data/rw/accesslog");
//echo($url);
header('Location: ' . $url);
die();
