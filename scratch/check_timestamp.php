<?php
define("ACCESS_SECURITY", "true");
include "security/config.php";

$query = "SELECT tbl_time_stamp FROM tblmatchplayed ORDER BY id DESC LIMIT 5";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "Timestamp: " . $row['tbl_time_stamp'] . "\n";
    }
}
?>
