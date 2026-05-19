<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
$res = mysqli_query($conn, "DESCRIBE tblmatchplayed");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
?>
