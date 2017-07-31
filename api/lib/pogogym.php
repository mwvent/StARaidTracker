<?php
class PogoGym {
	private $name;
	private $uid;
	private $url;
	private $lat;
	private $long;
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
