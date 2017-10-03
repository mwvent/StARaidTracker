<?php
require(APPLIBDIR . "pogogym.php");
require(APPLIBDIR . 'spreadsheet-reader/SpreadsheetReader.php');

class PogoGyms {
	// Data arrays
	private $gyms = [];
	// Base location for sorting output
	private $useBaseLocation = false;
	private $fromLat;
	private $fromLong;


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
		$reader = new SpreadsheetReader(APPDATADIR . 'Gyms.ods');
		// todo - read these values from row 0, error if not exist
		$COLS = [ "name" => 0, "UID" => 6, "URL" => 1, "LAT" => 7, "LONG" => 8 ];
		foreach ($reader as $row) {
			$newGym = new PogoGym($this);
			$newGym->setName( $row[$COLS["name"]] );
			$newGym->setUID( $row[$COLS["UID"]] );
			// $newGym->setURL( $row[$COLS["URL"]] );
			$newGym->setLat( $row[$COLS["LAT"]] );
			$newGym->setLong( $row[$COLS["LONG"]] );
			$this->gyms[ $row[$COLS["UID"]] ] = $newGym;
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
	
	// Find Gyms By Name
	public function getGymsByName(string $searchtxt) : array {
		$returnArray = [];
		foreach($this->getGyms() as $gym) {
			if( $gym->nameContains( $searchtxt ) ) {
				$returnArray[ $gym->getUID() ] = $gym->asArray();
			}
		}
		return $returnArray;
	}
	
	// Allow access to the Areas array
	public function getAreas() {
		return $this->areas;
	}
}


