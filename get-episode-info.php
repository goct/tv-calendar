<?php
date_default_timezone_set('America/Los_Angeles');

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}

//$shows_to_get_string = mysqli_real_escape_string($mysqli, $_POST["shows-to-get"]);
$shows_to_get_string = $_POST["shows-to-get"];
$recently_aired_episodes = array();
$next_airing_episodes = array();
$now = new DateTime();
$now_date_string = date_format($now, "Y-m-d");
$now_date = new DateTime($now_date_string);
/*
//select the most recently aired episode for each show
$query = "SELECT * "
. "FROM (SELECT * FROM episodes WHERE air_date < CURDATE() ORDER BY air_date DESC) as alias "
. "GROUP BY show_id;";



if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		$recently_aired_episodes[$row["show_name"]] = array();
		array_push($recently_aired_episodes[$row["show_name"]], 
			array(
			"season_num" => $row["season_num"],
			"episode_num" => $row["episode_num"],
			"title" => $row["title"],
			"air_date" => $row["air_date"]
			)
		);
	}
}
*/

//get all show names
$query = "SELECT * FROM shows;";
$all_show_names = array();

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		array_push($all_show_names, $row['title']);
	}
}

/*
//select next airing episodes for each show
SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date, shows.last_updated
FROM shows, episodes, 
	(SELECT show_id, MIN(air_date) AS latest_air_date FROM episodes WHERE air_date >= CURDATE() GROUP BY show_id) AS latest_episodes 
WHERE episodes.air_date = latest_episodes.latest_air_date 
AND episodes.show_id = latest_episodes.show_id 
AND shows.show_id = latest_episodes.show_id 
GROUP BY shows.title 
ORDER BY air_date 
ASC;
*/

if ($shows_to_get_string == "all") {
	//select the episode that airs next for every show
	$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title as episode_title, episodes.air_date, shows.last_updated 
	FROM shows, episodes, 
		(SELECT show_id, MIN(air_date) AS latest_air_date FROM episodes WHERE air_date >= CURDATE() GROUP BY show_id) AS latest_episodes 
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
		(SELECT show_id, MIN(air_date) AS latest_air_date FROM episodes WHERE air_date >= CURDATE() GROUP BY show_id) AS latest_episodes 
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
		$time_diff = date_diff($now_date, $episode_date);
		$days_airing_in = $time_diff->format("%a");
		if (!$next_airing_episodes["days airing in"][$days_airing_in]) {
			$next_airing_episodes["days airing in"][$days_airing_in] = array();
		}
		array_push($next_airing_episodes["days airing in"][$days_airing_in], array
			(
			"show_name" => $row["show_name"],
			"season_num" => $row["season_num"],
			"episode_num" => $row["episode_num"],
			"episode_title" => $row["episode_title"],
			"air_date" => $row["air_date"],
			"last_updated" => $row["last_updated"],
			)
		);
	}
}

echo json_encode(array("all show names" => $all_show_names, "recently aired episodes" => $recently_aired_episodes, "next airing episodes" => $next_airing_episodes));

?>