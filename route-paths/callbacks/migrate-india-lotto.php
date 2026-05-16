<?php
/**
 * Create the India Lotto transactions table
 * Run this ONCE to set up the database table
 */
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../../security/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `tbl_india_lotto_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `order_id` VARCHAR(100) NOT NULL UNIQUE,
    `buy_order_id` VARCHAR(100) DEFAULT '',
    `game_id` VARCHAR(50) DEFAULT '',
    `type` INT NOT NULL COMMENT '3=Bet, 4=Payout, 5=Refund, 9=Jackpot',
    `amount` DECIMAL(12,2) NOT NULL COMMENT 'Amount in rupees',
    `gift` INT DEFAULT 0,
    `description` VARCHAR(255) DEFAULT '',
    `created_at` DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $sql)) {
    echo "SUCCESS: tbl_india_lotto_transactions table created!";
} else {
    echo "ERROR: " . mysqli_error($conn);
}
