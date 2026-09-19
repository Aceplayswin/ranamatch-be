<?php
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../security/config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$resArr = [
    "status_code" => "success",
    "payment_methods" => []
];

$create_table_sql = "CREATE TABLE IF NOT EXISTS `tbl_payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `method_name` VARCHAR(100) NOT NULL,
  `method_type` VARCHAR(50) NOT NULL DEFAULT 'upi',
  `account_name` VARCHAR(150) DEFAULT NULL,
  `account_number_or_upi` VARCHAR(255) DEFAULT NULL,
  `ifsc_or_bank_name` VARCHAR(150) DEFAULT NULL,
  `qr_code_image` VARCHAR(255) DEFAULT NULL,
  `min_deposit` DECIMAL(10,2) DEFAULT 100.00,
  `max_deposit` DECIMAL(10,2) DEFAULT 50000.00,
  `bonus_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `instructions` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
@mysqli_query($conn, $create_table_sql);

$sql = "SELECT id, method_name, method_type, account_name, account_number_or_upi, ifsc_or_bank_name, qr_code_image, min_deposit, max_deposit, bonus_percentage, instructions 
        FROM tbl_payment_methods 
        WHERE status = 'active' 
        ORDER BY id ASC";

$result = @mysqli_query($conn, $sql);


if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $resArr['payment_methods'][] = [
