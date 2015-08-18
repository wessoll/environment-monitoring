<?php

define("DATABASE_PATH", "/var/www/phpliteadmin/public/environment-monitoring");
$allowedSensorTypes = array("temperature", "humidity", "co", "light");

// Validation
if (!isset($_GET["interval"]) || !isset($_GET["limit"]) || !isset($_GET["type"])) {
	$output = array("result" => "", "error" => "interval, limit, and type needs to be specified.");

	echo json_encode($output);
    exit; // Stop the script
}

if (!in_array($_GET["type"], $allowedSensorTypes)) {
	$output = array("result" => "", "error" => "type is not allowed");

	echo json_encode($output);
    exit; // Stop the script
}

$type = $_GET["type"];
$interval = $_GET["interval"];
$limit = $_GET["limit"];

$startTime = get_latest_timestamp_from_db($type); // Get the latest value to start the measurements from

$results = retrieve_samples_recursively($type, $startTime, $interval, $limit, 0);

// Output results
$output = array("result" => $results, "error" => "");

echo json_encode($output);


// CRUD

// Retrieves the samples from the database recursively.
function retrieve_samples_recursively($table, $startTime, $interval, $limit, $count)
{
	$results = array();

	if ($count == $limit) {
		return $results;
	}

    // We need to subtract the interval from the starting time
	$date = new DateTime($startTime);
	$date->sub(new DateInterval("PT" . $interval . "S"));

	$timeMinusInterval = $date->format('Y-m-d H:i:s');

    // Retrieve the first row that matches our time criteria.
	$results = get_samples_from_db("SELECT * FROM $table WHERE timestamp BETWEEN '$timeMinusInterval' AND '$startTime' ORDER BY timestamp DESC LIMIT 1");

	$count++;

    if (($count !=  $limit) && count($results) > 0) { // We can fetch more if there are results and we haven't reached the limit.

    // Call this method again and merge the results into our current results.
    $methodResult = retrieve_samples_recursively($table, $timeMinusInterval, $interval, $limit, $count);

    $results = array_merge($results, $methodResult);

} else { // No more results, quit.
	return $results;
}

return $results;
}
// Returns the timestamp of the latest entry for the table parameter
function get_latest_timestamp_from_db($table)
{
	$query = "SELECT timestamp FROM $table ORDER BY timestamp DESC LIMIT 1";

	$timestamp = NULL;

	$db = new SQLite3(DATABASE_PATH);
	$result = $db->query($query);

	if ($row = $result->fetchArray()) {
		$timestamp = $row["timestamp"];
	}

	return $timestamp;
}

// Returns the sample data from the database for the specified query
function get_samples_from_db($query)
{
	$results = array();

    // Fetch results.
	$db = new SQLite3(DATABASE_PATH);
	$result = $db->query($query);

	while ($row = $result->fetchArray()) {
		$newRow = array("id" => $row["id"],
			"value" => $row["value"],
			"source" => $row["source"],
			"timestamp" => $row["timestamp"]);

		array_push($results, $newRow);
	}
	return $results;
}
?>

