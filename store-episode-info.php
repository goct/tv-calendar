<?php
$episode_info_obj = json_decode($_POST['episode-info-obj'], true);
$show_name = $_POST['show-name'];

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

foreach($episode_info_obj as $episode) {
	$query = "INSERT INTO episodes (show_id, title, air_date, season_num, episode_num) VALUES ("
	. "(SELECT id FROM shows WHERE title = '" . $show_name . "'), "
	. "'" . $mysqli->real_escape_string($episode['title']) . "', "
	. "'" . $episode['date'] . "', "
	. "" . $episode['season-num'] . ","
	. "" . $episode['num'] . ""
	. ");";
	$mysqli->query($query);
}

if (!$mysqli->error) {
	echo "query was " . $query;
} else {
	echo "an error occured while trying to put the data in the database: error is: " . $mysqli->error . "###########query was " . $query;
}
?>