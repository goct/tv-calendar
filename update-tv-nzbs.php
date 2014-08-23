<?php
date_default_timezone_set("America/Vancouver");
$sleep_time = 10;
$max_load_attempts = 5;
$xml_items = 100;
$time_stamp = time();
//$now_date_time = new DateTime(null, new DateTimeZone('America/Vancouver'));
//nzbs.org tv feed
$rss_url = "http://nzbs.org/rss?t=5000&dl=1&i=11321&r=fe4d5c568e0596e5010a8c5d00797fa2&lang=EN&num=" . $xml_items;
$items_array = array();
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die();
}




while (!($xml = simplexml_load_file($rss_url))) {
	$load_attempts++;
	if ($load_attempts >= $max_load_attempts) {
		echo "<br/>Remote XML file request failed 5 times in a row. Stopping Script.";
		die();
	}
	echo "<br/>Failed to load xml file. Trying again in " . $sleep_time . " seconds...<br/>";
	sleep($sleep_time);
}

//we now have the xml data
$now_date_time = new DateTime("now");

$query = "INSERT INTO tv_nzb_update_times VALUES (null, " . $time_stamp . ");";

if ($mysqli->query($query)) {
	echo "successfully put data in the times db<br/>";
} else {
	echo "failed to input data scrape time: (" . $mysqli->errno . ") " . $mysqli->error;
}


foreach($xml->channel->item as $item) {
	$item_info = getItemInfo($item, $mysqli);
	
	array_push($items_array, $item_info);
}

foreach($items_array as $item) {
	$query = "INSERT INTO tv_nzbs VALUES (" .
	"null, " .
	"'" . $item["raw title"] . "', " .
	"'" . $item["download link"] . "', " .
	"'" . $item["guid"] . "', " .
	"'" . $item["episode name"] . "', " .
	"'" . $item["show name"] . "', " .
	"'" . $item["bytes"] . "', " .
	"'" . $item["season"] . "', " .
	"'" . $item["episode"] . "', " .
	"'" . $item["series ID"] . "', " .
	"'" . $item["tv air date"] . "', " .
	"'" . $item["passworded"] . "', " .
	"'" . $item["completion"] . "', " .
	"'" . $item["usenet date"] . "', " .
	"'" . $item["group"] . "', " .
	"0" .
	") ON DUPLICATE KEY UPDATE times_updated = times_updated + 1;";
	
	if ($mysqli->query($query)) {
		echo "successfully put data in the db<br/>";
	} else {
		echo "failed to input data: (" . $mysqli->errno . ") " . $mysqli->error;
	}
}




function getItemInfo($item, $mysqli) {
	$newznab_ns_register_url = "http://www.newznab.com/DTD/2010/feeds/attributes/";
	$attr_element = $item->children($newznab_ns_register_url)->attr;
	$raw_title = (string) $item->title;
	$download_link = (string) $item->link;
	$guid = (string) $item->guid;
	
	foreach($attr_element as $element) {
		$attributes = $element->attributes();
		$name = (string) $attributes->name;
		$value = $mysqli->real_escape_string((string) $attributes->value);
		switch($name) {
			case "tvtitle":
				$episode_name = $value;
				break;
			case "showtitle":
				$show_name = $value;
				break;
			case "size":
				$bytes = $value;
				break;
			case "season":
				$season = substr($value, 1);
				break;
			case "episode":
				$episode = substr($value, 1);
				break;
			case "seriesid":
				$series_ID = $value;
				break;
			case "tvairdate":
				// $month = substr($value, 8, 3);
				// $day = substr($value, 5, 2);
				// $year = substr($value, 12, 4);
				// $tv_air_date_string = $month . "-" . $day . "-" . $year;
				// $date = new DateTime($tv_air_date_string);
				// $tv_air_date = date_format($date, "Y-m-d");
				$tv_air_date = new DateTime($value);
				$tv_air_date_time_string = date_format($tv_air_date, "Y-m-d H:i:s");
				//echo "<br/>inputting " . $tv_air_date_time_string;
				/*
				$tv_air_date_string = substr($value, 5, 11);
				$date = date_create_from_format('j M Y', $tv_air_date_string);
				$tv_air_date = date_format($date, 'Y-m-d');
				"Sun, 02 Mar 2014 09:07:34 +0000"
				*/
				break;
			case "password":
				$passworded = $value;
				break;
			case "completion":
				$completion = $value;
				break;
			case "usenetdate":
				$month = substr($value, 8, 3);
				$day = substr($value, 5, 2);
				$year = substr($value, 12, 4);
				$usenet_date_string = $month . "-" . $day . "-" . $year;
				$date = new DateTime($usenet_date_string);
				$usenet_date = date_format($date, "Y-m-d");
				/*
				$usenet_date_string = substr($value, 5, 11);
				$date = date_create_from_format('j M Y', $usenet_date_string);
				$usenet_date = date_format($date, 'Y-m-d');*/
				break;
			case "group":
				$group = $value;
				break;
		}
	}
	
	return array(
		"raw title" => $raw_title,
		"download link" => $download_link,
		"guid" => $guid,
		"episode name" => $episode_name,
		"show name" => $show_name,
		"bytes" => $bytes,
		"season" => $season,
		"episode" => $episode,
		"series ID" => $series_ID,
		"tv air date" => $tv_air_date_time_string,
		"passworded" => $passworded,
		"completion" => $completion,
		"usenet date" => $usenet_date,
		"group" => $group
	);
}
?>