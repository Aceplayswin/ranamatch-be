<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

// Add columns
$r1 = $conn->query("ALTER TABLE tblmatchplayed 
    ADD COLUMN IF NOT EXISTS tbl_notified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tbl_notify_at DATETIME DEFAULT NULL");
echo "Add columns: " . ($r1 ? "OK" : $conn->error) . "\n";

// Mark all existing settled records as already notified so they don't spam
$r2 = $conn->query("UPDATE tblmatchplayed 
    SET tbl_notified = 1 
    WHERE tbl_match_status NOT IN ('wait') 
    AND tbl_notified = 0 
    AND tbl_notify_at IS NULL");
echo "Old records marked as notified: " . $conn->affected_rows . "\n";

// Add index
$r3 = $conn->query("CREATE INDEX IF NOT EXISTS idx_notifications ON tblmatchplayed(tbl_user_id, tbl_notified, tbl_notify_at)");
echo "Index: " . ($r3 ? "OK" : "Already exists (OK)") . "\n";

echo "Migration complete!\n";
$conn->close();
?>
