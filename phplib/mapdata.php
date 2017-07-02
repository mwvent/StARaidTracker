<?php

// Define an area of a map contaning PoGo Gyms
class PogoArea {
	private $name;
	private $poly;
	private $mapData;

	function __construct($newMapData) {
		$this->mapData = $newMapData;
	}

	public function setName( string $newName ) {
		$this->name = $newName;
	}
	public function setPoly( string $newPoly ) {
		$this->poly = $newPoly;
	}
	public function getName() : string {
		return $this->name;
	}
	public function getPoly() : string {
		return $this->poly;
	}
	
	// Return an array of Gym object residing inside the area
	// TODO this relies of manual tagging of the Gym to area
	// in the spreadsheet - a check to see which gyms are inside this
	// areas polygonm points will be much cooler and save work tagging
	// future Gyms
	public function getGyms() : array {
		$returnArray = [];
		foreach( $this->mapData->getGyms() as $currentGym ) {
			if( $currentGym->getArea()->getName() == $this->getName() ) {
				$returnArray[] = $currentGym;
			}
		}
		return $returnArray;
	}
}

// A PogoGym object
class PogoGym {
	private $name;
	private $uid;
	private $url;
	private $lat;
	private $long;
	private $areaName;
	private $mapData;

	function __construct($newMapData) {
		$this->mapData = $newMapData;
	}

	public function setName( $newName ) {
		$this->name = $newName;
	}
	public function setUID( $newUID ) {
		$this->uid = $newUID;
	}
	public function setURL( $newURL ) {
		$this->url = $newURL;
	}
	public function setLat( $newLat ) {
		$this->lat = $newLat;
	}
	public function setLong( $newLong ) {
		$this->long = $newLong;
	}
	public function setAreaName( $newAreaName ) {
		$this->areaName = $newAreaName;
	}

	public function getName( ) {
		return $this->name;
	}
	public function getUID( ) {
		return $this->uid;
	}
	public function getURL( ) {
		return $this->url;
	}
	public function getLat( ) {
		return $this->lat;
	}
	public function getLong( ) {
		return $this->long;
	}
	// Return the parent Area
	// TODO - as above relies on manual tagging of the Gyms area on the 
	// speadsheet - replace with check to see which polygon the gym is
	// inside of
	public function getArea( ) {
		if ( array_key_exists ( $this->areaName, $this->mapData->getAreas() ) ) {
			return $this->mapData->getAreas() [ $this->areaName ];
		}
		// handle area not exist error
		$nullarea = new PogoArea($this->mapData);
		$nullarea->setName( "Err" );
		return $nullarea;
	}
	
	public function asArray() {
		return [
			"name" => $this->getName(),
			"uid" => $this->getUID(),
			"url" => $this->getURL(),
			"lat" => $this->getLat(),
			"long" => $this->getLong(),
			"dist" => $this->kmFromBase()
		];
	}
	
	public function kmFromBase() {
		if ( ! $this->mapData->getUseBaseLocation() ) {
			return 0;
		}
		$lat = $this->mapData->getBaseLat();
		$long = $this->mapData->getBaseLong();
		// https://en.wikipedia.org/wiki/Haversine_formula
		$theta = $long - $this->getLong();
		$dist = sin(deg2rad($lat)) * sin(deg2rad($this->getLat())) 
				+ cos(deg2rad($lat)) * cos(deg2rad($this->getLat()))
				* cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$km = ( $dist * 60 * 1.1515 ) * 1.609344;
		return $km;
	}
}

// Load ( from a hardcoded google sheet url ) an array of Gyms and Areas and manage
// access to said list
class MapData {
	// hardcoded sheet key and ids for relevant tabs
	// sheet ids from 
	// https://spreadsheets.google.com/feeds/worksheets/
	//   1qXcVKDbSmTubvmUIyTfZD0qmmihkjE2nNZy8hEhlAOs/private/full
	private $sheetkey = "1qXcVKDbSmTubvmUIyTfZD0qmmihkjE2nNZy8hEhlAOs";
	private $sheettabs = [ "areas" => "oowygi8" , "gyms" => "otrv40q" ];
	// Data arrays
	private $gyms = [];
	private $areas = [];
	// Base location for sorting output
	private $useBaseLocation = false;
	private $fromLat;
	private $fromLong;

	// Get the url of the google sheet
	private function getSheetUrl(string $tab) : string {
		if( ! array_key_exists($tab, $this->sheettabs) ) {
			throw new Exception("Internal error - called getSheetUrl with unknown tab name " . $tab);
		}
		return "https://spreadsheets.google.com/feeds/list/".$this->sheetkey."/".$this->sheettabs[$tab]."/public/values?alt=json";
	}

	// Perform the actual grab of json data from a google sheets sheet
	private function pullDataFromSheetTab($tab) {
		$url = $this->getSheetUrl($tab);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		$response = curl_exec($curl);
		if(curl_error($curl)) {
			$errtxt = "Cannot pull gym data from google sheets:" . curl_error($curl);
			throw new Exception($errtxt);
		}
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		// TODO check response code
		$areadata = json_decode($response);
		return $areadata;
	}

	// Get data from a column given a spreadsheet entry/row
	// TODO check it actually exists and throw a friendly error
	private function pullTextFromEntryColumn($entryData, $columnName) {
		$textProperty = "\$t";
		$colProperty = "gsx\$" . $columnName;
		return $entryData->$colProperty->$textProperty;
	}
	
	// Data is loaded and stored upon construction
	function __construct() {		
		$areaSheetData = $this->pullDataFromSheetTab("areas");	
		foreach($areaSheetData->feed->entry as $entrydata) {
			$newArea = new PogoArea($this);
			$newArea->setName( $this->pullTextFromEntryColumn($entrydata, "areaname") );
			$newArea->setPoly( $this->pullTextFromEntryColumn($entrydata, "polypoints") );
			$this->areas[ $this->pullTextFromEntryColumn($entrydata, "areaname") ] = $newArea;
		}
	
		$gymSheetData = $this->pullDataFromSheetTab("gyms");
		foreach($gymSheetData->feed->entry as $entrydata) {
			$newGym = new PogoGym($this);
			$newGym->setName( $this->pullTextFromEntryColumn($entrydata, "gymname") );
			$newGym->setUID( $this->pullTextFromEntryColumn($entrydata, "infourluid") );
			$newGym->setURL( $this->pullTextFromEntryColumn($entrydata, "infourl") );
			$newGym->setLat( $this->pullTextFromEntryColumn($entrydata, "lat") );
			$newGym->setLong( $this->pullTextFromEntryColumn($entrydata, "long") );
			$newGym->setAreaName( $this->pullTextFromEntryColumn($entrydata, "areaname") );
			$this->gyms[ $this->pullTextFromEntryColumn($entrydata, "infourluid") ] = $newGym;
		}		
	} 
	
	// set a base location for sorting point output by distance
	public function setBaseLocation(float $lat, float $long)  {
		$this->useBaseLocation = true;
		$this->fromLat = $lat;
		$this->fromLong = $long;
	}
	
	// return base location info
	public function getUseBaseLocation() {
		return $this->useBaseLocation;
	}
	public function getBaseLat() {
		return $this->fromLat;
	}
	public function getBaseLong() {
		return $this->fromLong;
	}

	// Allow access to Gyms array
	public function getGyms() {
		if ( ! $this->useBaseLocation ) {
			return $this->gyms;
		}
		$gymDistances = [];
		foreach( $this->gyms as $gym ) {
			$gymDistances[ $gym->getUID() ] = $gym->kmFromBase();
		}
		$id_list = array_keys($gymDistances);
		$distance_list = array_values($gymDistances);
		array_multisort($distance_list,$id_list);
		$distance_list = array_combine($id_list,$distance_list);
		$returnArray = [];
		foreach( $distance_list as $gymUID => $gymDist ) {
			$returnArray[ $gymUID ] = $this->gyms[ $gymUID ];
		}
		return $returnArray;
	}
	
	// Get Gym data as array of arrays rather than objects
	public function getGymsAsArray() : array {
		$returnArray = [];
		foreach($this->getGyms() as $gym) {
			$returnArray[$gym->getUID()] = $gym->asArray();
		}
		return $returnArray;
	}
	
	// Specifically find a Gym object by UID - return an empty Gym
	// if not found
	public function getGymByUID(int $UID) : PogoGym {
		if( array_key_exists($UID, $this->gyms) ) {
			return $this->gyms [ $UID ]; 
		} else {
			$nullGym = new PogoGym($this);
			$nullGym->setName("Err cannot find gym with UID " . $UID);
			return $nullGym;
		}
	}
	
	// Allow access to the Areas array
	public function getAreas() {
		return $this->areas;
	}
}


