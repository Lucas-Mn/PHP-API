<?php

	// $query = $db->prepare('SELECT * FROM venues');
	// $query->execute();
	// $result = $query->fetchAll(PDO::FETCH_ASSOC);
	// var_dump($result);

	//config vars

	//util
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

	//inserts
	function insertVenue($db, string $name, float $x, float $y){
		$query = $db->prepare("INSERT INTO venues (name, pos_x, pos_y) VALUES('$name', $x, $y)");
		$query->execute(); }

	//queries
	function getById($db){
		$table = $_GET['table'];
		$statement = "SELECT * FROM $table WHERE id = ?;";
		$query = $db->prepare($statement);
		$query->bindValue(1, $_GET['id']);
		deliverResponse($query); }

	function getAll($db){
		$table = $_GET['table'];
		$query = $db->prepare("SELECT * FROM $table".buildOrder().";");
		deliverResponse($query); }

	function query($db){
		if(!isset($_GET['table'])){
			echo "no table selected";
			return; }
		else
			$table = $_GET['table'];

		if(isset($_GET['id'])) {
			getById($db);
			return; }

		if(isset($_GET['all'])){
			getAll($db);
			return; }

		//get parameters
		$parameters = array();
		if(isset($_GET['name']))
			$parameters['name'] = "%".$_GET['name']."%";
		if(isset($_GET['genre']))
			$parameters['genres'] = "%".$_GET['genre']."%";

		$rawstatement = "SELECT * FROM $table WHERE";

		//if there are no query parameters, getAll()
		$locational = (isset($_GET['latitude']) && isset($_GET['longitude']) && isset($_GET['range']));
		if(!$locational && count($parameters) <= 0) {
			getAll($db);
			return;
		}

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

		$rawstatement = $rawstatement.buildOrder().";";

		//prepare and deliver statement
		$query = $db->prepare($rawstatement);
		$index = 0; //index for parameters
		if($locational){
			$latitude = $_GET['latitude'];
			$longitude = $_GET['latitude'];
			$range = $_GET['range'];
			$query->bindValue(1, $latitude - $range, PDO::PARAM_STR);
			$query->bindValue(2, $latitude + $range, PDO::PARAM_STR);
			$query->bindValue(3, $longitude - $range, PDO::PARAM_STR);
			$query->bindValue(4, $longitude + $range, PDO::PARAM_STR);
			$index = 4; }
		foreach($parameters as $value){
			$query->bindValue(++$index, $value, PDO::PARAM_STR); }

		//messy, might want to make more elegant
		if($locational && isset($_GET['orderby']) && $_GET['orderby'] == 'proximity'){
			$query->bindValue(++$index, $_GET['latitude']);
			$query->bindValue(++$index, $_GET['longitude']);
		}
		deliverResponse($query); }

	//main
	try {
		$db = new PDO('mysql:host=*********;dbname=*******;port=****','*********','********');
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$query = $db->prepare("SET NAMES 'utf8'"); // necessary, otherwise everything breaks when selecting results with characters like é, á
		$query->execute(); }
	catch(PDOException $Exception) {
		echo($Exception->getMessage()); }

	if($_GET)
		query($db);
	else echo "lol no get";

?>
