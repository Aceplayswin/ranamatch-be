<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); // Disable PHP's automatic session cache headers

define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_users_data")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

// Adjust Balance Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_balance') {
    header('Content-Type: application/json');
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id'] ?? '');
    $type = mysqli_real_escape_string($conn, $_POST['type'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $remark = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');
    
    if (empty($user_id) || $amount <= 0 || !in_array($type, ['add', 'subtract'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters provided.']);
        exit;
    }
    
    $user_check = mysqli_query($conn, "SELECT tbl_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id'");
    if (mysqli_num_rows($user_check) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }
    
    $user_data = mysqli_fetch_assoc($user_check);
    $current_balance = (float)$user_data['tbl_balance'];
    
    if ($type === 'subtract' && $current_balance < $amount) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient user balance.']);
        exit;
    }
    
    $uniq_txn_id = "MAN-" . strtoupper(bin2hex(random_bytes(4)));
    $time_stamp = date('d-m-Y h:i A');
    
    if ($type === 'add') {
        $insert_sql = "INSERT INTO tblusersrecharge 
                       (tbl_user_id, tbl_recharge_amount, tbl_recharge_mode, tbl_recharge_details, tbl_request_status, tbl_remark, tbl_time_stamp, tbl_uniq_id) 
                       VALUES 
                       ('$user_id', '$amount', 'Admin Adjustment', 'Credit by Admin', 'success', '$remark', '$time_stamp', '$uniq_txn_id')";
        $update_balance_sql = "UPDATE tblusersdata 
                               SET tbl_balance = tbl_balance + $amount 
                               WHERE tbl_uniq_id = '$user_id'";
    } else {
        $insert_sql = "INSERT INTO tbluserswithdraw 
                       (tbl_user_id, tbl_withdraw_request, tbl_withdraw_amount, tbl_withdraw_details, tbl_request_status, tbl_extra_details, tbl_remark, tbl_time_stamp, tbl_uniq_id) 
                       VALUES 
                       ('$user_id', '$amount', '$amount', 'Admin Adjustment', 'success', 'None', '$remark', '$time_stamp', '$uniq_txn_id')";
        $update_balance_sql = "UPDATE tblusersdata 
                               SET tbl_balance = tbl_balance - $amount 
                               WHERE tbl_uniq_id = '$user_id'";
    }
    
    if (mysqli_query($conn, $insert_sql) && mysqli_query($conn, $update_balance_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Balance adjusted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . mysqli_error($conn)]);
    }
    exit;
}

$f_username = mysqli_real_escape_string($conn, $_POST['f_username'] ?? $_GET['f_username'] ?? '');
$f_mobile = mysqli_real_escape_string($conn, $_POST['f_mobile'] ?? $_GET['f_mobile'] ?? '');
$f_userid = mysqli_real_escape_string($conn, $_POST['f_userid'] ?? $_GET['f_userid'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');

$content = 15;
$page_num = (int)(isset($_GET['page_num']) ? $_GET['page_num'] : 1);
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $content;

$newRequestStatus = isset($_POST['order_type']) ? $_POST['order_type'] : (isset($_GET['order_type']) ? $_GET['order_type'] : "true");

// Handle download request (Excel export)
if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=all_user_data.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr style='background: #f4f4f4;'>
        <th>No</th><th>ID</th><th>Username</th><th>Balance</th><th>Total Deposit</th>
        <th>Total Bonus</th><th>Total Withdraw</th><th>Total Bet Amount</th><th>Total Sports Bet</th>
        <th>Sports P&L</th><th>Sports Profit</th><th>Sports Loss</th>
        <th>Mobile</th><th>User IP</th><th>Date & Time</th><th>Status</th>
    </tr>";

    $index = 1;
    $query = "SELECT * FROM tblusersdata ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $uniq_id = $row['tbl_uniq_id'];
        $username = ($row['tbl_user_name'] ?? $row['tbl_full_name']) ?: "N/A";
        $balance = $row['tbl_balance'];
        $mobile = $row['tbl_mobile_num'];
        $joined = $row['tbl_user_joined'];
        $status_raw = $row['tbl_account_status'];

        $ip = 'N/A';
        $ip_res = mysqli_query($conn, "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id='$uniq_id' ORDER BY id ASC LIMIT 1");
        if ($ip_row = mysqli_fetch_assoc($ip_res)) $ip = $ip_row['tbl_device_ip'];

        $bonus_total = 0;
        $bt_q = mysqli_query($conn, "SELECT SUM(bonus_amount) AS total FROM tbl_bonus_redemptions WHERE user_id='$uniq_id'");
        if ($bt_r = mysqli_fetch_assoc($bt_q)) $bonus_total = $bt_r['total'] ?? 0;

        $deposit = 0;
        $d_q = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) AS total FROM tblusersrecharge WHERE tbl_user_id='$uniq_id' AND tbl_request_status='success'");
        if ($d_r = mysqli_fetch_assoc($d_q)) $deposit = $d_r['total'] ?? 0;

        $withdraw = 0;
        $w_q = mysqli_query($conn, "SELECT SUM(tbl_withdraw_amount) AS total FROM tbluserswithdraw WHERE tbl_user_id='$uniq_id' AND tbl_request_status='success'");
        if ($w_r = mysqli_fetch_assoc($w_q)) $withdraw = $w_r['total'] ?? 0;

        $total_bet = 0;
        $b_q = mysqli_query($conn, "SELECT SUM(tbl_match_cost) AS total FROM tblmatchplayed WHERE tbl_user_id='$uniq_id'");
        if ($b_r = mysqli_fetch_assoc($b_q)) $total_bet = $b_r['total'] ?? 0;

        $s_bet = 0; $s_p_total = 0; $s_p = 0; $s_l = 0;
        $s_q = mysqli_query($conn, "SELECT tbl_match_cost, tbl_match_profit FROM tblmatchplayed WHERE tbl_user_id='$uniq_id' AND LOWER(tbl_project_name) IN ('saba sports', 'lucksport', 'lucksportgaming')");
        while ($s_r = mysqli_fetch_assoc($s_q)) {
            $c = $s_r['tbl_match_cost']; $p = $s_r['tbl_match_profit'];
            $n = $p - $c; $s_bet += $c; $s_p_total += $p;
            if ($n > 0) $s_p += $n; elseif ($n < 0) $s_l += abs($n);
        }

        $st = ($status_raw == 'true') ? 'Active' : (($status_raw == 'ban') ? 'Banned' : 'Not Active');
        echo "<tr>
            <td>$index</td><td>$uniq_id</td><td>$username</td><td>$balance</td>
            <td>₹".number_format($deposit,2)."</td><td>₹".number_format($bonus_total,2)."</td><td>₹".number_format($withdraw,2)."</td>
            <td>₹".number_format($total_bet,2)."</td><td>₹".number_format($s_bet,2)."</td>
            <td>₹".number_format($s_p_total,2)."</td><td>₹".number_format($s_p,2)."</td>
            <td>₹".number_format($s_l,2)."</td><td>$mobile</td><td>$ip</td>
            <td>$joined</td><td>$st</td>
        </tr>";
        $index++;
    }
    echo "</table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Users Data</title>
    <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
<style>
/* Page-specific variables */
<?php include "../components/theme-variables.php"; ?>
.advanced-filter-card {
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
    gap: 8px; 
    background: rgba(255, 255, 255, 0.02); 
    padding: 8px 12px; 
    border-radius: 8px; 
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.filter-input-group {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.filter-label {
    font-size: 7.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-dim);
    margin-left: 2px;
    opacity: 0.8;
}

.filter-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.filter-input-wrapper i {
    position: absolute;
    left: 10px;
    font-size: 13px;
    color: var(--accent-blue);
    opacity: 0.6;
}

.filter-inp {
    width: 100%;
    height: 32px;
    background: rgba(0, 0, 0, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 6px !important;
    padding: 0 8px 0 30px !important;
    color: #fff !important;
    font-size: 11px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease;
}

.filter-inp:focus {
    background: rgba(0, 0, 0, 0.25) !important;
    border-color: var(--accent-blue) !important;
    box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.05) !important;
    outline: none;
}

.filter-action-area {
    display: flex;
    align-items: flex-end;
}

.btn-filter-submit {
    width: 100%;
    height: 32px;
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    border: none;
    border-radius: 6px;
    color: #fff;
    font-weight: 800;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-filter-submit:hover {
    filter: brightness(1.2);
}

.btn-filter-submit i {
    font-size: 14px;
}
</style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div>
                    <span class="dash-breadcrumb">User Management > Users Data</span>
                    <h1 class="dash-title">Users Data</h1>
                </div>
            </div>
            <div class="dash-header-right">
                <button class="btn-modern btn-outline-modern filter-btn-toggle" type="button">
                    <i class='bx bx-filter-alt'></i> Filter Status
                </button>
                <button class="btn-modern btn-outline-modern" type="button" onclick="window.location.href='?download=excel'">
                    <i class='bx bx-cloud-download'></i> Bulk Export
                </button>
                <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'">
                    <i class='bx bx-refresh'></i> Refresh Data
                </button>
            </div>
        </div>

            
            <div class="search-area">

                <div class="filter-options <?php if($newRequestStatus != "true") echo 'show'; ?>">
                    <form method="POST" id="filter-form">
                        <label class="custom-check">
                            <input type="checkbox" name="order_type" value="true" onchange="this.form.submit()" <?php if($newRequestStatus=="true") echo "checked"; ?>>
                            Show Active
                        </label>
                        <label class="custom-check">
                            <input type="checkbox" name="order_type" value="ban" onchange="this.form.submit()" <?php if($newRequestStatus=="ban") echo "checked"; ?>>
                            Show Banned
                        </label>
                        <label class="custom-check">
                            <input type="checkbox" name="order_type" value="false" onchange="this.form.submit()" <?php if($newRequestStatus=="false") echo "checked"; ?>>
                            Show In-Active
                        </label>
                    </form>
                </div>

                <!-- Advanced Filters Dashboard -->
                <form method="POST" class="mt-3 advanced-filter-card">
                    <div class="filter-input-group">
                        <label class="filter-label">Username</label>
                        <div class="filter-input-wrapper">
                            <i class='bx bx-user'></i>
                            <input type="text" name="f_username" value="<?php echo $f_username; ?>" class="filter-inp" placeholder="Search Username...">
                        </div>
                    </div>
                    
                    <div class="filter-input-group">
                        <label class="filter-label">Mobile Num</label>
                        <div class="filter-input-wrapper">
                            <i class='bx bx-phone'></i>
                            <input type="text" name="f_mobile" value="<?php echo $f_mobile; ?>" class="filter-inp" placeholder="Search Mobile...">
                        </div>
                    </div>

                    <div class="filter-input-group">
                        <label class="filter-label">User ID</label>
                        <div class="filter-input-wrapper">
                            <i class='bx bx-fingerprint'></i>
                            <input type="text" name="f_userid" value="<?php echo $f_userid; ?>" class="filter-inp" placeholder="Search ID...">
                        </div>
                    </div>

                    <div class="filter-input-group">
                        <label class="filter-label">From Date</label>
                        <div class="filter-input-wrapper">
                            <i class='bx bx-calendar'></i>
                            <input type="date" name="f_date_from" value="<?php echo $f_date_from; ?>" class="filter-inp">
                        </div>
                    </div>

                    <div class="filter-input-group">
                        <label class="filter-label">To Date</label>
                        <div class="filter-input-wrapper">
                            <i class='bx bx-calendar-event'></i>
                            <input type="date" name="f_date_to" value="<?php echo $f_date_to; ?>" class="filter-inp">
                        </div>
                    </div>

                    <div class="filter-action-area">
                        <button type="submit" class="btn-filter-submit">
                            <i class='bx bx-search-alt'></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <div class="record-section" style="background: rgba(255,255,255,0.01); border-radius: 12px; padding: 8px;">
                <div class="w-100 ovflw-x-scroll hide-native-scrollbar">
                    <table id="table" class="r-table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>User Info (Username)</th>
                                <th>Balance</th>
                                <th style="text-align: center;">Actions</th>
                                <th>Deposits</th>
                                <th>Bonus</th>
                                <th>Withdraws</th>
                                <th>Total Bet</th>
                                <th>Sports Bet</th>
                                <th>Sports P&L</th>
                                <th>Mobile</th>
                                <th>IP Addr</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $indexVal = 1;
                            $grand_sports_total_bet = 0; $grand_sports_total_profit = 0;
                            $grand_sports_p_amt = 0; $grand_sports_l_amt = 0;

                            $where_clauses = ["tbl_account_status='{$newRequestStatus}'"];
                            if($f_username != "") $where_clauses[] = "(tbl_user_name LIKE '%$f_username%' OR tbl_full_name LIKE '%$f_username%')";
                            if($f_mobile != "") $where_clauses[] = "tbl_mobile_num LIKE '%$f_mobile%'";
                            if($f_userid != "") $where_clauses[] = "tbl_uniq_id LIKE '%$f_userid%'";
                            
                            if($f_date_from != "" && $f_date_to != ""){
                                $where_clauses[] = "STR_TO_DATE(LEFT(tbl_user_joined, 10), '%d-%m-%Y') BETWEEN '$f_date_from' AND '$f_date_to'";
                            } elseif($f_date_from != "") {
                                $where_clauses[] = "STR_TO_DATE(LEFT(tbl_user_joined, 10), '%d-%m-%Y') >= '$f_date_from'";
                            } elseif($f_date_to != "") {
                                $where_clauses[] = "STR_TO_DATE(LEFT(tbl_user_joined, 10), '%d-%m-%Y') <= '$f_date_to'";
                            }
                            
                            $where_str = implode(" AND ", $where_clauses);
                            $sql = "SELECT * FROM tblusersdata WHERE $where_str ORDER BY id DESC LIMIT {$offset},{$content}";
                    
                            $res = mysqli_query($conn, $sql);
                            $users_on_page = [];
                            $page_bal = 0; $page_dep = 0; $page_wit = 0;
                            
                            if (mysqli_num_rows($res) > 0){
                                while ($row = mysqli_fetch_assoc($res)){
                                    $uid_row = $row['tbl_uniq_id'];
                                    
                                    // Row totals
                                    $dq = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) AS total FROM tblusersrecharge WHERE tbl_user_id='{$uid_row}' AND tbl_request_status='success'");
                                    $d = ($dr = mysqli_fetch_assoc($dq)) ? ($dr['total'] ?? 0) : 0;
                                    
                                    $wq = mysqli_query($conn, "SELECT SUM(tbl_withdraw_amount) AS total FROM tbluserswithdraw WHERE tbl_user_id='{$uid_row}' AND tbl_request_status='success'");
                                    $w = ($wr = mysqli_fetch_assoc($wq)) ? ($wr['total'] ?? 0) : 0;
                                    
                                    $row['calc_dep'] = $d;
                                    $row['calc_wit'] = $w;
                                    
                                    $page_bal += $row['tbl_balance'];
                                    $page_dep += $d;
                                    $page_wit += $w;
                                    $users_on_page[] = $row;
                                }
                            }
                            ?>

                            <?php
                            if (count($users_on_page) > 0){
                                foreach ($users_on_page as $row){
                                    $uid = $row['tbl_uniq_id'];
                                    $st_raw = $row['tbl_account_status'];
                                    $bal = $row['tbl_balance'];
                                    $uname = ($row['tbl_user_name'] ?? $row['tbl_full_name']) ?: "N/A";
                                    $dep = $row['calc_dep'];
                                    $wit = $row['calc_wit'];

                                    // Recharge
                                    $d_q = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) AS total FROM tblusersrecharge WHERE tbl_user_id='{$uid}' AND tbl_request_status='success'");
                                    $dep = ($d_r = mysqli_fetch_assoc($d_q)) ? ($d_r['total'] ?? 0) : 0;

                                    // Withdraw
                                    $w_q = mysqli_query($conn, "SELECT SUM(tbl_withdraw_amount) AS total FROM tbluserswithdraw WHERE tbl_user_id='{$uid}' AND tbl_request_status='success'");
                                    $wit = ($w_r = mysqli_fetch_assoc($w_q)) ? ($w_r['total'] ?? 0) : 0;

                                    // Total matches
                                    $b_q = mysqli_query($conn, "SELECT SUM(tbl_match_cost) AS total FROM tblmatchplayed WHERE tbl_user_id='{$uid}'");
                                    $t_bet = ($b_r = mysqli_fetch_assoc($b_q)) ? ($b_r['total'] ?? 0) : 0;

                                    // Sports specific
                                    $s_bet = 0; $s_p_tot = 0; $s_p_amt = 0; $s_l_amt = 0;
                                    $s_q = mysqli_query($conn, "SELECT tbl_match_cost, tbl_match_profit FROM tblmatchplayed WHERE tbl_user_id='{$uid}' AND LOWER(tbl_project_name) IN ('saba sports', 'lucksport', 'lucksportgaming')");
                                    while ($s_r = mysqli_fetch_assoc($s_q)) {
                                        $c = $s_r['tbl_match_cost']; $p = $s_r['tbl_match_profit']; $n = $p - $c;
                                        $s_bet += $c; $s_p_tot += $p;
                                        if ($n > 0) $s_p_amt += $n; elseif ($n < 0) $s_l_amt += abs($n);
                                    }

                                    $grand_sports_total_bet += $s_bet; $grand_sports_total_profit += $s_p_tot;
                                    $grand_sports_p_amt += $s_p_amt; $grand_sports_l_amt += $s_l_amt;

                                    $ip = "N/A";
                                    $i_q = mysqli_query($conn, "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id='{$uid}' ORDER BY id ASC LIMIT 1");
                                    if ($i_r = mysqli_fetch_assoc($i_q)) $ip = $i_r['tbl_device_ip'];

                                    // Bonus Stats
                                    $bnt_q = mysqli_query($conn, "SELECT SUM(bonus_amount) AS total FROM tbl_bonus_redemptions WHERE user_id='{$uid}'");
                                    $bns_total = ($bnt_r = mysqli_fetch_assoc($bnt_q)) ? ($bnt_r['total'] ?? 0) : 0;
                                    $active_bonus_id = (int)$row['tbl_active_bonus_id'];
                                    $turnover_req = (float)$row['tbl_requiredplay_balance'];
                                    ?>
                                    <tr onclick="window.location.href='manager.php?id=<?php echo $uid; ?>'" style="cursor: pointer;">
                                        <td style="font-size: 11px; color: var(--text-dim);"><?php echo $indexVal + $offset; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($uname); ?></div>
                                                <div style="font-size: 10px; color: var(--accent-blue); opacity: 0.8;">(<?php echo htmlspecialchars($uid); ?>)</div>
                                            </div>
                                        </td>
                                        <td style="font-weight: 800; color: var(--text-main);">₹<?php echo number_format($bal, 2); ?></td>
                                        <td style="text-align: center;">
                                            <button class="btn-modern btn-primary-modern py-1 px-2 text-xs" style="height: 28px; font-size: 11px;" onclick="event.stopPropagation(); openAdjustBalanceModal('<?php echo htmlspecialchars($uid); ?>', '<?php echo htmlspecialchars($uname); ?>', <?php echo (float)$bal; ?>)">
                                                <i class='bx bx-wallet'></i> ±
                                            </button>
                                        </td>
                                        <td style="color: var(--accent-emerald);">₹<?php echo number_format($dep, 2); ?></td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--accent-amber);">₹<?php echo number_format($bns_total, 2); ?></div>
                                            <?php if($active_bonus_id > 0): ?>
                                                <div style="font-size: 9px; font-weight: 700; color: #ffad33; text-transform: uppercase;">Wagering: ₹<?php echo number_format($turnover_req, 2); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--accent-rose);">₹<?php echo number_format($wit, 2); ?></td>
                                        <td style="font-weight: 600;">₹<?php echo number_format($t_bet, 2); ?></td>
                                        <td style="font-weight: 600; color: var(--accent-amber);">₹<?php echo number_format($s_bet, 2); ?></td>
                                        <td style="font-weight: 700; color: <?php echo ($s_p_tot >= $s_bet) ? 'var(--accent-emerald)' : 'var(--accent-rose)'; ?>">
                                            ₹<?php echo number_format($s_p_tot, 2); ?>
                                        </td>
                                        <td style="font-size: 11px; font-weight: 600;"><?php echo $row['tbl_mobile_num']; ?></td>
                                        <td style="font-family: monospace; font-size: 11px; color: var(--text-dim);"><?php echo $ip; ?></td>
                                        <td style="font-size: 11px; white-space: nowrap;"><?php echo htmlspecialchars($row['tbl_user_joined']); ?></td>
                                        <td>
                                            <?php if($st_raw == "true"): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php elseif($st_raw == "ban"): ?>
                                                <span class="status-badge status-banned">Banned</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">In-Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php $indexVal++; 
                                }
                            } else {
                                echo "<tr><td colspan='14' class='text-center py-5 text-muted'>No user found matching criteria</td></tr>";
                            } ?>
                        </tbody>
                        <?php if ($indexVal > 1) { ?>
                        <tfoot style="background: var(--table-header-bg); border-top: 1px solid var(--border-dim);">
                            <tr style="font-weight: 700;">
                                <td colspan="14">
                                    <div style="display: flex; align-items: center; gap: 30px; padding: 6px 0;">
                                        <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Page Totals:</div>
                                        <div style="font-size: 13px; color: var(--text-main);">Balance: ₹<?php echo number_format($page_bal, 2); ?></div>
                                        <div style="font-size: 13px; color: var(--accent-emerald);">Deposits: + ₹<?php echo number_format($page_dep, 2); ?></div>
                                        <div style="font-size: 13px; color: var(--accent-rose);">Withdraws: - ₹<?php echo number_format($page_wit, 2); ?></div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                        <?php } ?>
                    </table>
                </div>

                <?php
                $c_sql = "SELECT COUNT(*) as total FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}'";
                $c_res = mysqli_query($conn, $c_sql);
                $total_recs = ($c_row = mysqli_fetch_assoc($c_res)) ? (int)$c_row['total'] : 0;
                $total_p = ceil($total_recs / $content);

                if ($total_recs > 0) {
                ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div style="font-size: 12px; color: var(--text-dim); font-weight: 600;">
                        Showing page <?php echo $page_num; ?> of <?php echo $total_p; ?> (<?php echo $total_recs; ?> records)
                    </div>
                    <div class="pagination-container">
                        <a href="?page_num=<?php echo max(1, $page_num - 1); ?>&order_type=<?php echo $newRequestStatus; ?>" class="page-btn <?php if ($page_num <= 1) echo 'disabled'; ?>">
                            <i class='bx bx-chevron-left'></i>
                        </a>
                        <?php
                        $sp = max(1, $page_num - 2); $ep = min($total_p, $page_num + 2);
                        for($i=$sp; $i<=$ep; $i++){
                            $act = ($page_num == $i) ? 'active' : '';
                            echo "<a href='?page_num={$i}&order_type={$newRequestStatus}' class='page-btn {$act}'>{$i}</a>";
                        }
                        ?>
                        <a href="?page_num=<?php echo min($total_p, $page_num + 1); ?>&order_type=<?php echo $newRequestStatus; ?>" class="page-btn <?php if ($page_num >= $total_p) echo 'disabled'; ?>">
                            <i class='bx bx-chevron-right'></i>
                        </a>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

<!-- Adjust Balance Modal -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 16px; color: var(--text-main); font-family: var(--font-body);">
            <div class="modal-header border-0 pb-0" style="padding: 20px 24px;">
                <h5 class="modal-title" id="adjustBalanceModalLabel" style="font-weight: 700; font-size: 16px;">Adjust User Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 12px;"></button>
            </div>
            <div class="modal-body" style="padding: 20px 24px;">
                <form id="adjustBalanceForm">
                    <input type="hidden" id="adjust_user_id" name="user_id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);">Player Info</label>
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim);">
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-size: 12px; color: var(--text-dim);">Username:</span>
                                <span id="adjust_username" style="font-size: 12px; font-weight: 700;">N/A</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-size: 12px; color: var(--text-dim);">Player ID:</span>
                                <span id="adjust_userid_display" style="font-size: 12px; font-weight: 700; color: var(--accent-blue);">N/A</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span style="font-size: 12px; color: var(--text-dim);">Current Balance:</span>
                                <span id="adjust_balance_display" style="font-size: 13px; font-weight: 800; color: var(--accent-emerald);">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);">Action Type</label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="adjust_type" id="type_add" value="add" checked autocomplete="off">
                            <label class="btn btn-outline-success flex-grow-1 py-2" for="type_add" style="font-weight: 700; font-size: 13px; border-radius: 10px;" onclick="document.getElementById('type_add').checked = true;">
                                <i class='bx bx-plus-circle'></i> Add Money (+)
                            </label>

                            <input type="radio" class="btn-check" name="adjust_type" id="type_subtract" value="subtract" autocomplete="off">
                            <label class="btn btn-outline-danger flex-grow-1 py-2" for="type_subtract" style="font-weight: 700; font-size: 13px; border-radius: 10px;" onclick="document.getElementById('type_subtract').checked = true;">
                                <i class='bx bx-minus-circle'></i> Subtract Money (-)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adjust_amount" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);">Amount (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border-dim); color: var(--text-dim); border-radius: 10px 0 0 10px;">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="adjust_amount" required placeholder="0.00" style="background: rgba(0,0,0,0.1); border: 1px solid var(--border-dim); color: var(--text-main); font-weight: 700; border-radius: 0 10px 10px 0; height: 42px;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adjust_remark" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);">Comment / Remark</label>
                        <textarea class="form-control" id="adjust_remark" required rows="3" placeholder="Explain why you are adjusting the balance..." style="background: rgba(0,0,0,0.1); border: 1px solid var(--border-dim); color: var(--text-main); border-radius: 10px; resize: none; font-size: 13px;"></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" id="btnSubmitAdjust" class="btn-modern btn-primary-modern py-2.5 w-100 justify-content-center" style="border-radius: 10px; height: 44px; font-weight: 700;">
                            Confirm Balance Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../script.js?v=05"></script>
<script>
    document.querySelector(".filter-btn-toggle").addEventListener("click", () => {
        document.querySelector(".filter-options").classList.toggle("show");
    });
    
    // Ensure only one filter checkbox is checked at a time (radio behavior)
    const filters = document.querySelectorAll('.custom-check input');
    filters.forEach(f => {
        f.addEventListener('click', function() {
            filters.forEach(other => { if (other !== this) other.checked = false; });
        });
    });

    let adjustModal = null;

    function openAdjustBalanceModal(uid, username, balance) {
        document.getElementById('adjust_user_id').value = uid;
        document.getElementById('adjust_username').textContent = username;
        document.getElementById('adjust_userid_display').textContent = uid;
        document.getElementById('adjust_balance_display').textContent = '₹' + parseFloat(balance).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        document.getElementById('adjust_amount').value = '';
        document.getElementById('adjust_remark').value = '';
        
        // Select Add by default
        document.getElementById('type_add').checked = true;
        
        if (!adjustModal) {
            adjustModal = new bootstrap.Modal(document.getElementById('adjustBalanceModal'));
        }
        adjustModal.show();
    }

    document.getElementById('adjustBalanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const uid = document.getElementById('adjust_user_id').value;
        const type = document.querySelector('input[name="adjust_type"]:checked').value;
        const amount = parseFloat(document.getElementById('adjust_amount').value);
        const remark = document.getElementById('adjust_remark').value;
        
        if (!uid || isNaN(amount) || amount <= 0 || !remark.trim()) {
            alert('Please fill in all fields correctly.');
            return;
        }
        
        const btnSubmit = document.getElementById('btnSubmitAdjust');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = "<span class='spinner-border spinner-border-sm' role='status' aria-hidden='true'></span> Processing...";
        
        const formData = new FormData();
        formData.append('action', 'adjust_balance');
        formData.append('user_id', uid);
        formData.append('type', type);
        formData.append('amount', amount);
        formData.append('remark', remark);
        
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.textContent = "Confirm Balance Adjustment";
            
            if (data.status === 'success') {
                alert(data.message);
                if (adjustModal) adjustModal.hide();
                window.location.reload();
            } else {
                alert(data.message || 'Failed to adjust balance.');
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.textContent = "Confirm Balance Adjustment";
            console.error(err);
            alert('An error occurred. Please try again.');
        });
    });
</script>
</body>
</html>
            if (data.status === 'success') {
                alert(data.message);
                if (addUserModal) addUserModal.hide();
                window.location.reload();
            } else {
                alert(data.message || 'Failed to add user.');
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.textContent = "Create User";
            console.error(err);
            alert('Error: ' + err.message);
        });
    });
</script>
</body>
</html>
