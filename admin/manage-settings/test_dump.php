<?php
define("ACCESS_SECURITY", "true");
require_once "../../security/config.php";
$res = mysqli_query($conn, "SELECT * FROM tblservices");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['tbl_service_name'] . " = " . $row['tbl_service_value'] . "\n";
}
?>
