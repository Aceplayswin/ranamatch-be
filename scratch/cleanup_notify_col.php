<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

// Drop the unused tbl_notify_at column (no longer needed — using tbl_updated_at instead)
$r1 = $conn->query("ALTER TABLE tblmatchplayed DROP COLUMN IF EXISTS tbl_notify_at");
echo "Drop tbl_notify_at: " . ($r1 ? "OK" : $conn->error) . "\n";

// Confirm tbl_notified still exists
$r2 = $conn->query("SELECT tbl_notified FROM tblmatchplayed LIMIT 1");
echo "tbl_notified column: " . ($r2 ? "EXISTS OK" : "MISSING - " . $conn->error) . "\n";

// Confirm tbl_updated_at exists (needed for 8s delay query)
$r3 = $conn->query("SELECT tbl_updated_at FROM tblmatchplayed LIMIT 1");
echo "tbl_updated_at column: " . ($r3 ? "EXISTS OK" : "MISSING - " . $conn->error) . "\n";

echo "Done!\n";
$conn->close();
?>
