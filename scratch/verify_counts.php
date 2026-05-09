<?php
define("ACCESS_SECURITY", true);
include 'security/config.php';
$provider = 'MAC88';
$sql = "SELECT COUNT(*) as count FROM tbl_games WHERE game_provider = '$provider' AND game_status = 1";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
echo "Provider: $provider, Count: " . $row['count'] . "\n";

$provider = 'WS168';
$sql = "SELECT COUNT(*) as count FROM tbl_games WHERE game_provider = '$provider' AND game_status = 1";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
echo "Provider: $provider, Count: " . $row['count'] . "\n";
?>
