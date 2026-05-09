<?php
define("ACCESS_SECURITY", "true");
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../security/config.php';
include '../security/constants.php';

$output = "DATABASE CONNECTED: " . (isset($conn) ? "YES" : "NO") . "\n";
if(isset($conn)) {
    $res = mysqli_query($conn, "SELECT * FROM tblservices");
    if($res) {
        $output .= "--- SERVICES TABLE ---\n";
        while($row = mysqli_fetch_assoc($res)) {
            $output .= $row['tbl_service_name'] . " => " . $row['tbl_service_value'] . "\n";
        }
    } else {
        $output .= "QUERY FAILED: " . mysqli_error($conn) . "\n";
    }
}

$output .= "\n--- CONSTANTS ---\n";
$output .= "APP_NAME: " . $APP_NAME . "\n";
$output .= "APP_LOGO: " . $APP_LOGO . "\n";
$output .= "APP_FAVICON: " . $APP_FAVICON . "\n";

file_put_contents('../scratch/debug_branding.txt', $output);
echo "Debug finished. Check scratch/debug_branding.txt";
?>
