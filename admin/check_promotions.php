<?php
define("ACCESS_SECURITY", true);
include '../security/config.php';
if (!isset($conn)) {
    die("Connection failed - check config path.");
}
$res = $conn->query("SELECT * FROM tbl_promotions");
$data = [];
while($row = $res->fetch_assoc()) {
    $row['file_exists'] = file_exists("../" . $row['image_path']);
    $data[] = $row;
}
header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
?>
