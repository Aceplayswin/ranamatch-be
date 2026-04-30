<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "UNIQUE PROVIDERS:\n";
$res = mysqli_query($conn, "SELECT DISTINCT game_provider FROM tbl_games");
while($row = mysqli_fetch_array($res)) {
    echo "- " . $row[0] . "\n";
}

echo "\nUNIQUE CATEGORIES:\n";
$res = mysqli_query($conn, "SELECT DISTINCT game_category FROM tbl_games");
while($row = mysqli_fetch_array($res)) {
    echo "- " . $row[0] . "\n";
}

echo "\nLOBBY GAMES (Search for 'Lobby'):\n";
$res = mysqli_query($conn, "SELECT game_name, game_uid, game_provider FROM tbl_games WHERE game_name LIKE '%Lobby%'");
while($row = mysqli_fetch_assoc($res)) {
    echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . " | Provider: " . $row['game_provider'] . "\n";
}
?>
