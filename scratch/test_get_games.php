<?php
define("ACCESS_SECURITY", "true");
include 'security/config.php';

// Simulate RequestHeaders object if needed, but request-get-games.php doesn't use it except for defining ACCESS_SECURITY
// and the router handles the rest.

$_GET['category'] = ''; // Get all
include 'route-paths/request-get-games.php';
?>
