<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");

if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}

$query = "SELECT * FROM shows;";
$shows_array = array();

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		array_push($shows_array, $row['title']);
	}
}

echo json_encode($shows_array);
?>