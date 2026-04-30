<?php
define("ACCESS_SECURITY", "true");
include "security/config.php";

$sql = "SELECT game_uid, game_name, game_category, game_provider FROM tbl_games ORDER BY game_provider, game_name";
$result = mysqli_query($conn, $sql);

$games = [];
while ($row = mysqli_fetch_assoc($result)) {
    $games[] = $row;
}

echo json_encode($games, JSON_PRETTY_PRINT);
?>
