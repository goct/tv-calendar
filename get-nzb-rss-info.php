<?php
date_default_timezone_set('America/Vancouver');
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo json_encode(array("status" => "error", "msg" => "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error));
}

$user_id = $mysqli->real_escape_string($_POST["user-id"]);
$last_scrape_ts = ($_POST["last-scrape-ts"] === null) ? null : $mysqli->real_escape_string($_POST["last-scrape-ts"]);
//$last_viewed_nzb_id = ($_POST["last-viewed-nzb-id"] === null) ? null: $mysqli->real_escape_string($_POST["last-viewed-nzb-id"]);

if ($last_scrape_ts === null) {
	//get last scrape timestamp
	$query = "SELECT time FROM tv_nzb_update_times ORDER BY id DESC LIMIT 1";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$last_scrape_ts = $row["time"];
	} else {
		//database query error
		echo json_encode(array("status" => "error", "msg" => "failed getting a timestamp" . $mysqli->error));
		die();	
	}
} else {
	//already have last scrape timestamp
	//user is looking for an update
	$query = "SELECT time FROM tv_nzb_update_times ORDER BY id DESC LIMIT 1";
		if ($result = $mysqli->query($query)) {
			$row = $result->fetch_assoc();
			$db_last_scrape_ts = $row["time"];
			if ($db_last_scrape_ts > $last_scrape_ts) {
				$last_scrape_ts = $db_last_scrape_ts;
				//there is a new update
			} else {
				//update doesn't exist yet
				echo json_encode(array("msg" => "no update yet"));
				die();
			}
		} else {
			//database query error
			echo json_encode(array("status" => "error", "msg" => "failed getting a timestamp" . $mysqli->error));
			die();	
		}	
}

getNzbRssInfo($mysqli, $user_id, $last_scrape_ts);



function getNzbRssInfo($mysqli, $user_id, $last_scrape_ts) {
	$rss_items = array();

	//find last viewed nzb id for this user_id
	$query = "SELECT last_nzb_id_viewed AS last_id FROM users WHERE user_id = " . $user_id . ";";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$last_viewed_nzb_id = $row["last_id"];
		if ($last_viewed_nzb_id === null) {
			//set users last viewed nzb id
			$query = "SELECT item_id FROM tv_nzbs ORDER BY item_id DESC LIMIT 1;";
			$result = $mysqli->query($query);
			$row = $result->fetch_assoc();
			$last_viewed_nzb_id = ($row["item_id"]) - 3; //user should see 3 items on first viewing
		}
	} else {
		echo json_encode(array("status" => "error", "msg" => "last viewed nzb id for user from db" . $mysqli->error));
		die();	
	}

	//get unviewed nzb rows
	$query = "SELECT * FROM tv_nzbs WHERE item_id > " . $last_viewed_nzb_id . " ORDER BY item_id ASC;";
	if ($result = $mysqli->query($query)) {
		$potential_last_viewed_nzb_id = $result->num_rows + $last_viewed_nzb_id;
		while ($row = $result->fetch_assoc()) {
			$row = assignCustomShowNames($row);
			array_push($rss_items, array(
				"item id" => $row["item_id"],
				"raw title" => $row["raw_title"],
				"download_link" => $row["download_link"],
				"episode name" => $row["episode_name"],
				"show name" => $row["show_name"],
				"bytes" => (int) $row["bytes"],
				"season" => (int) $row["season"],
				"episode" => (int) $row["episode"],
				"series id" => (int) $row["series_id"],
				"tv air date" => $row["tv_air_date"],
				"passworded" => $row["passworded"],
				"completion" => $row["completion"]
				)
			);
		}
		echo json_encode(array("potential-last-viewed-nzb-id" => $potential_last_viewed_nzb_id, 
					"rss-items" => $rss_items, 
					"last-scrape-ts" => $last_scrape_ts
					)
		);
	} else {
		//database query error
		echo json_encode(array("status" => "error", "msg" => "last viewed nzb id for user from db" . $mysqli->error));
		die();	
	}
}

function assignCustomShowNames($episode) {
	$custom_patterns = array(
		"british football" => "/uefa|epl/i"
	);
	foreach($custom_patterns as $pattern_name => $pattern) {
		if (preg_match($pattern, $episode["raw_title"])) {
			switch($pattern_name) {
				case "british football":
					$episode["show_name"] = "British Football";
					$episode["series_id"] = 2147483647;
					break;
			}
			return $episode;
		}
	}
	return $episode;
}
?>