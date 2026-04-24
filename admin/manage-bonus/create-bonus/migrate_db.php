<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

$queries = [
    "ALTER TABLE tbl_bonuses ADD COLUMN is_published TINYINT(1) DEFAULT 1 AFTER status",
    "ALTER TABLE tbl_bonuses ADD COLUMN is_public TINYINT(1) DEFAULT 1 AFTER is_published",
    "ALTER TABLE tbl_bonuses ADD COLUMN affiliate_mapping VARCHAR(50) DEFAULT 'none' AFTER affiliate_id",
    "ALTER TABLE tbl_bonuses ADD COLUMN parent_coupon_code VARCHAR(100) DEFAULT NULL AFTER coupon_code",
    "ALTER TABLE tbl_bonuses ADD COLUMN payment_methods VARCHAR(255) DEFAULT NULL AFTER bonus_category",
    "ALTER TABLE tbl_bonuses ADD COLUMN player_limit_type VARCHAR(50) DEFAULT 'daily' AFTER limit_per_player",
    "ALTER TABLE tbl_bonuses ADD COLUMN redemption_pattern TEXT DEFAULT NULL AFTER end_at"
];

foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "Executed: $q\n";
    } else {
        echo "Error on $q: " . mysqli_error($conn) . "\n";
    }
}
?>
