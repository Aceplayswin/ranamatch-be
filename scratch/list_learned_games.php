<?php
define("ACCESS_SECURITY", "true");
include "security/config.php";

$sql = "SELECT tbl_game_id, tbl_game_name, tbl_last_updated FROM tbl_game_names ORDER BY tbl_last_updated DESC";
$result = mysqli_query($conn, $sql);

$games = [];
while ($row = mysqli_fetch_assoc($result)) {
    $games[] = $row;
}

echo json_encode($games, JSON_PRETTY_PRINT);
?>
