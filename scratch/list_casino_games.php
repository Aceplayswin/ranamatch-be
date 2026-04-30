<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';
$res = mysqli_query($conn, "SELECT game_name FROM tbl_games WHERE game_category = 'casino'");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['game_name'] . "\n";
}
?>
