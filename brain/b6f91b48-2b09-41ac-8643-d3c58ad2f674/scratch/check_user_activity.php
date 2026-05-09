<?php
define("ACCESS_SECURITY", true);
include 'd:/xampp/htdocs/security/config.php';
if (!isset($conn)) {
    die("Connection failed");
}

$res = $conn->query("SELECT tbl_uniq_id, tbl_last_active_date, tbl_last_active_time FROM tblusersdata LIMIT 5");
$data = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
?>
