<?php
define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

echo "Running Migration: Affiliate Settlement & Watermark Schema...\n";

// 1. Add last_settled_at & settlement_cycle to affiliates table if not exists
$checkCol1 = $conn->query("SHOW COLUMNS FROM affiliates LIKE 'last_settled_at'");
if ($checkCol1 && $checkCol1->num_rows == 0) {
    $conn->query("ALTER TABLE affiliates ADD COLUMN last_settled_at TIMESTAMP NULL DEFAULT NULL AFTER lifetime_earnings");
    echo "Added last_settled_at to affiliates table.\n";
} else {
    echo "last_settled_at already exists in affiliates table.\n";
}

$checkCol2 = $conn->query("SHOW COLUMNS FROM affiliates LIKE 'settlement_cycle'");
if ($checkCol2 && $checkCol2->num_rows == 0) {
    $conn->query("ALTER TABLE affiliates ADD COLUMN settlement_cycle ENUM('daily', 'weekly_monday', 'bi_weekly', 'monthly') DEFAULT 'weekly_monday' AFTER last_settled_at");
    echo "Added settlement_cycle to affiliates table.\n";
} else {
    echo "settlement_cycle already exists in affiliates table.\n";
}

// 2. Create affiliate_settlements table
$sqlSettlements = "CREATE TABLE IF NOT EXISTS affiliate_settlements (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT(11) NOT NULL,
    cycle_label VARCHAR(100) NOT NULL,
    start_time TIMESTAMP NULL DEFAULT NULL,
    end_time TIMESTAMP NULL DEFAULT NULL,
    total_bets DECIMAL(20,2) DEFAULT 0.00,
    total_wins DECIMAL(20,2) DEFAULT 0.00,
    total_losses DECIMAL(20,2) DEFAULT 0.00,
    total_deposits DECIMAL(20,2) DEFAULT 0.00,
    total_withdrawals DECIMAL(20,2) DEFAULT 0.00,
    total_ggr DECIMAL(20,2) DEFAULT 0.00,
    total_ngr DECIMAL(20,2) DEFAULT 0.00,
    commission_amount DECIMAL(20,2) DEFAULT 0.00,
    sub_override_amount DECIMAL(20,2) DEFAULT 0.00,
    total_payout DECIMAL(20,2) DEFAULT 0.00,
    status ENUM('pending', 'settled', 'paid', 'cancelled') DEFAULT 'settled',
    settled_by VARCHAR(50) DEFAULT 'admin',
    settled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (affiliate_id),
    INDEX (settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sqlSettlements)) {
    echo "affiliate_settlements table verified / created successfully.\n";
} else {
    echo "Error creating affiliate_settlements: " . $conn->error . "\n";
}

echo "Migration completed successfully!\n";
