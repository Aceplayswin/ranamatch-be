<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

$tables = [
    'tbl_bonus_providers',
    'tbl_bonus_content',
    'tbl_bonus_abuse',
    'tbl_bonuses'
];

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tables as $table) {
    if (mysqli_query($conn, "TRUNCATE TABLE $table")) {
        echo "Successfully truncated $table\n";
    } else {
        echo "Error truncating $table: " . mysqli_error($conn) . "\n";
    }
}

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
?>
