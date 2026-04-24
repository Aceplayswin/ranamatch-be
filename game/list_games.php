<?php
define("ACCESS_SECURITY", "true");
include "../security/config.php";
$res = mysqli_query($conn, "SELECT tbl_game_id, tbl_game_name FROM tbl_game_names WHERE tbl_game_id = 'd7e5f6258dd0dfdd29b3798f124b6b9d'");
while($row = mysqli_fetch_assoc($res)) {
    echo $row["tbl_game_id"] . " => " . $row["tbl_game_name"] . "\n";
}
?>
