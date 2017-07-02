<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
require("phplib/raiddata.php");

class RaidTrackerAPI {
	var $returnData = [];
	var $status = [ "haserror" => 0, "errtext" =>"" ];
	var $PARMS; // store a copy of $_GET

	// call the method handler on initialisation - catch errors
	function __construct(array $PARMS) {
		$this->PARMS = $PARMS;
		$status[ "haserror	" ] = 0;
		try {
			$this->handleMethod( $this->getStringParm( "method" ));
		} catch (Exception $e) {
			$this->status["errtext"] = $e->getMessage();
			$this->status["haserror"] = 1;
		}
	}
	
	// test for parameters
	public function hasParm(string $key) {
		return isset ( $this->PARMS[$key] );
	}
		
	// use this method to pull expected parameters
	public function getStringParm(string $key) {
		if ( ! isset ( $this->PARMS[$key] ) ) {
			throw new Exception ( "Missing required parameter: $key" );
		}
		return $this->PARMS [ $key ];
	}
	
	// method handler switchboard
	public function handleMethod( $method ) {
		switch (strtolower($method)) {
			case "ping" :
				$this->returnData[]="pong";
				break;
			case "getgyms" :
				$raidData = new RaidData();
				if ( $this->hasParm("lat" ) and $this->hasParm("long") ) {
					$lat = (float)$this->getStringParm("lat");
					$long = (float)$this->getStringParm("long");
					$raidData->setBaseLocation( $lat, $long );
				}
				$this->returnData = $raidData->getMapData()->getGymsAsArray(); 
				break;
			case "getraids" :
				$raidData = new RaidData();
				if ( $this->hasParm("lat" ) and $this->hasParm("long") ) {
					$lat = (float)$this->getStringParm("lat");
					$long = (float)$this->getStringParm("long");
					$raidData->setBaseLocation( $lat, $long );
				}
				$this->returnData = $raidData->getActiveRaidsAsArray(); 
				break;
			default:
				throw new Exception ( "Unknown method: " .$method );
				break;
		}
	}

	public function getJSON() {
		$jsonArray = [ "data" => $this->returnData, "status" => $this->status ];
		return json_encode( $jsonArray );
	}
	
}


$api = new RaidTrackerAPI( $_GET );
echo $api->getJSON();
