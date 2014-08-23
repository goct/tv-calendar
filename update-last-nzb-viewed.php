<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}
$user_id = (int) mysqli_real_escape_string($mysqli, $_POST["user_id"]);
$last_nzb_id = (int) mysqli_real_escape_string($mysqli, $_POST["last_nzb_id"]);

$query = "UPDATE users SET last_nzb_id_viewed = " . $last_nzb_id . 
" WHERE user_id = " . $user_id . ";";

if ($mysqli->query($query)) {
	echo "success";
} else {
	echo "error: " . $mysqli->error;
}

?>