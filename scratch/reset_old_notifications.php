<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

// Reset any pending notifications that were scheduled with the old 8s window
// They'll get rescheduled with the new 35s window on next poll
$r = mysqli_query($conn, "UPDATE tblmatchplayed 
    SET tbl_notify_at = NULL 
    WHERE tbl_notified = 0 
    AND tbl_notify_at IS NOT NULL 
    AND tbl_notify_at > NOW()");

echo "Reset " . mysqli_affected_rows($conn) . " pending notifications (will reschedule with 35s delay)\n";
$conn->close();
?>
