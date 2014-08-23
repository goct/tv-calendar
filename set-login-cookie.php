<?php
$combined_hash = $_POST["combined_hash"];

setcookie("hash", $combined_hash, time()+60*60*24*30);
?>