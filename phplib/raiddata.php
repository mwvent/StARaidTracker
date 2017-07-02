<?php
require("mapdata.php");

// All info about a Pokemon Go Raid
class PogoRaid {
	private $startTime;
	private $pokemon = "???";
	private $gymUID;
	private $raidData;
	
	function __construct( RaidData $raidData ) {
		$this->raidData = $raidData;
	}
	
	public function setStartTime( DateTime $newTime) {
		$this->startTime = $newTime;
	}
	
	public function getStartTime() : DateTime { 
		return $this->startTime;
	}
	
	public function setGymUID( int $newGymUID ) {
		$this->gymUID = $newGymUID;
	}
	
	public function getGym() : PogoGym {
		return $this->raidData->getMapData()->getGymByUID( $this->gymUID );
	}
	
	public function setPokemon( string $newPokemon ) {
		$this->pokemon = $newPokemon;
	}
	
	public function getPokemon( ) : string {
		return $this->pokemon;
	}
	
	public function hasHatched () : bool {
		return ( new DateTime() > $this->startTime ) ? true : false;
	}
	
	public function timeUntilHatch_asSeconds () : int {
		if ( $this->hasHatched() ) {
			return 0;
		}
		$now = new DateTime();
		$diff = $this->startTime->getTimestamp() - $now->getTimestamp();
		return $diff;
	}
	
	public function save() {
			$this->raidData->saveRaidData( $this );
	}
	
	public function asArray() {
			return [
				"startTime" => $this->startTime->getTimestamp(),
				"pokemon" => $this->getPokemon(),
				"gyminfo" => $this->getGym()->asArray()
			];
	}
}

// Handles database access to raid information and linking to mapdata
class RaidData {
	private $db;
	private $mapData;
	private $activeRaids = [];
	private $useBaseLocation = false;
	private $fromLat;
	private $fromLong;
	
	function __construct() {
		$this->db = new SQLite3('db/raiddb.db');
		$this->mapData = new MapData();

		// create a db table if not exists
		$sql = "
			CREATE TABLE IF NOT EXISTS 'raids' (
  				GYMUID INT PRIMARY KEY NOT NULL,
  				POKEMON TEXT,
  				RAIDSTART INTEGER NOT NULL
			);
		";
		if( ! $this->db->query($sql) ) {
			throw new Exception("Internal error - cannot create main db table");
		}
		
		// load active raids at statup
		$ignoreRaidsBefore = new DateTime( "-1 hour" );
		$ignoreRaidsBefore = $ignoreRaidsBefore->getTimestamp();
		$sql = "SELECT GYMUID, RAIDSTART, POKEMON FROM 'raids' WHERE RAIDSTART > :ignoreRaidsBefore;";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':ignoreRaidsBefore', $ignoreRaidsBefore);
		$result = $stmt->execute();
		while( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			$newPogoRaid = new PogoRaid($this);
			$newPogoRaid->setStartTime( new DateTime( "@" . $row['RAIDSTART'] ) );
			$newPogoRaid->setGymUID( $row['GYMUID'] );
			$newPogoRaid->setPokemon( $row['POKEMON'] );
			$this->activeRaids[ $row['GYMUID'] ] = $newPogoRaid;
		}
		
		if( ! $this->db->query($sql) ) {
			throw new Exception("Internal error - cannot create main db table");
		}
	}
	
	// set a base location for sorting point output by distance
	public function setBaseLocation(float $lat, float $long)  {
		$this->useBaseLocation = true;
		$this->fromLat = $lat;
		$this->fromLong = $long;
		$this->mapData->setBaseLocation($lat, $long);
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
	
	// put or update raid info into database
	public function saveRaidData( PogoRaid $raid ) {
		$sql = "
			INSERT OR REPLACE INTO 
				raids(GYMUID, POKEMON, RAIDSTART) 
			VALUES
				(:gymuid, :pokemon, :raidstart)
		";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':gymuid', $raid->getGym()->getUID());
		$stmt->bindValue(':pokemon', $raid->getPokemon());
		$stmt->bindValue(':raidstart', $raid->getStartTime()->getTimestamp());
		$stmt->execute();
	}
	
	// take in new raid data
	public function newRaidData( PoGoGym $gym, DateTime $startTime, string $pokemon = "???" ) {
		$newPogoRaid = new PogoRaid($this);
		$newPogoRaid->setStartTime( $startTime );
		$newPogoRaid->setGymUID( $gym->getUID() );
		$newPogoRaid->setPokemon( $pokemon );
		$this->activeRaids[ $gym->getUID() ] = $newPogoRaid;
		$this->activeRaids[ $gym->getUID() ]->save();
	}

	public function getActiveRaids() : array {
		if( ! $this->getUseBaseLocation() ) {
			return $this->activeRaids;
		}
		$gymDistances = [];
		foreach( $this->activeRaids as $raid ) {
			$gymDistances[ $raid->getGym()->getUID() ] = $raid->getGym()->kmFromBase();
		}
		$id_list = array_keys($gymDistances);
		$distance_list = array_values($gymDistances);
		array_multisort($distance_list,$id_list);
		$distance_list = array_combine($id_list,$distance_list);
		$returnArray = [];
		foreach( $distance_list as $gymUID => $gymDist ) {
			$returnArray[ $gymUID ] = $this->activeRaids[ $gymUID ];
		}
		return $returnArray;
	}
	
	// Get Raid data as array of arrays rather than objects
	public function getActiveRaidsAsArray() : array {
		$returnArray = [];
		foreach($this->getActiveRaids() as $raid) {
			$returnArray[$raid->getGym()->getUID()] = $raid->asArray();
		}
		return $returnArray;
	}
	
	public function getMapData() : MapData {
		return $this->mapData;
	}
}
