<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$search_games = ['Aviator', 'Go Rush', 'Mines', 'Trump Card'];
echo "SEARCHING FOR SPECIFIC GAMES:\n";

foreach($search_games as $name) {
    $res = mysqli_query($conn, "SELECT game_name, game_uid, game_provider FROM tbl_games WHERE game_name LIKE '%$name%'");
    while($row = mysqli_fetch_assoc($res)) {
        echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . " | Provider: " . $row['game_provider'] . "\n";
    }
}
?>
