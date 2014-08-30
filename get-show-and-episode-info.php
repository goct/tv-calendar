<?php
date_default_timezone_set('America/Vancouver');

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}

//$shows_to_get_string = mysqli_real_escape_string($mysqli, $_POST["shows-to-get"]);
$user_id = $mysqli->real_escape_string($_POST["user-id"]);
$shows_to_get_string = $mysqli->real_escape_string($_POST["shows-to-get"]);
$users_tracked_show_ids = array();
$users_tracked_show_names = array();
$recently_aired_episodes = array();
$next_airing_episodes = array();
$now = new DateTime();
$now_date_string = date_format($now, "Y-m-d");
$now_date = new DateTime($now_date_string);

if ($user_id) {
	//find show ids tracked by user
	$query = "SELECT show_id "
	. "FROM users_tracked_shows "
	. "WHERE user_id = '" . $user_id . "';";

	if ($result = $mysqli->query($query)) {
		while ($row = $result->fetch_assoc()) {
			array_push($users_tracked_show_ids, $row["show_id"]);
		}
	} else {
		echo json_encode(array("status" => "error", "msg" => "couldn't get tracked shows from db"));
		die();
	}
}

foreach(array("future", "past") as $relative_time) {
	switch($relative_time) {
		case "future":
			$operator = ">=";
			$min_or_max = "MIN";
			$multiplier = 1;
			break;
		case "past":
			$operator = "<";
			$min_or_max = "MAX";
			$multiplier = -1;
			break;
	}
	if ($shows_to_get_string == "all") {
		//select the most recently aired OR the next airing episode for every show
		$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num,
		 episodes.title as episode_title, episodes.air_date AS air_date, episodes.airtime "
		. "FROM episodes, shows, "
		. "(SELECT show_id, " . $min_or_max . "(air_date) AS air_date FROM episodes WHERE air_date " . $operator . " '" . $now_date_string . "' GROUP BY show_id) AS sub_episodes "
		. "WHERE episodes.air_date = sub_episodes.air_date "
		. "AND episodes.show_id = sub_episodes.show_id "
		. "AND shows.show_id = sub_episodes.show_id "
		. "GROUP BY show_name "
		. "ORDER BY air_date DESC;";
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_assoc()) {
				/*if ($row["show_name"] === null) {// || !$result->num_rows) {
					//no episodes available
					continue;
				}*/

				$show_name = $row["show_name"];
				$season_num = $row["season_num"];
				$episode_num = $row["episode_num"];
				$episode_title = $row["episode_title"];
				$air_date = $row["air_date"];
				$airtime = $row["airtime"];
				$info_link = $row["info_link"];
				
				$episode_date = date_create($air_date);
				$time_diff = date_diff($now_date, $episode_date);
				$days_airing_in = intval($time_diff->format("%a")) * $multiplier;

				if ($relative_time == "future") {
					$x = false;
					array_push($next_airing_episodes, array
						(
						"show_name" => $show_name,
						"season_num" => $season_num,
						"episode_num" => $episode_num,
						"episode_title" => $episode_title,
						"air_date" => $air_date,
						"airtime" => $airtime,
						"info_link" => $info_link,
						"days_airing_in" => $days_airing_in
						)
					);
				} else if ($relative_time == "past") {
					$x = true;
					array_push($recently_aired_episodes, array
						(
						"show_name" => $show_name,
						"season_num" => $season_num,
						"episode_num" => $episode_num,
						"episode_title" => $episode_title,
						"air_date" => $air_date,
						"airtime" => $airtime,
						"info_link" => $info_link,
						"days_airing_in" => $days_airing_in
						)
					);
				}
			}
		} else {
			echo json_encode(array("status" => "error", "msg" => "couldn't get episodes from db" . $mysqli->error));
			die();		
		}
	} else {
		//select the most recently aired OR the next airing episode for every show the user is tracking
		foreach($users_tracked_show_ids as $show_id) {
			$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num,
			 episodes.title AS episode_title, " . $min_or_max . "(episodes.air_date) AS air_date,
			  episodes.info_link, episodes.airtime "
			. "FROM shows, episodes, "
			. "(SELECT " . $min_or_max . "(episodes.air_date) AS air_date FROM episodes WHERE episodes.air_date " . $operator . " '" . $now_date_string . "' AND episodes.show_id = " . $show_id . ") AS sub_air_date "
			. "WHERE shows.show_id = " . $show_id . " "
			. "AND episodes.show_id = " . $show_id . " "
			. "AND episodes.air_date " . $operator . " '" . $now_date_string . "' "
			. "AND episodes.air_date = sub_air_date.air_date;";


			/*
			$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title AS episode_title, " . $mix_or_max . "(episodes.air_date) AS air_date, episodes.info_link "
			. "FROM shows, episodes "
			. "WHERE shows.show_id = " . $show_id . " "
			. "AND episodes.show_id = " . $show_id . " "
			. "AND episodes.air_date " . $operator . " '" . $now_date_string . "' "
			. "GROUP BY show_name;";
			*/
			if ($result = $mysqli->query($query)) {
				$row = $result->fetch_assoc();
				if ($row["show_name"] === null) {// || !$result->num_rows) {
					//no episodes available, so just grab the show name
					$query = "SELECT title AS show_name FROM shows WHERE show_id = " . $show_id . ";";
					if ($result = $mysqli->query($query)) {
						$row = $result->fetch_assoc();
						if (!array_search($row["show_name"], $users_tracked_show_names)) {
							array_push($users_tracked_show_names, $row["show_name"]);
						}
						continue;
					} else {
						echo "error";
						die();
					}
				}

				
				$show_name = $row["show_name"];
				$season_num = $row["season_num"];
				$episode_num = $row["episode_num"];
				$episode_title = $row["episode_title"];
				$air_date = $row["air_date"];
				$airtime = $row["airtime"];
				$info_link = $row["info_link"];
				
				$episode_date = date_create($air_date);
				$time_diff = date_diff($now_date, $episode_date);
				$days_airing_in = intval($time_diff->format("%a")) * $multiplier;

				if ($relative_time == "future") {
					array_push($next_airing_episodes, array
						(
						"show_name" => $show_name,
						"season_num" => $season_num,
						"episode_num" => $episode_num,
						"episode_title" => $episode_title,
						"air_date" => $air_date,
						"airtime" => $airtime,
						"info_link" => $info_link,
						"days_airing_in" => $days_airing_in
						)
					);
				} else if ($relative_time == "past") {
					array_push($recently_aired_episodes, array
						(
						"show_name" => $show_name,
						"season_num" => $season_num,
						"episode_num" => $episode_num,
						"episode_title" => $episode_title,
						"air_date" => $air_date,
						"airtime" => $airtime,
						"info_link" => $info_link,
						"days_airing_in" => $days_airing_in
						)
					);
				}
				if (!array_search($row["show_name"], $users_tracked_show_names)) {
					array_push($users_tracked_show_names, $show_name);
				}
			} else {
				echo json_encode(array("status" => "error", "msg" => "couldn't get episodes from db" . $mysqli->error));
				die();		
			}


			/*
			$query = "SELECT shows.title AS show_name, episodes.episode_num, episodes.title as episode_title, episodes.air_date AS recent_air_date
			FROM episodes, shows,
			(SELECT show_id, MAX(air_date) AS recent_air_date FROM episodes WHERE air_date < CURDATE() GROUP BY show_id) AS recent_episodes
			WHERE episodes.air_date = recent_episodes.recent_air_date 
			AND episodes.show_id = recent_episodes.show_id 
			AND shows.show_id = recent_episodes.show_id 
			AND shows.title IN ('" . $shows_to_get_string . "') 
			GROUP BY show_name
			ORDER BY air_date DESC;";
			*/
			//return;
		}
	}
}
/*echo json_encode(array("status" => "testerror", 
						"msg" => "after " . $relative_time . " nextairingepisodes is " . $next_airing_episodes,
						"array" => $next_airing_episodes));
die();*/
/*
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
	echo "failed mysql thing";
	die();
}
*/
/*
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

//package next airing episodes into an array
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
	echo "failed mysql thing3";
	die();
}
*/
//get all show names
$query = "SELECT * FROM shows;";
$all_show_names = array();

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		array_push($all_show_names, $row['title']);
	}
} else {
	echo "failed mysql thing2";
	die();
}

echo json_encode(array(
	"status" => "success",
	"all-show-names" => $all_show_names, 
	"recently-aired-episodes" => $recently_aired_episodes, 
	"next-airing-episodes" => $next_airing_episodes,
	"users-tracked-show-names" => $users_tracked_show_names
	)
);
?>