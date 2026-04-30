<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

// Re-add tbl_notify_at column (was dropped, needed again for two-stage notification)
$r1 = $conn->query("ALTER TABLE tblmatchplayed
    ADD COLUMN IF NOT EXISTS tbl_notified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tbl_notify_at DATETIME DEFAULT NULL");
echo "Columns: " . ($r1 ? "OK" : $conn->error) . "\n";

// Safety: any NEW settled records (tbl_notified=0) that somehow got stuck
// with a stale tbl_notify_at from before — reset them so they get re-scheduled
$r2 = $conn->query("UPDATE tblmatchplayed
    SET tbl_notify_at = NULL
    WHERE tbl_match_status NOT IN ('wait')
    AND tbl_notified = 0
    AND tbl_notify_at IS NOT NULL
    AND tbl_notify_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)");
echo "Reset stale notify_at: " . $conn->affected_rows . " rows\n";

echo "Ready!\n";
$conn->close();
?>
