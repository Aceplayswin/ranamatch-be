<?php
define("ACCESS_SECURITY", true);
include "d:/xampp/htdocs/security/config.php";

$sql = "ALTER TABLE tblusersdata ADD COLUMN tbl_user_name VARCHAR(255) AFTER tbl_uniq_id";
if (mysqli_query($conn, $sql)) {
    echo "Column tbl_user_name added successfully\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Also make sure it's unique if possible, but let's just start with adding it.
$sql = "CREATE UNIQUE INDEX idx_user_name ON tblusersdata(tbl_user_name)";
if (mysqli_query($conn, $sql)) {
    echo "Unique index added successfully\n";
} else {
    echo "Error adding unique index: " . mysqli_error($conn) . "\n";
}
?>
