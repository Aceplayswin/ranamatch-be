<?php
define("ACCESS_SECURITY", "true");
include 'd:\xampp\htdocs\security\config.php';
$res = mysqli_query($conn, "DESCRIBE tbl_promotions");
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
