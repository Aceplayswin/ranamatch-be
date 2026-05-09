<?php
define("ACCESS_SECURITY", true);
include 'd:/xampp/htdocs/security/config.php';

echo "Standard Bonuses:\n";
$res = $conn->query("SELECT id, image_path FROM tbl_bonus_content LIMIT 5");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Path: " . $row['image_path'] . "\n";
}

echo "\nCashback Bonuses:\n";
$res = $conn->query("SELECT id, image_path FROM tbl_cashback_bonuses LIMIT 5");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Path: " . $row['image_path'] . "\n";
}
?>
