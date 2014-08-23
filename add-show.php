<?php
$mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
$dupe_entry_code = 1062;
$title = $_GET['title'];
$tvrage_show_id;
$season_num;
$official_title;
$episode_num;
$episode_title;
$air_date;
$episode_info_link;
$show_info;


//get tvrage show id from xml
$xml = simplexml_load_file("http://services.tvrage.com/feeds/search.php?show=" . $title);
if (!$xml) {
	echo "error: remote xml file request timed out";
	die();
}
$tvrage_show_id = $xml->show[0]->showid;
$title = mysqli_real_escape_string($mysqli, (string) $xml->show[0]->name);
//$title = preg_replace("/\\'/", "", $title);
$show_info = array($title => array());


$query = "INSERT INTO shows (show_id, title, last_updated) VALUES ("
. "'" . $tvrage_show_id . "', "
. "'" . $title . "', "
. "'never'"
. ");";


//get episode list from tvrage xml
$xml2 = simplexml_load_file("http://services.tvrage.com/feeds/episode_list.php?sid=" . $tvrage_show_id);
if (!$xml2) {
	echo "error: remote xml file request timed out";
	die();	
}
foreach($xml2->Episodelist->Season as $season) {
	$season_num = $season->attributes()->no;
	foreach($season->episode as $e) {
		$episode_num = mysqli_real_escape_string($mysqli, $e->seasonnum);
		$air_date = mysqli_real_escape_string($mysqli, $e->airdate);
		$episode_info_link = mysqli_real_escape_string($mysqli, $e->link);
		$episode_title = mysqli_real_escape_string($mysqli, $e->title);
		array_push($show_info[$title], array(
			"date" => $air_date,
			"season-num" => $season_num,
			"episode-num" => $episode_num,
			"title" => $episode_title,
			"episode-info-link" => $episode_info_link
			)
		);
	}
}

$result = $mysqli->query($query);

if ($mysqli->errno == $dupe_entry_code) {
	//show is already in the database
	echo "duplicate";
	die();
} else if (!$result) {
	echo "error: couldn't insert into shows for some reason";
}

foreach($show_info[$title] as $ep) {
	$query = "INSERT INTO episodes (show_id, title, air_date, season_num, episode_num, info_link) "
	. "VALUES ( "
	. "(SELECT show_id FROM shows WHERE title = '" . $title . "'), "
	. "'" . $ep['title'] . "', "
	. "'" . $ep['date'] . "', "
	. $ep['season-num'] . ","
	. $ep['episode-num'] . ", "
	. "'" . $ep['episode-info-link'] . "'"
	. ")";
	$mysqli->query($query);
}

if (!$mysqli->error) {
	echo $title;
} else {
	echo "an error occured while trying to put the data in the database: error is: <br/>" . $mysqli->error . "<br/>###########query was " . $query;
}



?>