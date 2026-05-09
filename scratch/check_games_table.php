<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "COLUMNS IN tbl_games:\n";
$res = mysqli_query($conn, "SHOW COLUMNS FROM tbl_games");
while($row = mysqli_fetch_array($res)) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\nTOP 50 GAMES:\n";
$res = mysqli_query($conn, "SELECT game_name, game_uid, game_category, game_provider FROM tbl_games LIMIT 50");
while($row = mysqli_fetch_assoc($res)) {
    echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . " | Cat: " . $row['game_category'] . " | Provider: " . $row['game_provider'] . "\n";
}
?>
