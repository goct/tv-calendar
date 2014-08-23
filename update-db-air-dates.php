<?php
$sleep_time = 10;
$max_load_attempts = 5;
echo "starting the script</br>";

// $mysqli = new mysqli("localhost", "fraggle_db", "l337crewdb", "fraggle_tv");
// if ($mysqli->connect_errno) {
	// echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
// }
$tv_air_times = array();

//get episode lists from tvrage xml feeds
$load_attempts = 0;
while (!($xml = simplexml_load_file("http://goct.ca/tv-calendar/myrss.xml"))) {
	$load_attempts++;
	if ($load_attempts >= $max_load_attempts) {
		echo "<br/>###########Remote XML file request failed 5 times in a row. Stopping Script.################";
		die();
	}
	echo "<br/>Failed to load xml file " . $load_attempts . " times. "
	. "Trying again in 11 seconds...<br/>";
	sleep(11);
}

foreach($xml->channel->item as $item) {
	echo $item->title;
}

echo "<pre>";
print_r($xml);
echo "</pre>";

echo "<br/>script ending";
?>