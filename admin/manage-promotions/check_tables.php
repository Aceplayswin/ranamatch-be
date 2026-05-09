<?php
define("ACCESS_SECURITY", "true");
include 'd:\xampp\htdocs\security\config.php';
$res = mysqli_query($conn, "SHOW TABLES");
while($row = mysqli_fetch_row($res)) {
    echo $row[0] . "\n";
}
?>
