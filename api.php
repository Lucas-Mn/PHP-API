<?php

	//requirements
	require('api_inserts.php');

	//config
	function dfn(string $key, $value){
		if(!defined($key)) define($key, $value);
	}
	dfn('DEBUG', false);
	dfn('MAX_ARTISTS_PER_GIG', 10);
	dfn('MAX_QUERY_RESULTS', 3);
	dfn('DEFAULT_ORDER', 'ASC');

	//util
	class util{
		public static function error(string $message){
			echo('{"error":{"message":"'.$message.'"}}'); }
	}
	$util = new util();
	
	function buildOrder(){
		//defaults
		$orderby = "entry_time";
		$order = "DESC";

		//parameters
		if(isset($_GET['orderby']))
			$orderby = $_GET['orderby'];
		if(isset($_GET['order']))
			$order = $_GET['order'];

		if($orderby == "proximity")
			return " ORDER BY ABS(? - latitude) + ABS(? - longitude) $order";
		return " ORDER BY $orderby $order"; }

	function deliverResponse($query){
		$query->execute();
		$data = $query->fetchAll(PDO::FETCH_ASSOC);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data); }

	function action_gigs_in_venue(){
		global $db;
		global $util;

		if(!isset($_GET['id']))
			return $util::error("no id provided");
		$filter = "";
		if(isset($_GET['filter']))
			switch($_GET['filter']){
				case 'upcoming':
					$filter = "AND start_time > NOW()"; break;
				case 'past':
					$filter ="AND start_time < NOW()"; break; }

		$limit = MAX_QUERY_RESULTS;
		if(isset($_GET['limit']) && $_GET['limit'] < MAX_QUERY_RESULTS)
			$limit = $_GET['limit'];
		$query = $db->prepare("SELECT * FROM gigs WHERE venue_id = :id $filter ORDER BY start_time ASC LIMIT :limit;");
		$query->bindValue(':id', $_GET['id'], PDO::PARAM_INT);
		$query->bindValue(':limit', $limit, PDO::PARAM_INT);
		deliverResponse($query);
	}

	//queries
	function query($db){
		global $util;

		//action or query?
		if(isset($_GET['action']))
			switch($_GET['action']){
				case ('gigsinvenue'):
					return action_gigs_in_venue();
				default:
					return $util::error("invalid action"); }

		if(!isset($_GET['table'])){
			echo "no table selected";
			return; }	

		//defaults
		$limit = MAX_QUERY_RESULTS;

		//get parameters
		$parameters = array();
		if(isset($_GET['name']))
			$parameters['name'] = "%".$_GET['name']."%";
		if(isset($_GET['genre']))
			$parameters['genres'] = "%".$_GET['genre']."%";
		if(isset($_GET['id'])){
			$parameters['id'] = "%".$_GET['id']."%";
			$limit = 1; }

		$rawstatement = "SELECT * FROM ".$_GET['table'];

		//if there are no query parameters, getAll()
		$locational = (isset($_GET['latitude']) && isset($_GET['longitude']) && isset($_GET['range']));
		if($locational || count($parameters) > 0) {
			$rawstatement = $rawstatement." WHERE"; }

		//add locational parameters to statement if all of them are present
		$paramand = ""; //we change this if the query is locational, because additional params will need an AND
		if($locational){
			$rawstatement = $rawstatement." (latitude BETWEEN ? AND ?) AND (longitude BETWEEN ? AND ?)";
			$locational = true;
			$paramand = " AND"; }

		//add parameters to statement
		foreach($parameters as $key => $value){
			$rawstatement = $rawstatement.$paramand." $key LIKE ?";
			$paramand = " AND"; }

		$rawstatement = $rawstatement.buildOrder()." LIMIT ?;";

		//prepare and deliver statement
		$query = $db->prepare($rawstatement);
		$index = 0; //index for parameters
		if($locational){ //bind location parameters
			$latitude = $_GET['latitude'];
			$longitude = $_GET['latitude'];
			$range = $_GET['range'];
			$query->bindValue(1, $latitude - $range, PDO::PARAM_STR);
			$query->bindValue(2, $latitude + $range, PDO::PARAM_STR);
			$query->bindValue(3, $longitude - $range, PDO::PARAM_STR);
			$query->bindValue(4, $longitude + $range, PDO::PARAM_STR);
			$index = 4; }
		foreach($parameters as $value){ //bind query paramaters
			$query->bindValue(++$index, $value, PDO::PARAM_STR); }

		//bind order
		//messy, might want to make more elegant
		if($locational && isset($_GET['orderby']) && $_GET['orderby'] == 'proximity'){
			$query->bindValue(++$index, $_GET['latitude']);
			$query->bindValue(++$index, $_GET['longitude']); }
		$query->bindValue(++$index, $limit, PDO::PARAM_INT); //bind LIMIT
		deliverResponse($query); }

	//main
	try {
		$db = new PDO('mysql:host=*********;dbname=*********;port=****','*********','************');
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$query = $db->prepare("SET NAMES 'utf8'"); // necessary, otherwise everything breaks when selecting results with characters like é, á
		$query->execute(); }
	catch(PDOException $Exception) {
		echo($Exception->getMessage()); }

	if($_GET)
		query($db);
	else { 
		echo "lol no get";
	}

?>
