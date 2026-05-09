<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$user_id = 'test_user'; // Doesn't matter if it exists, just checking syntax

$res = mysqli_query($conn, "UPDATE tblmatchplayed
    SET tbl_notify_at = CASE
        WHEN tbl_project_name LIKE '%Roulette%' THEN DATE_ADD(NOW(), INTERVAL 60 SECOND)
        ELSE DATE_ADD(NOW(), INTERVAL 8 SECOND)
    END
    WHERE tbl_user_id = '{$user_id}'
    AND tbl_match_status NOT IN ('wait')
    AND tbl_notified = 0
    AND tbl_notify_at IS NULL");

if (!$res) {
    echo "SQL ERROR: " . mysqli_error($conn);
} else {
    echo "SQL OK. Rows updated: " . mysqli_affected_rows($conn);
}
?>
