<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define("ACCESS_SECURITY", "true");
echo "Loading config...\n";
include '../../security/config.php';
echo "Loading access...\n";
include '../access_validate.php';

echo "Database connected? " . ($conn ? "Yes" : "No") . "\n";

$id = "08ced9dd788aed11ff3c7f387ae0f063"; // Example ID from screenshot
$sql = "SELECT * FROM tblmatchplayed WHERE tbl_period_id = '$id' LIMIT 1";

echo "Running query: $sql\n";
$start = microtime(true);
$result = mysqli_query($conn, $sql);
$end = microtime(true);

echo "Query time: " . ($end - $start) . " seconds\n";

if ($row = mysqli_fetch_assoc($result)) {
    print_r($row);
} else {
    echo "No record found.\n";
}
?>
