<?php
define("ACCESS_SECURITY", true);
require_once __DIR__ . '/config.php';

echo "=== Setting up tbl_agent_approvals table ===\n";

$sql = "CREATE TABLE IF NOT EXISTS tbl_agent_approvals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(50) NOT NULL UNIQUE,
    requester_id INT NOT NULL,
    requester_type ENUM('agent', 'player') NOT NULL DEFAULT 'agent',
    approver_id INT NOT NULL,
    approval_type ENUM('downline_registration', 'credit_recharge', 'payout_withdrawal', 'commission_rate_change') NOT NULL,
    amount DECIMAL(12,2) DEFAULT 0.00,
    details_json LONGTEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,
    processed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approver_status (approver_id, status),
    INDEX idx_requester (requester_id),
    INDEX idx_approval_type (approval_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "SUCCESS: tbl_agent_approvals created or already exists.\n";
} else {
    echo "ERROR: " . mysqli_error($conn) . "\n";
}
