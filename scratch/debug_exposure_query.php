<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$user_id = '8091921';
$res = mysqli_query($conn, "SELECT SUM(tbl_match_cost) AS total_exposure FROM tblmatchplayed WHERE tbl_user_id = '{$user_id}' AND tbl_match_status = 'wait'");
$row = mysqli_fetch_assoc($res);
echo "SQL result: " . json_encode($row) . "\n";
?>
