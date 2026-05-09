<?php
define("ACCESS_SECURITY", true);
include '../security/config.php';
if (!isset($conn)) {
    die("Connection failed");
}

$res = $conn->query("SELECT * FROM tbl_bonus_content LIMIT 10");
$data = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
}

$res2 = $conn->query("SELECT * FROM tbl_cashback_bonuses LIMIT 10");
$data2 = [];
if ($res2) {
    while($row = $res2->fetch_assoc()) {
        $data2[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode(['standard' => $data, 'cashback' => $data2], JSON_PRETTY_PRINT);
?>
