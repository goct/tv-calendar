<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

$user_id = $_POST["user-id"];
$newly_tracked_show_names = json_decode($_POST["newly-tracked-shows"]);

foreach($newly_tracked_show_names as $show_name) {
	$query = "SELECT show_id "
	. "FROM shows "
	. "WHERE title = '" . $show_name . "';";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$show_id = $row["show_id"];
		
		$query = "INSERT INTO users_tracked_shows "
		. "SET user_id = " . $user_id . ", "
		. "show_id = " . $show_id . ";";
		if ($result = $mysqli->query($query)) {
			echo "success";
		}
	}
}
?>