<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$res = mysqli_query($conn, "SELECT game_name, game_uid, game_category, game_provider, game_image, game_status FROM tbl_games ORDER BY game_provider, game_name");
$games = [];
while($row = mysqli_fetch_assoc($res)) {
    $games[] = $row;
}

$json_content = json_encode($games, JSON_PRETTY_PRINT);
$output_file = 'd:/xampp/htdocs/scratch/all_games_details.json';
file_put_contents($output_file, $json_content);

echo "SUCCESS: Saved " . count($games) . " games to " . $output_file . "\n";
?>
