<?php
$db_shows = array();
$load_attempts = 0;
$reload_frequency = 3;

$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

//get episode air times from tvrage xml feeds

//http://www.goct.ca/tv-calendar/tvtime.xml local copy
while (!($xml = simplexml_load_file("http://www.tvrage.com/myrss.php?date=tomorrow"))) {
	$load_attempts++;
	if ($load_attempts >= $max_load_attempts) {
		echo "<br/>###########Remote XML file request failed " . $load_attempts . " times in a row. Stopping Script.################";
		die();
		
	}
	echo "<br/>Failed to load xml file " . $load_attempts . " times. "
	. "Trying again in " . $reload_frequency . " seconds...<br/>";
	for ($i = $reload_frequency; $i >= 0; $i--) {
		echo "<br/>" . $i;
		sleep(1);
	}
}

$query = "SELECT show_id, title FROM shows";

if ($result = $mysqli->query($query)) {
	while ($row = $result->fetch_assoc()) {
		/*array_push($db_shows, 
			array($row["title"] => array())
		);*/
		$db_shows[$row["title"]] = array("show id" => $row["show_id"], "dates and times" => array());
	}
} else {
	echo "db query error";
	die();
}

$date = $xml->channel->pubDate;
$date_matches = array();
preg_match("/.*,\s(\w+)\s(\d+)\s(\d+)\s.*/", $date, $date_matches);

//echo "<br/>";
$date_obj = new DateTime($date_matches[1] . "-" . $date_matches[2] . "-" . $date_matches[3]);
date_add($date_obj, date_interval_create_from_date_string('1 days'));
//echo "date is " . $date_obj->format("Y-m-d");
//echo "<br/>";
 //"Apr-17-1790"

$time = "00:00:00";

foreach($xml->channel->item as $object) {
	$time_matches = array();
	if (preg_match("/(\d\d):(\d\d)\s(pm|am)/", $object->title, $time_matches)) {
		//it's a time
		$hours = $time_matches[1];
		$min = $time_matches[2];
		$am_pm = $time_matches[3];
		if ($am_pm == "pm") {
			//set it to 24 hour time
			$hours = intval($hours) + 12;
			//set it to my time zone
			$hours -= 2;
		}
		$prev_hours = intval(substr($time, 0, 2));
		if ($hours < $prev_hours && $prev_hours != 12) {
			//we've gone into the next day
			date_add($date_obj, date_interval_create_from_date_string('1 days'));
			//echo "<br/>###Increasing the day by 1###<br/>";
		}
		$time = $hours . ":" . $min . ":00";
		continue;
	} else {
		//it's an episode
		$matches = array();
		$other_matches = array();
		preg_match("/^-\s(.*)\s\(((\d+)x(\d+)|.*Special)\)$/", $object->title, $matches);
		if (strpos($matches[2], "Special" !== false)) {
			//it's a special, with no episode number
			$show_name = $matches[1];
			$season_num = null;
			$episode_num = null;
		} else if (preg_match("/^-\s(.*)\s\(\)$/", $object->title, $other_matches)) {
			//missing season and episode numbers
			$show_name = $other_matches[1];
			$season_num = null;
			$episode_num = null;
		} else {
			//it's a regular episode
			$show_name = $matches[1];
			$season_num = $matches[3];
			$episode_num = $matches[4];
		}
		/*echo $show_name
			 . " S" . str_pad($season_num, 2, "0", STR_PAD_LEFT)
			 . "E" . str_pad($episode_num, 2, "0", STR_PAD_LEFT)
			 . "<br/>";	*/
		if (array_key_exists($show_name, $db_shows)) {
			//this show is in the db, so we'll update the air time
			if (array_key_exists("date", $db_shows[$show_name]["dates and times"]) &&
				$db_shows[$show_name]["dates and times"]["date"] == $date_obj->format("Y-m-d")) {
				//this episode is not the first one for today, so we'll ignore it
				//echo "not the first one, so ignoring it";
				continue;
			} else {
				array_push($db_shows[$show_name]["dates and times"], array("date" => $date_obj->format("Y-m-d"),
													"time" => $time,
													)
						);
			}
		}

	}
}
/*echo "<pre>";
print_r($db_shows);
echo "</pre>";
*/
foreach($db_shows as $show => $meta_array) {
	foreach($meta_array["dates and times"] as $show_time_array) {
		$date = $show_time_array["date"];
		$time = $show_time_array["time"];
		//$query = "SELECT show_id FROM shows WHERE title = " . $show;

		$query = "UPDATE episodes SET airtime = '" . $mysqli->real_escape_string($time) . "' "
				. "WHERE air_date = '" . $date . "' "
				. "AND show_id = " . $mysqli->real_escape_string($meta_array["show id"]);

		if (!$result = $mysqli->query($query)) {
			echo "db query error " . $mysqli->error;
			die();
		} else {
			echo "updated " . $show . "<br/>";// . " S" . $season_num . "E" . $episode_num . "<br/>";
			echo "query was " . $query . "<br/>";
			echo "mysqli info was " . $mysqli->info . "<br/>";
		}
	}

}
?>