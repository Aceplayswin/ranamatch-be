<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "GAMES RELATED TO EZUGI:\n";
$res = mysqli_query($conn, "SELECT game_name, game_uid, game_category, game_provider FROM tbl_games WHERE game_name LIKE '%Ezugi%' OR game_provider LIKE '%Ezugi%'");
while($row = mysqli_fetch_assoc($res)) {
    echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . " | Provider: " . $row['game_provider'] . "\n";
}

echo "\nALL CasinoLive PROVIDER GAMES (Common for Lobbies):\n";
$res = mysqli_query($conn, "SELECT game_name, game_uid FROM tbl_games WHERE game_provider = 'CasinoLive'");
while($row = mysqli_fetch_assoc($res)) {
    echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . "\n";
}
?>
