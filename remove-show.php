<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}

$user_ID = (int) $_POST["user-id"];
$show_name = mysqli_real_escape_string($mysqli, $_POST['show-name']);
/*
$query = "DELETE FROM users_tracked_shows " .
"WHERE show_id IN " .
	"(SELECT show_id FROM shows WHERE title = '" . $show_name . "') "
. "AND user_id = " . $user_ID . "1;";
*/

$query = "DELETE FROM users_tracked_shows " .
"WHERE user_id = " . $user_ID . " " . 
"AND show_id IN (SELECT show_id FROM shows WHERE title = '" . $show_name . "');";


if ($result = $mysqli->query($query)) {
	echo "success";
	echo "<br/>Show Name was " . $show_name;
	echo "<br/>User ID was " . $user_ID;
	echo "<br/>potential error was " . $mysqli->error;
} else {
	echo "error: " . $mysqli->error;
	die();
}
?>