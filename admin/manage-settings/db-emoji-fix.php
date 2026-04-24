<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
// Simple security check
if($accessObj->validate()!="true"){
    die("Unauthorized access! Please login to admin first.");
}

echo "<h2>Starting Database Emoji Upgrade...</h2>";

// 1. Upgrade the table set
$q1 = mysqli_query($conn, "ALTER TABLE tblservices CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if($q1) {
    echo "<p style='color:green'>✅ Table 'tblservices' upgraded to utf8mb4 successfully.</p>";
} else {
    echo "<p style='color:red'>❌ Failed to upgrade Table: " . mysqli_error($conn) . "</p>";
}

// 2. Upgrade the column specifically
$q2 = mysqli_query($conn, "ALTER TABLE tblservices MODIFY tbl_service_value LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if($q2) {
    echo "<p style='color:green'>✅ Column 'tbl_service_value' upgraded to longtext successfully.</p>";
} else {
    echo "<p style='color:red'>❌ Failed to upgrade Column: " . mysqli_error($conn) . "</p>";
}

echo "<h3>Upgrade Complete! You can now use emojis like 🔥 in Branding.</h3>";
echo "<p><i>This file will now attempt to self-destruct for security...</i></p>";

// Self-destruct
if(unlink(__FILE__)) {
    echo "<p style='color:blue'>✅ Security Patch file deleted successfully.</p>";
} else {
    echo "<p style='color:orange'>⚠️ Cleanup failed. Please delete this file ('db-emoji-fix.php') manually.</p>";
}
?>
