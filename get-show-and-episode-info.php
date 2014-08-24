<?php
date_default_timezone_set('America/Vancouver');

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}

//$shows_to_get_string = mysqli_real_escape_string($mysqli, $_POST["shows-to-get"]);
$shows_to_get_string = $mysqli->real_escape_string($_POST["shows-to-get"]);
$recently_aired_episodes = array();
$next_airing_episodes = array();
$now = new DateTime();
$now_date_string = date_format($now, "Y-m-d");
$now_date = new DateTime($now_date_string);

if ($shows_to_get_string == "all") {
	//select the most recently aired episode for every show
	$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date AS recent_air_date
	FROM episodes, shows,
	(SELECT show_id, MAX(air_date) AS recent_air_date FROM episodes WHERE air_date < CURDATE() GROUP BY show_id) AS recent_episodes
	WHERE episodes.air_date = recent_episodes.recent_air_date 
	AND episodes.show_id = recent_episodes.show_id 
	AND shows.show_id = recent_episodes.show_id 
	GROUP BY show_name
	ORDER BY air_date DESC;";
} else {
	//select most recently aired episodes for each show in the string
	$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date AS recent_air_date
	FROM episodes, shows,
	(SELECT show_id, MAX(air_date) AS recent_air_date FROM episodes WHERE air_date < CURDATE() GROUP BY show_id) AS recent_episodes
	WHERE episodes.air_date = recent_episodes.recent_air_date 
	AND episodes.show_id = recent_episodes.show_id 
	AND shows.show_id = recent_episodes.show_id 
	AND shows.title IN ('" . $shows_to_get_string . "') 
	GROUP BY show_name
	ORDER BY air_date DESC;";
}

//package the recently aired episodes into an array
if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		$episode_date = date_create($row["recent_air_date"]);
		$episode_date->setTime(0, 0, 0);
		$now_date->setTime(0, 0, 0);
		$time_diff = date_diff($now_date, $episode_date);
		$days_airing_in = $time_diff->format("%a");
		
		array_push($recently_aired_episodes, array
			(
			"show_name" => $row["show_name"],
			"season_num" => $row["season_num"],
			"episode_num" => $row["episode_num"],
			"episode_title" => $row["episode_title"],
			"air_date" => $row["recent_air_date"],
			"days_airing_in" => $days_airing_in * -1
			)
		);
	}
} else {
	echo "failed!";
}

//get all show names
$query = "SELECT * FROM shows;";
$all_show_names = array();

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		array_push($all_show_names, $row['title']);
	}
}

if ($shows_to_get_string == "all") {
	//select the episode that airs next for every show
	$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date, shows.last_updated 
	FROM shows, episodes, 
		(SELECT show_id, MIN(air_date) AS latest_air_date FROM episodes WHERE air_date >= '" . $now_date_string . "' GROUP BY show_id) AS latest_episodes 
	WHERE episodes.air_date = latest_episodes.latest_air_date 
	AND episodes.show_id = latest_episodes.show_id 
	AND shows.show_id = latest_episodes.show_id 
	GROUP BY shows.title 
	ORDER BY air_date 
	ASC;";
} else {
	//select the episode that airs next for each show in the string
	$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date, shows.last_updated 
	FROM shows, episodes, 
		(SELECT show_id, MIN(air_date) AS latest_air_date FROM episodes WHERE air_date >= '" . $now_date_string . "' GROUP BY show_id) AS latest_episodes 
	WHERE episodes.air_date = latest_episodes.latest_air_date 
	AND episodes.show_id = latest_episodes.show_id 
	AND shows.show_id = latest_episodes.show_id 
	AND shows.title IN ('" . $shows_to_get_string . "') 
	GROUP BY shows.title 
	ORDER BY air_date 
	ASC;";
}

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		$episode_date = date_create($row["air_date"]);
		$episode_date->setTime(0, 0, 0);
		//we already set now date time to 0 0 0
		$time_diff = date_diff($now_date, $episode_date);
		$days_airing_in = $time_diff->format("%a");
		
		array_push($next_airing_episodes, array
			(
			"show_name" => $row["show_name"],
			"season_num" => $row["season_num"],
			"episode_num" => $row["episode_num"],
			"episode_title" => $row["episode_title"],
			"air_date" => $row["air_date"],
			"last_updated" => $row["last_updated"],
			"days_airing_in" => $days_airing_in
			)
		);
	}
} else {
	echo "failed!";
}

echo json_encode(array("all show names" => $all_show_names, "recently aired episodes" => $recently_aired_episodes, "next airing episodes" => $next_airing_episodes));
/*
echo "<pre>";
print_r($next_airing_episodes);
echo "</pre>";
*/
?>