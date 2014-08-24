<?php
//
//WARNING! THIS IS AT LEAST LARGELY A DUPLICATE OF CODE WITHIN LOGIN.PHP
//
date_default_timezone_set('America/Vancouver');
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo json_encode(array("status" => "error", "msg" => "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error));
}

$query = "SELECT time FROM tv_nzb_update_times ORDER BY id DESC LIMIT 1";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$db_last_scrape_ts = $row["time"];
		if ($db_last_scrape_ts > $_POST["last-scrape-ts"]) {
			//there is a new update
			$info = getNzbRssInfoPeriodic($mysqli, $_POST["user-id"], $_POST["last-scrape-ts"]);

			echo json_encode(array(
									"last-nzb-id" => $info[0],
									"rss-items" => $info[1],
									"last-scrape-ts" => $info[2],
									"msg" => "success"
									)
							);
		} else {
			echo json_encode(array("msg" => "no update yet"));
			die();
		}
	} else {
		echo json_encode(array("status" => "error", "msg" => "failed getting a timestamp" . $mysqli->error));
		die();	
	}
/*
$info = getNzbRssInfoPeriodic($mysqli, $_POST["user-id"], $_POST["last-scrape-ts"]);

echo json_encode(array(
						"last-nzb-id" => $info[0],
						"rss-items" => $info[1],
						"last-scrape-ts" => $info[2],
						"msg" => "success"
						)
				);
die();*/

function getNzbRssInfoPeriodic($mysqli, $user_id) {
	$rss_items = array();
	//find last xml scrape timestamp
	$query = "SELECT time FROM tv_nzb_update_times ORDER BY id DESC LIMIT 1";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$last_scrape_ts = $row["time"];
	} else {
		echo json_encode(array("status" => "error", "msg" => "failed to get last xml scrape timestamp " . $mysqli->error));
		die();	
	}
	//find last viewed nzb id for this user_id
	$query = "SELECT last_nzb_id_viewed AS last_id FROM users WHERE user_id = " . $user_id . ";";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		if (!($last_viewed_nzb_id = $row["last_id"])) {
			//last viewed id is null
			$query = "SELECT item_id FROM tv_nzbs ORDER BY item_id DESC LIMIT 1;";
			$result = $mysqli->query($query);
			$row = $result->fetch_assoc();
			$last_viewed_nzb_id = ((int) $row["item_id"]) - 3;
		}
	} else {
		echo json_encode(array("status" => "error", "msg" => "last viewed nzb id for user from db" . $mysqli->error));
		die();	
	}
	
	//get unviewed nzb rows
	$query = "SELECT * FROM tv_nzbs WHERE item_id > " . $last_viewed_nzb_id . " ORDER BY show_name ASC;";
	if ($result = $mysqli->query($query)) {
		$last_nzb_id = $result->num_rows + $last_viewed_nzb_id;
		while ($row = $result->fetch_assoc()) {
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
		return array($last_nzb_id, $rss_items, $last_scrape_ts);
	} else {
		echo json_encode(array("status" => "error", "msg" => "last viewed nzb id for user from db" . $mysqli->error));
		die();	
	}
}
?>