<?php
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../security/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `tbl_payment_methods` (
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

if (mysqli_query($conn, $sql)) {
    echo "Table tbl_payment_methods created or already exists successfully.\n";

    // Insert sample default payment methods if empty
    $check_sql = "SELECT COUNT(*) as count FROM `tbl_payment_methods`";
    $check_res = mysqli_query($conn, $check_sql);
    $row = mysqli_fetch_assoc($check_res);

    if ($row['count'] == 0) {
        $sample_sql = "INSERT INTO `tbl_payment_methods` 
        (`method_name`, `method_type`, `account_name`, `account_number_or_upi`, `ifsc_or_bank_name`, `qr_code_image`, `min_deposit`, `max_deposit`, `bonus_percentage`, `instructions`, `status`) VALUES
        ('UPI Instant Pay', 'upi', 'Merchant Official', 'paytmqr2810050501011@paytm', 'Paytm Bank', '', 100.00, 50000.00, 5.00, 'Scan QR or copy UPI ID to complete your payment. Enter UTR reference number after payment.', 'active'),
        ('Bank Wire Transfer', 'bank_transfer', 'HDFC Main Business Account', '50200012345678', 'HDFC0001234', '', 500.00, 200000.00, 0.00, 'Transfer directly to account number and upload transfer screenshot or UTR number.', 'active'),
        ('USDT (TRC20)', 'crypto', 'Crypto Deposit Wallet', 'TY1234567890ABCDEF1234567890ABCDEF', 'Tron TRC20', '', 1000.00, 500000.00, 2.00, 'Send USDT TRC20 to the address. 1 USDT = Server Rate.', 'active');";
        
        if (mysqli_query($conn, $sample_sql)) {
            echo "Sample payment methods inserted successfully.\n";
        }
    }
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}
?>
