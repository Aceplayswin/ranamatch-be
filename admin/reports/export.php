<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    die("Access Denied");
}

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';

// Capture Filters
$f_username = mysqli_real_escape_string($conn, $_GET['f_username'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_GET['f_date_to'] ?? '');
$f_status = mysqli_real_escape_string($conn, $_GET['f_status'] ?? '');
$f_game = mysqli_real_escape_string($conn, $_GET['f_game'] ?? ''); // Added for sports
$f_type = mysqli_real_escape_string($conn, $_GET['f_type'] ?? ''); // Added for transactions

if ($format === 'json') {
    header('Content-Type: application/json');
}

// Helper function for Excel headers
function setExcelHeaders($filename) {
    global $format;
    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=$filename.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}

// Data accumulator for JSON
$data_array = [];

switch ($type) {
    case 'players':
        setExcelHeaders("players_report_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>ID</th><th>Username</th><th>Full Name</th><th>Mobile</th><th>Balance</th><th>Joined Date</th><th>Status</th></tr>";
        
        $where = "WHERE 1=1";
        if($f_username != "") $where .= " AND (tbl_uniq_id LIKE '%$f_username%' OR tbl_user_name LIKE '%$f_username%')";
        if ($f_date_from != "" && $f_date_to != "") {
            $where .= " AND STR_TO_DATE(tbl_user_joined, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }

        $res = mysqli_query($conn, "SELECT tbl_uniq_id as ID, IFNULL(tbl_user_name, tbl_full_name) as Username, tbl_full_name as Name, tbl_mobile_num as Mobile, tbl_balance as Balance, tbl_user_joined as Joined, tbl_account_status as Status_Raw FROM tblusersdata $where ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            $status = ($row['Status_Raw'] == 'true') ? 'Active' : (($row['Status_Raw'] == 'ban') ? 'Banned' : 'Inactive');
            if ($format === 'excel') echo "<tr><td>{$row['ID']}</td><td>{$row['Username']}</td><td>{$row['Name']}</td><td>{$row['Mobile']}</td><td>{$row['Balance']}</td><td>{$row['Joined']}</td><td>{$status}</td></tr>";
            else {
                $row['Status'] = $status;
                $data_array[] = $row;
            }
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'banned':
        setExcelHeaders("banned_players_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>ID</th><th>Username</th><th>Full Name</th><th>Mobile</th><th>Balance</th><th>Status</th></tr>";
        
        $where = "WHERE tbl_account_status = 'ban'";
        if($f_username != "") $where .= " AND (tbl_uniq_id LIKE '%$f_username%' OR tbl_user_name LIKE '%$f_username%')";
        if ($f_date_from != "" && $f_date_to != "") {
            $where .= " AND STR_TO_DATE(tbl_user_joined, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }

        $res = mysqli_query($conn, "SELECT tbl_uniq_id as ID, IFNULL(tbl_user_name, tbl_full_name) as Username, tbl_full_name as Name, tbl_mobile_num as Mobile, tbl_balance as Balance, 'Banned' as Status FROM tblusersdata $where ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            if ($format === 'excel') echo "<tr><td>{$row['ID']}</td><td>{$row['Username']}</td><td>{$row['Name']}</td><td>{$row['Mobile']}</td><td>{$row['Balance']}</td><td>{$row['Status']}</td></tr>";
            else $data_array[] = $row;
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'deposits':
        setExcelHeaders("deposits_report_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>User ID</th><th>Amount</th><th>Method</th><th>UTR/Details</th><th>Time</th><th>Status</th></tr>";
        
        $where = "WHERE 1=1";
        if($f_status != "" && $f_status != "all") $where .= " AND tbl_request_status = '$f_status'";
        if($f_username != "") $where .= " AND tbl_user_id LIKE '%$f_username%'";
        if ($f_date_from != "" && $f_date_to != "") {
            $where .= " AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }

        $res = mysqli_query($conn, "SELECT tbl_user_id as UserID, tbl_recharge_amount as Amount, tbl_recharge_mode as Method, tbl_recharge_details as UTR, tbl_time_stamp as Time, tbl_request_status as Status FROM tblusersrecharge $where ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            $status = ucfirst($row['Status']);
            if ($format === 'excel') echo "<tr><td>{$row['UserID']}</td><td>{$row['Amount']}</td><td>{$row['Method']}</td><td>{$row['UTR']}</td><td>{$row['Time']}</td><td>{$status}</td></tr>";
            else {
                $row['Status'] = $status;
                $data_array[] = $row;
            }
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'bonus':
        setExcelHeaders("bonus_data_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>Bonus ID</th><th>User Info</th><th>Bonus Name</th><th>Amount</th><th>Wage Req</th><th>Wage Rem</th><th>Status</th><th>Time</th></tr>";
        
        $where = "WHERE 1=1";
        if($f_username != "") $where .= " AND (r.user_id LIKE '%$f_username%' OR u.tbl_user_name LIKE '%$f_username%')";
        if($f_status != "") $where .= " AND r.status = '$f_status'";
        if ($f_date_from != "" && $f_date_to != "") {
            $where .= " AND STR_TO_DATE(r.created_at, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }

        $check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bonus_redemptions'");
        if(mysqli_num_rows($check) > 0) {
            $res = mysqli_query($conn, "SELECT r.bonus_id as BonusID, r.user_id as UserID, u.tbl_user_name as Username, u.tbl_mobile_num as Mobile, r.bonus_amount as Amount, b.name as Description, r.created_at as Time, r.wagering_required as WageringReq, r.status as Status_Raw, u.tbl_requiredplay_balance as WageringRem
                                        FROM tbl_bonus_redemptions r 
                                        LEFT JOIN tbl_bonuses b ON r.bonus_id = b.id 
                                        LEFT JOIN tblusersdata u ON r.user_id = u.tbl_uniq_id
                                        $where ORDER BY r.id DESC");
            while ($row = mysqli_fetch_assoc($res)) {
                $desc = $row['Description'] ?? 'Custom Reward';
                $status = ucfirst($row['Status_Raw'] ?? 'active');
                $rem = ($row['Status_Raw'] == 'active') ? $row['WageringRem'] : 0;
                $user_info = ($row['Username'] ?: 'N/A') . " (#{$row['UserID']}) | " . ($row['Mobile'] ?: 'N/A');
                
                if ($format === 'excel') echo "<tr><td>{$row['BonusID']}</td><td>{$user_info}</td><td>{$desc}</td><td>{$row['Amount']}</td><td>{$row['WageringReq']}</td><td>{$rem}</td><td>{$status}</td><td>{$row['Time']}</td></tr>";
                else {
                    $row['Description'] = $desc;
                    $row['Status'] = $status;
                    $row['WageringRem'] = $rem;
                    $row['Username'] = $row['Username'] ?: 'N/A';
                    $row['Mobile'] = $row['Mobile'] ?: 'N/A';
                    $data_array[] = $row;
                }
            }
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'sports':
        setExcelHeaders("sports_report_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>User ID</th><th>Game</th><th>Cost</th><th>Profit</th><th>Selection</th><th>Time</th></tr>";
        
        $where = "WHERE LOWER(tbl_project_name) IN ('saba sports', 'lucksport', 'lucksportgaming')";
        if($f_username != "") $where .= " AND tbl_user_id LIKE '%$f_username%'";
        if ($f_date_from != "" && $f_date_to != "") {
            $where .= " AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }

        $res = mysqli_query($conn, "SELECT tbl_user_id as UserID, tbl_project_name as Game, tbl_match_cost as Cost, tbl_match_profit as Profit, tbl_selection as Selection, tbl_time_stamp as Time FROM tblmatchplayed $where ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            if ($format === 'excel') echo "<tr><td>{$row['UserID']}</td><td>{$row['Game']}</td><td>{$row['Cost']}</td><td>{$row['Profit']}</td><td>{$row['Selection']}</td><td>{$row['Time']}</td></tr>";
            else $data_array[] = $row;
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'player_based':
        $uid = mysqli_real_escape_string($conn, $_GET['uid'] ?? '');
        setExcelHeaders("player_report_" . $uid . "_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>Field</th><th>Value</th></tr>";
        
        $u_res = mysqli_query($conn, "SELECT tbl_uniq_id, tbl_user_name, tbl_full_name, tbl_mobile_num, tbl_balance, tbl_user_joined, tbl_account_status FROM tblusersdata WHERE tbl_uniq_id = '$uid'");
        if ($u = mysqli_fetch_assoc($u_res)) {
            $status = ($u['tbl_account_status'] == 'true') ? 'Active' : (($u['tbl_account_status'] == 'ban') ? 'Banned' : 'Inactive');
            
            // Financial Stats
            $dep = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) as total FROM tblusersrecharge WHERE tbl_user_id = '$uid' AND tbl_request_status = 'success'"))['total'] ?: 0;
            $wit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tbl_withdraw_amount) as total FROM tbluserswithdraw WHERE tbl_user_id = '$uid' AND tbl_request_status = 'success'"))['total'] ?: 0;
            $bet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tbl_match_cost) as total FROM tblmatchplayed WHERE tbl_user_id = '$uid'"))['total'] ?: 0;
            $win = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tbl_match_profit) as total FROM tblmatchplayed WHERE tbl_user_id = '$uid'"))['total'] ?: 0;

            $rows = [
                ['Player ID', $u['tbl_uniq_id']],
                ['Username', $u['tbl_user_name'] ?: 'N/A'],
                ['Full Name', $u['tbl_full_name']],
                ['Mobile', $u['tbl_mobile_num']],
                ['Account Balance', "INR " . number_format($u['tbl_balance'], 2)],
                ['Account Status', $status],
                ['Joined Date', $u['tbl_user_joined']],
                ['---', '---'],
                ['Total Deposits', "INR " . number_format($dep, 2)],
                ['Total Withdrawals', "INR " . number_format($wit, 2)],
                ['Total Bets Placed', "INR " . number_format($bet, 2)],
                ['Total Winnings', "INR " . number_format($win, 2)],
                ['Net P/L', "INR " . number_format($win - $bet, 2)]
            ];

            foreach ($rows as $r) {
                if ($format === 'excel') echo "<tr><td><b>{$r[0]}</b></td><td>{$r[1]}</td></tr>";
                else $data_array[] = ['Field' => $r[0], 'Value' => $r[1]];
            }
        }
        if ($format === 'excel') echo "</table>";
        break;

    case 'transactions':
        setExcelHeaders("full_transaction_history_" . date('Y-m-d'));
        if ($format === 'excel') echo "<table border='1'><tr style='background:#f4f4f4;'><th>User ID</th><th>Type</th><th>Amount</th><th>Time</th><th>Status</th></tr>";
        
        $where_clauses = [];
        if ($f_username != "") $where_clauses[] = "UserID LIKE '%$f_username%'";
        if ($f_date_from != "" && $f_date_to != "") {
            $where_clauses[] = "STR_TO_DATE(Time, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
        }
        $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

        $sql = "SELECT * FROM (
                    SELECT tbl_user_id as UserID, 'Deposit' as Type, tbl_recharge_amount as Amount, tbl_time_stamp as Time, tbl_request_status as Status FROM tblusersrecharge
                    UNION ALL
                    SELECT tbl_user_id as UserID, 'Withdraw' as Type, tbl_withdraw_amount as Amount, tbl_time_stamp as Time, tbl_request_status as Status FROM tbluserswithdraw
                ) as Transactions 
                $where_sql 
                ORDER BY STR_TO_DATE(Time, '%d-%m-%Y %h:%i %p') DESC";

        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) {
            if ($format === 'excel') echo "<tr><td>{$row['UserID']}</td><td>{$row['Type']}</td><td>{$row['Amount']}</td><td>{$row['Time']}</td><td>{$row['Status']}</td></tr>";
            else $data_array[] = $row;
        }
        if ($format === 'excel') echo "</table>";
        break;

    default:
        die("Invalid Type");
}

if ($format === 'json') {
    echo json_encode($data_array);
}

mysqli_close($conn);
?>
