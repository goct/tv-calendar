<?php
date_default_timezone_set('America/Vancouver');

function authenticate_autologin($mysqli, $autologin_hash) {
	$query = "SELECT user_id, user_name, user_password "
	. "FROM users "
	. "WHERE user_password = '" . $autologin_hash . "';";
	
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$user_id = $row["user_id"];
		$username = $row["user_name"];
		$combined_hash = $row["user_password"];
	} else {
		echo json_encode(array("status" => "error", "errormsg" => "bad autologin hash", "official error" => $mysqli->error));
		die();	
	}
	return array($user_id, $username, $combined_hash);
}

function authenticate_manual_login($mysqli) {
	$username = $_POST["username"];
	$pw = $_POST["pw"];
	$rememberMe = (bool) $_POST["remember-me"];
	$user_id;
	
	//get users salt
	$query = "SELECT user_password, salt "
	. "FROM users "
	. "WHERE user_name = '" . $username . "';";
	if ($result = $mysqli->query($query)) {
		$row = $result->fetch_assoc();
		$salt = $row["salt"];
		$combined_hash = hash('sha256', $salt . $username . $pw);
	} else {
		echo json_encode(array("status" => "error", "errormsg" => "bad username or password", "salt" => $salt, "salted input pw hash" => $combined_hash, "official error" => $mysqli->error));
		die();
	}
	
	//check if username and password combo exists
	$query = "SELECT user_id, user_name, user_password "
	. "FROM users "
	. "WHERE user_name = '" . $username . "' "
	. "AND user_password = '" . $combined_hash . "';";

	if ($result = $mysqli->query($query)) {
		if ($result->num_rows == 1) {
			//login credentials are correct
			$row = $result->fetch_assoc();
			$user_id = $row["user_id"];
		} else if (!$result->num_rows) {
			echo json_encode(array("status" => "error", "errormsg" => "bad username or password", "salt" => $salt, "salted input pw hash" => $combined_hash, "official error" => $mysqli->error));
			die();
		} else {
			echo json_encode(array("status" => "error", "errormsg" => "duplicate users? something has gone horribly wrong"));
			die();		
		}
	} else {
		echo json_encode(array("status" => "error", "errormsg" => "error getting info from db: " . $msyqli->error));
		die();
	}

	return array($user_id, $username, $combined_hash);
}

function update_last_login($mysqli, $user_id) {
	//update last_login date
	$query = "UPDATE users "
	. "SET last_login = CURDATE() "
	. "WHERE user_id = " . $user_id . " ;";

	if (!($result = $mysqli->query($query))) {
		echo json_encode(array("status" => "error", "msg" => "couldn't update last_login"));
		die();
	}
}

function get_episode_info($mysqli, $user_id) {
	$users_tracked_show_ids = array();
	$users_tracked_show_names = array();
	$recently_aired_episodes = array();
	$next_airing_episodes = array();
	$now = new DateTime();
	$now_date_string = date_format($now, "Y-m-d");
	$now_date = new DateTime($now_date_string);
	
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

	/*
	SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title AS episode_name, MIN(episodes.air_date) AS next_air_date, episodes.info_link FROM shows, episodes WHERE shows.show_id = 22589 AND episodes.show_id = 22589 AND episodes.air_date >= CURDATE() GROUP BY show_name;
	*/
	
	//get next airing episode of each of user's tracked shows
	foreach($users_tracked_show_ids as $show_id) {
		$query = "SELECT shows.title AS show_name, episodes.season_num, episodes.episode_num, episodes.title AS episode_title, MIN(episodes.air_date) AS next_air_date, episodes.info_link "
		. "FROM shows, episodes "
		. "WHERE shows.show_id = " . $show_id . " "
		. "AND episodes.show_id = " . $show_id . " "
		. "AND episodes.air_date >= '" . $now_date_string . "' "
		. "GROUP BY show_name;";
		
		if ($result = $mysqli->query($query)) {
			if (!$result->num_rows) {
				//no future episodes available, so just grab the show name
				$query = "SELECT title AS show_name FROM shows WHERE show_id = " . $show_id . ";";
				if ($result = $mysqli->query($query)) {
					$row = $result->fetch_assoc();
					array_push($users_tracked_show_names, $row["show_name"]);
					continue;
				} else {
					echo "error";
					die();
				}
			}
			$row = $result->fetch_assoc();
			
			$show_name = $row["show_name"];
			$season_num = $row["season_num"];
			$episode_num = $row["episode_num"];
			$episode_title = $row["episode_title"];
			$air_date = $row["next_air_date"];
			$info_link = $row["info_link"];
			
			$episode_date = date_create($air_date);
			$time_diff = date_diff($now_date, $episode_date);
			$days_airing_in = $time_diff->format("%a");
			
			array_push($next_airing_episodes, array
				(
				"show_name" => $show_name,
				"season_num" => $season_num,
				"episode_num" => $episode_num,
				"episode_title" => $episode_title,
				"air_date" => $air_date,
				"info_link" => $info_link,
				"days_airing_in" => $days_airing_in
				)
			);
			array_push($users_tracked_show_names, $show_name);
		} else {
			echo json_encode(array("status" => "error", "msg" => "couldn't get next airing episodes from db" . $mysqli->error));
			die();		
		}
	}
	return array($next_airing_episodes, $users_tracked_show_names);
}

function getNzbRssInfo($mysqli, $user_id) {
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

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo json_encode(array("status" => "error", "msg" => "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error));
}

if ($autologin_hash = $_POST["autologin-hash"]) {
	//user is autologging in
	$login_type = "auto";
	$user_info_array = authenticate_autologin($mysqli, $autologin_hash);
} else {
	//user is manually logging in
	$login_type = "manual";
	$user_info_array = authenticate_manual_login($mysqli);
}

$user_id = $user_info_array[0];
$username = $user_info_array[1];
$combined_hash = $user_info_array[2];
update_last_login($mysqli, $user_id);
$episode_info = get_episode_info($mysqli, $user_id);
$next_airing_episodes = $episode_info[0];
$users_tracked_show_names = $episode_info[1];
$rss_info = getNzbRssInfo($mysqli, $user_id);
$last_nzb_id = $rss_info[0];
$rss_items = $rss_info[1];
$last_scrape_ts = $rss_info[2];

echo json_encode(array("status" => "success", "next airing episodes" => $next_airing_episodes,
 "username" => $username, "combined hash" => $combined_hash, "users tracked show names" => $users_tracked_show_names, "login type" => $login_type, "user id" => $user_id, "rss items" => $rss_items, "last nzb id" => $last_nzb_id, "last scrape ts" => $last_scrape_ts));
?>