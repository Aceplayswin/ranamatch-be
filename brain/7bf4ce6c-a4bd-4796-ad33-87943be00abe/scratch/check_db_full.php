<?php
define("ACCESS_SECURITY", true);
include "d:/xampp/htdocs/security/config.php";

$sql = "SHOW COLUMNS FROM tblusersdata";
$result = mysqli_query($conn, $sql);
$cols = [];
while($row = mysqli_fetch_assoc($result)) {
    $cols[] = $row;
}
echo json_encode($cols);
?>
