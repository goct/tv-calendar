<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
//$username = mysqli_real_escape_string($mysqli, (string) $_POST["username"]);
$username = $_POST["username"];
$pw = $_POST["pw"];

//check if username is already in the database
$query = "SELECT user_name FROM users WHERE user_name = '" . $username . "';";

if ($result = $mysqli->query($query)) {
	if ($result->num_rows) {
		//username is already in db
		echo json_encode(array("status" => "duplicate user"));
		die();
	}
} else {
	echo json_encode(array("status" => "error", "errormsg" => "error 123: " . $mysqli->error));
	die();
}

while (true) {
	$salt = hash('sha256', mcrypt_create_iv(64, MCRYPT_DEV_URANDOM));
	$combined_hash = hash('sha256', $salt . $username . $pw);

	//check if combined hash already exists
	$query = "SELECT user_password "
	. "FROM users "
	. "WHERE user_password = '" . $combined_hash . "';";
	
	if ($result = $mysqli->query($query)) {
		if (!$result->num_rows) {
			//combined hash is unique, so we can enter it
			break;
		}
	} else {
		echo json_encode(array("status" => "error", "errormsg" => "error 1234: " . $mysqli->error));
		die();
	}
}

//enter newly registered user into the database
$query = "INSERT INTO users "
. "VALUES ("
. "null, "
. "'" . $username . "', "
. "'" . $combined_hash . "', "
. "CURDATE(), "
. "CURDATE(), "
. "true, "
. "null, "
. "'" . $salt . "', "
. "null);";

if ($result = $mysqli->query($query)) {
	echo json_encode(array("status" => "success", "username" => $username, "pw" => $pw));
} else {
	echo json_encode(array("status" => "error", "errormsg" => "couldn't put user into db: " . $mysqli->error));
	die();
}
?>