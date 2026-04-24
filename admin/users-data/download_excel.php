<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

// Handle download request
if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=all_user_data.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
        <th>No</th>
        <th>ID</th>
        <th>Username</th>
        <th>Balance</th>
        <th>Total Deposit</th>
        <th>Total Withdraw</th>
        <th>Total Bet Amount</th>
        <th>Total Sports Bet</th>
        <th>Sports P&L</th>
        <th>Sports Profit</th>
        <th>Sports Loss</th>
        <th>Mobile</th>
        <th>User IP</th>
        <th>Date & Time</th>
        <th>Status</th>
    </tr>";

    $index = 1;
    $query = "SELECT * FROM tblusersdata ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $uniq_id = $row['tbl_uniq_id'];
        $username = $row['tbl_full_name'];
        $balance = $row['tbl_balance'];
        $mobile = $row['tbl_mobile_num'];
        $joined = $row['tbl_user_joined'];
        $status_raw = $row['tbl_account_status'];

        // Get IP
        $ip = 'N/A';
        $ip_result = mysqli_query($conn, "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id='$uniq_id' ORDER BY id ASC LIMIT 1");
        if ($ip_row = mysqli_fetch_assoc($ip_result)) {
            $ip = $ip_row['tbl_device_ip'];
        }

        // Deposit
        $deposit = 0;
        $d_query = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) AS total FROM tblusersrecharge WHERE tbl_user_id='$uniq_id' AND tbl_request_status='success'");
        if ($d_row = mysqli_fetch_assoc($d_query)) {
            $deposit = $d_row['total'] ?? 0;
        }

        // Withdraw
        $withdraw = 0;
        $w_query = mysqli_query($conn, "SELECT SUM(tbl_withdraw_amount) AS total FROM tbluserswithdraw WHERE tbl_user_id='$uniq_id' AND tbl_request_status='success'");
        if ($w_row = mysqli_fetch_assoc($w_query)) {
            $withdraw = $w_row['total'] ?? 0;
        }

        // Total bet
        $total_bet = 0;
        $b_query = mysqli_query($conn, "SELECT SUM(tbl_match_cost) AS total FROM tblmatchplayed WHERE tbl_user_id='$uniq_id'");
        if ($b_row = mysqli_fetch_assoc($b_query)) {
            $total_bet = $b_row['total'] ?? 0;
        }

        // Sports data
        $sports_bet = 0;
        $sports_profit_total = 0;
        $sports_profit = 0;
        $sports_loss = 0;
        $sports_query = mysqli_query($conn, "
            SELECT tbl_match_cost, tbl_match_profit 
            FROM tblmatchplayed 
            WHERE tbl_user_id='$uniq_id' 
            AND LOWER(tbl_project_name) IN ('saba sports', 'lucksport', 'lucksportgaming')
        ");
        while ($s_row = mysqli_fetch_assoc($sports_query)) {
            $cost = $s_row['tbl_match_cost'];
            $profit = $s_row['tbl_match_profit'];
            $net = $profit - $cost;
            $sports_bet += $cost;
            $sports_profit_total += $profit;
            if ($net > 0) $sports_profit += $net;
            elseif ($net < 0) $sports_loss += abs($net);
        }

        $status = ($status_raw == 'true') ? 'Active' : (($status_raw == 'ban') ? 'Banned' : 'Not Active');

        echo "<tr>
            <td>$index</td>
            <td>$uniq_id</td>
            <td>$username</td>
            <td>$balance</td>
            <td>₹" . number_format($deposit, 2) . "</td>
            <td>₹" . number_format($withdraw, 2) . "</td>
            <td>₹" . number_format($total_bet, 2) . "</td>
            <td>₹" . number_format($sports_bet, 2) . "</td>
            <td>₹" . number_format($sports_profit_total, 2) . "</td>
            <td>₹" . number_format($sports_profit, 2) . "</td>
            <td>₹" . number_format($sports_loss, 2) . "</td>
            <td>$mobile</td>
            <td>$ip</td>
            <td>$joined</td>
            <td>$status</td>
        </tr>";
        $index++;
    }

    echo "</table>";
    exit;
}
?>

<!-- HTML content -->
<!DOCTYPE html>
<html>
<head>
    <title>Export Excel</title>
</head>
<body>

<!-- Your table or page content here -->

<!-- Download Excel Button -->
<form method="get" action="">
    <input type="hidden" name="download" value="excel">
    <button type="submit">Download All Data as Excel</button>
</form>

</body>
</html>
