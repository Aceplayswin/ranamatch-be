<?php
define("ACCESS_SECURITY", true);
include "d:/xampp/htdocs/security/config.php";

$sql = "DESCRIBE tblusersdata";
$result = mysqli_query($conn, $sql);
$columns = [];
while($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
}
echo json_encode($columns);
?>
