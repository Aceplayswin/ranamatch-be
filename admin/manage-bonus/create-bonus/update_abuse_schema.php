<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

$queries = [
    "ALTER TABLE tbl_bonus_abuse ADD COLUMN block_vpn TINYINT(1) DEFAULT 0",
    "ALTER TABLE tbl_bonus_abuse ADD COLUMN block_proxy TINYINT(1) DEFAULT 0",
    "ALTER TABLE tbl_bonus_abuse ADD COLUMN exclude_days INT(11) DEFAULT 0"
];

foreach ($queries as $q) {
    mysqli_query($conn, $q);
}
echo "Abuse table updated.";
?>
