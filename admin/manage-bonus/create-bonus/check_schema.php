<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';
$res = mysqli_query($conn, "DESCRIBE tbl_bonuses");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
?>
