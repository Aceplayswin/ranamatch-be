<?php
/**
 * Migration: Add tbl_notified and tbl_notify_at columns to tblmatchplayed.
 * Run this ONCE via browser: http://localhost/game/migrate_notifications.php
 * Safe to re-run — uses IF NOT EXISTS.
 */
define("ACCESS_SECURITY", "true");
include "../security/config.php";

$results = [];

// 1. Add the two new columns
$r1 = $conn->query("ALTER TABLE tblmatchplayed 
    ADD COLUMN IF NOT EXISTS tbl_notified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tbl_notify_at DATETIME DEFAULT NULL");

$results[] = "Add columns: " . ($r1 ? "OK" : $conn->error);

// 2. For existing records that are already settled and were never notified,
//    mark them as already notified (tbl_notified = 1) so they don't spam on first load.
$r2 = $conn->query("UPDATE tblmatchplayed 
    SET tbl_notified = 1 
    WHERE tbl_match_status NOT IN ('wait') 
    AND tbl_notified = 0 
    AND tbl_notify_at IS NULL");

$results[] = "Mark old records as notified: " . ($r2 ? "Rows affected: " . $conn->affected_rows : $conn->error);

// 3. Add index for fast notification queries
$r3 = $conn->query("CREATE INDEX IF NOT EXISTS idx_notifications 
    ON tblmatchplayed(tbl_user_id, tbl_notified, tbl_notify_at)");

$results[] = "Add index: " . ($r3 ? "OK" : "Already exists (OK)");

echo "<pre>";
echo "=== Notification Migration Complete ===\n\n";
foreach ($results as $r) {
    echo "- $r\n";
}
echo "\nAll done! You can now delete this file.";
echo "</pre>";

$conn->close();
?>
