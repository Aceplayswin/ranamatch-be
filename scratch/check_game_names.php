<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "DETAILS FROM tbl_game_names:\n";
$res = mysqli_query($conn, "SELECT * FROM tbl_game_names LIMIT 20");
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
