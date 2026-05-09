<?php
define("ACCESS_SECURITY", true);
include '../security/config.php';
if (!isset($conn)) {
    die("Connection failed - check config path.");
}

$sliders = [];
$res1 = $conn->query("SELECT * FROM tblsliders");
if ($res1) {
    while($row = $res1->fetch_assoc()) {
        $row['file_exists'] = file_exists("../" . ($row['tbl_slider_img'] ?? ""));
        $sliders[] = $row;
    }
}

$promos = [];
$res2 = $conn->query("SELECT * FROM tbl_promotions");
if ($res2) {
    while($row = $res2->fetch_assoc()) {
        $row['file_exists'] = file_exists("../" . ($row['image_path'] ?? ""));
        $promos[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'tblsliders' => $sliders,
    'tbl_promotions' => $promos
], JSON_PRETTY_PRINT);
?>
