<?php
define("ACCESS_SECURITY", "true");
include 'security/config.php';
$res = mysqli_query($conn, "SELECT game_uid, game_name FROM tbl_games WHERE game_provider = 'India Lotto'");
echo "India Lotto Games in DB:\n";
while($row = mysqli_fetch_assoc($res)) {
    echo $row['game_uid'] . " -> " . $row['game_name'] . "\n";
}
?>
