<?php
define("ACCESS_SECURITY", "true");
include "security/config.php";

$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    echo $row[0] . "\n";
}
?>
