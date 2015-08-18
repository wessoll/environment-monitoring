<?php

define("DATABASE_PATH", "/var/www/phpliteadmin/public/environment-monitoring");
$allowedSensorTypes = array("temperature", "humidity", "co", "light");

// Validation
if (empty($_FILES) || empty($_GET["type"]) || empty($_GET["millisecond"])) {
	error_log("file, type, or millisecond is empty");

	$output = array("result" => "", "error" => "file, type, and millisecond needs to be specified.");

	echo json_encode($output);
    exit; // Stop the script
}

if (!in_array($_GET["type"], $allowedSensorTypes)) {
	error_log("type is not allowed");

	$output = array("result" => "", "error" => "type is not allowed");

	echo json_encode($output);
    exit; // Stop the script
}

// Begin the actual process
$type = $_GET["type"];

// If we set millisecond (the time the request was made) to the current time, we can infer the time from the other milliseconds in the .csv file more accurately.
$requestMilliseconds = $_GET["millisecond"];
$startTime = new DateTime("NOW");
$filePath = "";

foreach ($_FILES as $file) {
	$filePath = $file["tmp_name"];
	if (!file_exists($filePath)) {
		error_log("error saving file");

		$output = array("result" => "", "error" => "Error saving file.");

		echo json_encode($output);
        exit; // Stop the script
    }
    break; // We are only using one file at once.
}

// Parse the entire .csv file into an array
$csv = array_map("str_getcsv", file($filePath));

foreach ($csv as $entry) {
	if ($entry[0] == "value") continue; // Continue if first row contains titles

	if (count($entry) < 3) { // Ignore invalid rows
		error_log("invalid row");
		continue;
	}

	// Calculate the timestamp
	$rowMilliseconds = $entry[2];

	// By subtracting the rowMilliseconds from the requestMilliseconds we get the amount of milliseconds from which we can infer a datetime, if we subtract that result in turn from the startTime
	$millisecondsDifference = $requestMilliseconds - $rowMilliseconds;
	$seconds = round($millisecondsDifference / 1000);

	$sampleTime = clone $startTime;
	$sampleTime->modify("-$seconds second");

	// We use the the time right now as the timestamp, because the Arduino can't send the proper timestamps along
	insert_into_db($type, $entry[0], $entry[1], $sampleTime->format("Y-m-d H:i:s"));

	error_log("insert into db");
}

// Inserts the actual values into the db
function insert_into_db($table, $value, $source, $timestamp)
{
	$db = new SQLite3(DATABASE_PATH);

	$query = $db->prepare("INSERT INTO $table (value, source, timestamp) VALUES (:value, :source, :timestamp)");
	$query->bindValue(":value", $value);
	$query->bindValue(":source", $source);
	$query->bindValue(":timestamp", $timestamp);

	$query->execute();
}

?>