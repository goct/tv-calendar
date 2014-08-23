<?php
$sleep_time = 10;
$max_load_attempts = 5;
$timestamp = time();
echo "starting the script</br>";

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
$show_ids = array();
$episodes_list = array();
$query = "SELECT show_id FROM shows";

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		array_push($show_ids, $row[show_id]);
	}
} else {
	echo "error: failed to select show ids from database";
	die();
}

foreach($show_ids as $id) {
	//get episode lists from tvrage xml feeds
	$load_attempts = 0;
	while (!($xml = simplexml_load_file("http://services.tvrage.com/feeds/episode_list.php?sid=" . $id))) {
		$load_attempts++;
		if ($load_attempts >= $max_load_attempts) {
			echo "<br/>###########Remote XML file request failed 5 times in a row. Stopping Script.################";
			die();
			
		}
		echo "<br/>Failed to load xml file for showID " . $id . " " . $load_attempts . " times. "
		. "Trying again in 11 seconds...<br/>";
		for ($i = 11; $i >= 0; $i--) {
			echo "<br/>" . $i;
			sleep(1);
		}
	}

	foreach($xml->Episodelist->Season as $season) {
		$season_num = mysqli_real_escape_string($mysqli, $season->attributes()->no);
		foreach($season->episode as $e) {
			$episode_num = mysqli_real_escape_string($mysqli, $e->seasonnum);
			$air_date = mysqli_real_escape_string($mysqli, $e->airdate);
			$episode_info_link = mysqli_real_escape_string($mysqli, $e->link);
			$episode_title = mysqli_real_escape_string($mysqli, $e->title);
			array_push($episodes_list, array(
				"show_id" => $id,
				"date" => $air_date,
				"season-num" => $season_num,
				"episode-num" => $episode_num,
				"title" => $episode_title,
				"episode-info-link" => $episode_info_link
				)
			);
		}
	}
	echo "<br/>generated info for show_id " . $id . ". Sleeping for " . $sleep_time . " seconds...<br/>";
	for ($i = $sleep_time; $i >= 0; $i--) {
		echo "<br/>" . $i;
		sleep(1);
	}
}

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

//insert non-duplicate episodes into db
foreach($episodes_list as $episode) {
	$query = "INSERT INTO episodes (show_id, title, air_date, season_num, episode_num, info_link, id) "
	. "VALUES ("
	. $episode["show_id"] . ", "
	. "'" . $episode["title"] . "', "
	. "'" . $episode["date"] . "', "
	. $episode["season-num"] . ", "
	. $episode["episode-num"] . ", "
	. "'" . $episode["episode-info-link"] . "', "
	. "null) "
	. "ON DUPLICATE KEY UPDATE air_date = '" . $episode["date"] . "';";
	
	$result = $mysqli->query($query);
	
	if ($result) {
		//echo "<br/>entered " . $episode["title"] . " - " . $episode["date"] . " successfully<br/>";
	} else {
		echo "<br/>error: failed to input episode into the database";
		echo "<br/>query was " . $query;
		echo "<br/>mysqli error was (" . $mysqli->errno . ") " . $mysqli->error . "</br>";
		die();
	}
}

echo "<br/>update complete!";
?>