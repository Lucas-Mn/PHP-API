<?php

	//util
	function debug(string $message){
		if(DEBUG) echo $message."<br>"; }

	function insert(){
		if(!isset($_POST['table']))
			return error("no table provided");
		switch($_POST['table']){
			case 'gigs':
				insertGigPost();
				break;
			default:
				return error("invalid table"); }
	}

	function insertGigPost(){
		if(!isset($_POST['name']) || strlen($_POST['name']) < 1)
			return error("no name provided");
		if(!isset($_POST['venue_id']))
			return error("no venue_id provided");
		insertGig($_POST['name'], $_POST['description'], $_POST['venue_id'], $_POST['artist_ids'], $_POST['schedules']);
	}

	//$schedules - array of associative arrays with keys artist_id, start_time
	//MUST BE ORDERED BY START TIME, ASCENDING
	function insertGig(string $name, string $description, $venue_id, array $artist_ids, array $schedules){
		debug("insertGig: ");

		global $db;

		//get venue
		$query = $db->prepare("SELECT * FROM venues WHERE id = ? LIMIT 1");
		$query->bindValue(1, $venue_id);
		$query->execute();
		$fetch = $query->fetchAll(PDO::FETCH_ASSOC);
		if(count($fetch)<=0)
			return error("Invalidad venue ID");
		$venue = $fetch[0];
		debug("-got venue ".$venue["name"]);

		//check parameter errors
		if(count($artist_ids)<=0)
			return error("no artist ids provided");
		if(count($schedules)<=0)
			return error("no schedules providad");
		if(count($artist_ids) != count($schedules))
			return error("amount of artist ids and schedules aren't equal");
		if(count($artist_ids)>MAX_ARTISTS_PER_GIG)
			return error("too many artists; max is ".MAX_ARTISTS_PER_GIG);
		//building the query
		$statement = "SELECT * FROM artists WHERE id IN (?";
		//add parameter for each artist
		for($i = 1; $i < count($artist_ids); ++$i) //counting from 1 because the first ? is already in the statement
			$statement = $statement.', ?';
		$query = $db->prepare($statement.');');
		//bind values and execute
		for($i = 0; $i < count($artist_ids); ++$i)
			$query->bindValue($i+1, $artist_ids[$i], PDO::PARAM_INT);
		$query->execute();
		$artists = $query->fetchAll(PDO::FETCH_ASSOC);
		//DEBUG
		if(DEBUG) {
			debug("-got artists: ");
			foreach ($artists as $x)
				echo("--".$x['name'])."<br>"; }
		//check for errors
		//missing IDs
		if(count($artists) != count($schedules))
			return $util::error("one or more invalid artist IDs");
		//build genres label
		$genre_array = array();
		foreach($artists as $x)
			foreach(explode(',', $x['genres']) as $genre)
				if(!in_array($genre, $genre_array))
					array_push($genre_array, $genre);
		$genres = '';
		for($i = 0; $i < count($genre_array); ++$i)
			$genres = $genres.$genre_array[$i].($i == count($genre_array)-1 ? '':',');
		//debug
		debug('-genres: '.$genres);

		//insert gig
		$query = $db->prepare("INSERT INTO gigs(name, latitude, longitude, description, genres, start_time, entry_time, venue_id) ".
						"VALUES (:name, :latitude, :longitude, :description, :genres, :start_time, NOW(), :venue_id);");
		$query->bindValue(':name', $name);
		$query->bindValue(':latitude', $venue['latitude']);
		$query->bindValue(':longitude', $venue['longitude']);
		$query->bindValue(':description', $description);
		$query->bindValue(':genres', $genres);
		$query->bindValue(':start_time', $schedules[0]);
		$query->bindValue(':venue_id', $venue_id, PDO::PARAM_INT);
		$query->execute();
		//insert schedules
		$gig_id = $db->lastInsertId();
		$statement = "INSERT INTO schedules(gig_id, artist_id, start_time) VALUES";
		$comma = ''; //turns into a comma after first row is added
		foreach($schedules as $x){
			$statement = $statement.$comma."(?, ?, ?)";
			$comma = ','; }
		$statement = $statement.";";
		$query = $db->prepare($statement);
		//bind parameters
		$index = 0;
		for($i = 0; $i < count($schedules); ++$i){ //bind parameters for each row
			$query->bindValue(++$index, $gig_id, PDO::PARAM_INT);
			$query->bindValue(++$index, $artist_ids[$i]);
			$query->bindValue(++$index, $schedules[$i]); }
		$inserted_id = $db->lastInsertId();
		$query->execute();

		//get inserted item
		$query = $db->prepare("SELECT * FROM gigs WHERE id = :id;");
		$query->bindValue(':id', $inserted_id, PDO::PARAM_INT);
		deliverResponse($query);
	}

?>