<?php
include 'security/config.php';
header('Content-Type: text/plain');

echo "Current System Date: " . date('Y-m-d') . "\n";
echo "--- Recent Match Records ---\n";

$res = mysqli_query($conn, "SELECT tbl_user_id, tbl_project_name, tbl_time_stamp, tbl_match_status, tbl_match_cost, tbl_match_profit FROM tblmatchplayed ORDER BY id DESC LIMIT 10");
while($row = mysqli_fetch_assoc($res)) {
    $raw = $row['tbl_time_stamp'];
    $ts = strtotime($raw);
    $fmt = $ts ? date('Y-m-d', $ts) : 'FAILED';
    
    echo "Raw: [$raw] | Parsed: [$fmt] | Status: [".$row['tbl_match_status']."] | Game: [".$row['tbl_project_name']."]\n";
}

echo "\n--- Recent Recharge Records ---\n";
$res2 = mysqli_query($conn, "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge WHERE tbl_request_status = 'success' ORDER BY id DESC LIMIT 5");
while($row = mysqli_fetch_assoc($res2)) {
    $raw = $row['tbl_time_stamp'];
    $ts = strtotime($raw);
    $fmt = $ts ? date('Y-m-d', $ts) : 'FAILED';
    echo "Raw: [$raw] | Parsed: [$fmt] | Amt: [".$row['tbl_recharge_amount']."]\n";
}
?>
