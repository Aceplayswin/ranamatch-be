<?php
define("ACCESS_SECURITY", true);
include '../security/config.php';
if (!isset($conn)) {
    die("Connection failed");
}

$res = $conn->query("SELECT id, image_path FROM tbl_bonus_content LIMIT 5");
$data = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
}

$res2 = $conn->query("SELECT id, image_path FROM tbl_cashback_bonuses LIMIT 5");
$data2 = [];
if ($res2) {
    while($row = $res2->fetch_assoc()) {
        $data2[] = $row;
    }
}

file_put_contents('bonus_paths.txt', json_encode(['standard' => $data, 'cashback' => $data2], JSON_PRETTY_PRINT));
echo "Done";
?>
