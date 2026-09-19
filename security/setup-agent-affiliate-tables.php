<?php
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../security/config.php';

header('Content-Type: text/plain');

echo "=== SPRINT 1 DATABASE MIGRATION START ===\n\n";

if (!$conn) {
    die("Database connection failed!\n");
}

// Enable exception mode for reporting error details
mysqli_report(MYSQLI_REPORT_OFF);

// Helper function to run query safely
function runQuery($conn, $sql, $tableName) {
    echo "Processing $tableName... ";
    if (mysqli_query($conn, $sql)) {
        echo "✅ SUCCESS\n";
        return true;
    } else {
        echo "❌ ERROR: " . mysqli_error($conn) . "\n";
        return false;
    }
}

// 1. CREATE program_settings
$sql1 = "CREATE TABLE IF NOT EXISTS program_settings (
    id INT PRIMARY KEY DEFAULT 1,
    agent_default_level ENUM('senior_super_agent', 'super_agent', 'master_agent', 'agent') NOT NULL DEFAULT 'agent',
    agent_review_time_hours INT NOT NULL DEFAULT 24,
    agent_default_partnership_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    agent_default_commission_rate_pct DECIMAL(5,2) NOT NULL DEFAULT 2.50,
    agent_default_opening_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    agent_settlement_cycle ENUM('daily', 'weekly_monday', 'bi_weekly', 'monthly') NOT NULL DEFAULT 'weekly_monday',
    agent_minimum_payout DECIMAL(18,2) NOT NULL DEFAULT 1000.00,
    agent_negative_carryover TINYINT(1) NOT NULL DEFAULT 1,
    affiliate_default_tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    affiliate_default_revshare_pct DECIMAL(5,2) NOT NULL DEFAULT 35.00,
    affiliate_default_cpa_amount DECIMAL(18,2) NOT NULL DEFAULT 50.00,
    affiliate_cpa_min_deposit_threshold DECIMAL(18,2) NOT NULL DEFAULT 100.00,
    affiliate_cookie_duration_days INT NOT NULL DEFAULT 30,
    affiliate_minimum_payout DECIMAL(18,2) NOT NULL DEFAULT 1000.00,
    affiliate_payout_hold_days INT NOT NULL DEFAULT 7,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql1, "program_settings");

// Seed program_settings row 1 if missing
mysqli_query($conn, "INSERT INTO program_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id=1;");

// 2. CREATE agent_tree
$sql2 = "CREATE TABLE IF NOT EXISTS agent_tree (
    ancestor_id INT NOT NULL,
    descendant_id INT NOT NULL,
    depth INT NOT NULL,
    PRIMARY KEY (ancestor_id, descendant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql2, "agent_tree");

// 3. CREATE agent_credit_ledger
$sql3 = "CREATE TABLE IF NOT EXISTS agent_credit_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    admin_id INT NULL,
    transferring_agent_id INT NULL,
    transaction_type VARCHAR(32) NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    balance_before DECIMAL(18,2) NOT NULL,
    balance_after DECIMAL(18,2) NOT NULL,
    remark TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql3, "agent_credit_ledger");

// 4. CREATE agent_applications
$sql4 = "CREATE TABLE IF NOT EXISTS agent_applications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(128) NOT NULL,
    username VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    company VARCHAR(128),
    phone VARCHAR(64),
    password_hash VARCHAR(255),
    market_region VARCHAR(128) NOT NULL,
    volume_bracket VARCHAR(32) NOT NULL,
    expected_players VARCHAR(32),
    experience VARCHAR(64),
    application_notes TEXT,
    requested_upline_id INT NULL,
    claimed_upline_text VARCHAR(128),
    status ENUM('pending', 'info_requested', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT,
    info_request_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql4, "agent_applications");

// Add fields to installations created before the expanded application form.
$applicationColumns = [
    "phone" => "VARCHAR(64) NULL",
    "password_hash" => "VARCHAR(255) NULL",
    "expected_players" => "VARCHAR(32) NULL",
    "experience" => "VARCHAR(64) NULL",
    "application_notes" => "TEXT NULL"
];
foreach ($applicationColumns as $column => $definition) {
    $columnEscaped = mysqli_real_escape_string($conn, $column);
    $exists = mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_applications' AND COLUMN_NAME = '$columnEscaped' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) === 0) {
        runQuery($conn, "ALTER TABLE agent_applications ADD COLUMN `$column` $definition", "agent_applications.$column");
    }
}

// 5. CREATE sports_bets
$sql5 = "CREATE TABLE IF NOT EXISTS sports_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bet_amount DECIMAL(18,2) NOT NULL,
    win_amount DECIMAL(18,2) DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql5, "sports_bets");

// 6. CREATE casino_bets
$sql6 = "CREATE TABLE IF NOT EXISTS casino_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bet_amount DECIMAL(18,2) NOT NULL,
    win_amount DECIMAL(18,2) DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql6, "casino_bets");

// 6b. CREATE player_bets
$sql6b = "CREATE TABLE IF NOT EXISTS player_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    bet_ref VARCHAR(100) NULL,
    agent_id INT NOT NULL,
    user_id INT NOT NULL,
    game_type VARCHAR(32) NOT NULL DEFAULT 'sports',
    event_name VARCHAR(255) NOT NULL DEFAULT 'Cricket Match',
    market_name VARCHAR(255) NOT NULL DEFAULT 'Match Odds',
    selection_name VARCHAR(255) NOT NULL DEFAULT 'Team 1',
    side VARCHAR(16) NOT NULL DEFAULT 'back',
    odds DECIMAL(10,2) NOT NULL DEFAULT 1.95,
    stake DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    liability DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    win_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    pnl DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL,
    INDEX idx_pb_agent_id (agent_id),
    INDEX idx_pb_user_id (user_id),
    INDEX idx_pb_status (status),
    INDEX idx_pb_game_type (game_type),
    INDEX idx_pb_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql6b, "player_bets");

// 7. CREATE admin_audit_logs
$sql7 = "CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_group VARCHAR(64) NOT NULL,
    action_type VARCHAR(64) NOT NULL,
    target_entity_id BIGINT NOT NULL,
    target_entity_type VARCHAR(32) NOT NULL,
    payload_before JSON NULL,
    payload_after JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($conn, $sql7, "admin_audit_logs");

// 8. ALTER TABLE agents (to align with blueprint)
echo "\n--- Aligning 'agents' Table Columns ---\n";
$agent_cols = [
    "agent_code" => "ALTER TABLE agents ADD COLUMN agent_code VARCHAR(32) UNIQUE NULL AFTER id;",
    "name" => "ALTER TABLE agents ADD COLUMN name VARCHAR(128) NULL AFTER agent_code;",
    "rank_level" => "ALTER TABLE agents ADD COLUMN rank_level ENUM('senior_super_agent', 'super_agent', 'master_agent', 'agent') NOT NULL DEFAULT 'agent';",
    "partnership_pct" => "ALTER TABLE agents ADD COLUMN partnership_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00;",
    "turnover_commission_pct" => "ALTER TABLE agents ADD COLUMN turnover_commission_pct DECIMAL(5,2) NOT NULL DEFAULT 2.50;",
    "current_credit" => "ALTER TABLE agents ADD COLUMN current_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00;",
    "exposed_credit" => "ALTER TABLE agents ADD COLUMN exposed_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00;"
];

foreach ($agent_cols as $col => $alterSql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM agents LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        runQuery($conn, $alterSql, "agents (add $col)");
    } else {
        echo "Column '$col' already exists in 'agents'. Skipping.\n";
    }
}

// 9. ALTER TABLE affiliates (to align with blueprint)
echo "\n--- Aligning 'affiliates' Table Columns ---\n";
$aff_cols = [
    "affiliate_code" => "ALTER TABLE affiliates ADD COLUMN affiliate_code VARCHAR(32) UNIQUE NULL AFTER id;",
    "deal_type" => "ALTER TABLE affiliates ADD COLUMN deal_type ENUM('revenue_share', 'cpa', 'hybrid') NOT NULL DEFAULT 'revenue_share';",
    "revshare_pct" => "ALTER TABLE affiliates ADD COLUMN revshare_pct DECIMAL(5,2) NOT NULL DEFAULT 35.00;",
    "sub_override_pct" => "ALTER TABLE affiliates ADD COLUMN sub_override_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00;",
    "available_balance" => "ALTER TABLE affiliates ADD COLUMN available_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00;",
    "pending_balance" => "ALTER TABLE affiliates ADD COLUMN pending_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00;",
    "lifetime_earnings" => "ALTER TABLE affiliates ADD COLUMN lifetime_earnings DECIMAL(18,2) NOT NULL DEFAULT 0.00;"
];

foreach ($aff_cols as $col => $alterSql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM affiliates LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        runQuery($conn, $alterSql, "affiliates (add $col)");
    } else {
        echo "Column '$col' already exists in 'affiliates'. Skipping.\n";
    }
}

// 10. ALTER TABLE affiliate_referrals (to align with blueprint)
echo "\n--- Aligning 'affiliate_referrals' Table Columns ---\n";
$ref_cols = [
    "first_deposit_amount" => "ALTER TABLE affiliate_referrals ADD COLUMN first_deposit_amount DECIMAL(18,2) DEFAULT 0.00;",
    "is_cpa_qualified" => "ALTER TABLE affiliate_referrals ADD COLUMN is_cpa_qualified BOOLEAN DEFAULT FALSE;"
];

foreach ($ref_cols as $col => $alterSql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM affiliate_referrals LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        runQuery($conn, $alterSql, "affiliate_referrals (add $col)");
    } else {
        echo "Column '$col' already exists in 'affiliate_referrals'. Skipping.\n";
    }
}

// 11. ALTER TABLE affiliate_payouts (to align with blueprint)
echo "\n--- Aligning 'affiliate_payouts' Table Columns ---\n";
$pay_cols = [
    "payout_method_type" => "ALTER TABLE affiliate_payouts ADD COLUMN payout_method_type VARCHAR(32) NULL;",
    "payout_method_details" => "ALTER TABLE affiliate_payouts ADD COLUMN payout_method_details JSON NULL;",
    "transaction_reference" => "ALTER TABLE affiliate_payouts ADD COLUMN transaction_reference VARCHAR(128) NULL;",
    "rejection_reason" => "ALTER TABLE affiliate_payouts ADD COLUMN rejection_reason TEXT NULL;",
    "requested_at" => "ALTER TABLE affiliate_payouts ADD COLUMN requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;",
    "paid_at" => "ALTER TABLE affiliate_payouts ADD COLUMN paid_at TIMESTAMP NULL;"
];

foreach ($pay_cols as $col => $alterSql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM affiliate_payouts LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        runQuery($conn, $alterSql, "affiliate_payouts (add $col)");
    } else {
        echo "Column '$col' already exists in 'affiliate_payouts'. Skipping.\n";
    }
}

echo "\n=== SPRINT 1 DATABASE MIGRATION COMPLETE ===\n";
