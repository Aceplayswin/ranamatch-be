<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "========================================================\n";
echo "DISCOVERED GAMES FROM PROVIDER LOBBIES\n";
echo "========================================================\n";
echo "These games were automatically recorded when a user played them\n";
echo "inside a lobby (Ezugi, Evolution, etc.)\n\n";

$sql = "SELECT tbl_game_id, tbl_game_name, tbl_last_updated FROM tbl_game_names ORDER BY tbl_last_updated DESC";
$res = mysqli_query($conn, $sql);

if (!$res) {
    echo "Error: " . mysqli_error($conn) . "\n";
    exit;
}

if (mysqli_num_rows($res) == 0) {
    echo "No games discovered yet. Try opening a lobby and playing a game!\n";
} else {
    echo str_pad("GAME UID", 40) . " | " . str_pad("GAME NAME", 30) . " | LAST DISCOVERED\n";
    echo str_repeat("-", 100) . "\n";
    while($row = mysqli_fetch_assoc($res)) {
        echo str_pad($row['tbl_game_id'], 40) . " | " . str_pad($row['tbl_game_name'], 30) . " | " . $row['tbl_last_updated'] . "\n";
    }
}

echo "\n========================================================\n";
?>
