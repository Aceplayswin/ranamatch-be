<?php
define("ACCESS_SECURITY", true);
include __DIR__ . '/../security/config.php';
$res = mysqli_query($conn, "SHOW TABLES");
while($row = mysqli_fetch_array($res)) {
    echo $row[0] . "\n";
}
?>
