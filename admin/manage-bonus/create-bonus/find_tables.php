<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

// Check for user tables
$tables = ['tbl_users', 'users', 'tbl_players', 'players', 'tbl_members', 'members', 'tbl_accounts'];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (mysqli_num_rows($res) > 0) {
        echo "FOUND: $t\n";
        $desc = mysqli_query($conn, "DESCRIBE $t");
        while ($row = mysqli_fetch_assoc($desc)) {
            echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "\n";
    }
}

// Also check for redemption tables
$rtables = ['tbl_bonus_redemptions', 'tbl_redemptions', 'bonus_redemptions', 'tbl_bonus_history'];
foreach ($rtables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (mysqli_num_rows($res) > 0) {
        echo "FOUND: $t\n";
        $desc = mysqli_query($conn, "DESCRIBE $t");
        while ($row = mysqli_fetch_assoc($desc)) {
            echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
        }
    }
}
?>
