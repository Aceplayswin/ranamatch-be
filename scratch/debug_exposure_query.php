<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$user_id = '8091921';
$res = mysqli_query($conn, "SELECT SUM(
    CASE
        WHEN LOWER(TRIM(tbl_bet_type)) = 'lay' AND tbl_match_profit > 0 THEN tbl_match_profit
        ELSE tbl_match_cost
    END
) AS total_exposure
FROM tblmatchplayed
WHERE tbl_user_id = '{$user_id}'
AND tbl_match_cost > 0
AND (LOWER(tbl_match_status) = 'wait' OR LOWER(tbl_match_result) = 'pending')");
$row = mysqli_fetch_assoc($res);
echo "SQL result: " . json_encode($row) . "\n";
?>
