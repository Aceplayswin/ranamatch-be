<?php
define("ACCESS_SECURITY", "true");
include 'security/config.php';
include 'security/constants.php';
$output = "NAME: " . $APP_NAME . "\n";
$output .= "LOGO: " . $APP_LOGO . "\n";
$output .= "FAVICON: " . $APP_FAVICON . "\n";
file_put_contents('scratch/branding_results.txt', $output);
?>
