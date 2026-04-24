<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_pandl") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../index.php');
}

date_default_timezone_set('Asia/Kolkata');
$today_ymd = date('Y-m-d');

$f_from = isset($_POST['f_from']) ? $_POST['f_from'] : $today_ymd;
$f_to = isset($_POST['f_to']) ? $_POST['f_to'] : $today_ymd;

if (isset($_POST['reset'])) {
    $f_from = $today_ymd;
    $f_to = $today_ymd;
}

$from_ts = strtotime($f_from . " 00:00:00");
$to_ts = strtotime($f_to . " 23:59:59");

// Core Analytics Calculation
$anlyt_total_users = 0;
$anlyt_total_recharge = 0;
$anlyt_total_withdraw = 0;
$anlyt_total_balance = 0;
$anlyt_today_active = 0;
$anlyt_number_withdraw = 0;
$anlyt_number_recharge = 0;

// Get users data
$u_sql = "SELECT tbl_user_joined, tbl_balance, tbl_last_active_date FROM tblusersdata";
$u_res = mysqli_query($conn, $u_sql);
$anlyt_total_users = mysqli_num_rows($u_res);
while ($u = mysqli_fetch_assoc($u_res)) {
    // Last Active
    $act_ts = strtotime($u['tbl_last_active_date']);
    if ($act_ts >= $from_ts && $act_ts <= $to_ts)
        $anlyt_today_active++;

    // Joined Date (for balance debt)
    $joined_ts = strtotime($u['tbl_user_joined']);
    if ($joined_ts >= $from_ts && $joined_ts <= $to_ts) {
        if (is_numeric($u['tbl_balance']))
            $anlyt_total_balance += $u['tbl_balance'];
    }
}

// Get recharge data
$r_sql = "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge WHERE tbl_request_status='success'";
$r_res = mysqli_query($conn, $r_sql);
while ($r = mysqli_fetch_assoc($r_res)) {
    $r_ts = strtotime($r['tbl_time_stamp']);
    if ($r_ts >= $from_ts && $r_ts <= $to_ts) {
        $anlyt_number_recharge++;
        $anlyt_total_recharge += $r['tbl_recharge_amount'];
    }
}

// Get withdraw data
$w_sql = "SELECT tbl_withdraw_amount, tbl_time_stamp FROM tbluserswithdraw WHERE tbl_request_status='success'";
$w_res = mysqli_query($conn, $w_sql);
while ($w = mysqli_fetch_assoc($w_res)) {
    $w_ts = strtotime($w['tbl_time_stamp']);
    if ($w_ts >= $from_ts && $w_ts <= $to_ts) {
        $anlyt_number_withdraw++;
        $anlyt_total_withdraw += $w['tbl_withdraw_amount'];
    }
}

$anlyt_p_and_l = $anlyt_total_recharge - $anlyt_total_withdraw;
$anlyt_final_result = $anlyt_total_recharge - ($anlyt_total_withdraw + $anlyt_total_balance);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Game Statistics | P&L Intelligence</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body {
            font-family: 'DM Sans', sans-serif !important;
            background: var(--page-bg) !important;
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .admin-main-content {
            padding: 24px;
            margin-left: 260px;
            min-height: 100vh;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 900px) { .admin-main-content { margin-left: 0; padding: 15px; } }
        
        .glass-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .glass-card:hover { border-color: rgba(59, 130, 246, 0.3); transform: translateY(-2px); }

        .stat-mini { display: flex; align-items: center; gap: 15px; }
        .icon-box {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .label-text { font-size: 11px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }
        .value-text { font-size: 18px; font-weight: 800; color: var(--text-main); }

        .filter-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid var(--border-dim); padding-bottom: 20px;
        }
        .date-inp {
            background: var(--input-bg) !important; border: 1px solid var(--input-border) !important;
            border-radius: 10px !important; color: #fff !important; height: 36px; padding: 0 12px;
            font-size: 12px; font-weight: 600; outline: none; width: 140px;
        }
        .btn-action {
            height: 36px; border-radius: 10px; font-weight: 700; font-size: 12px;
            padding: 0 18px; border: none; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-search { background: var(--accent-blue); color: #fff; }
        .btn-search:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3); }

        .p-badge { font-size: 10px; font-weight: 900; padding: 3px 10px; border-radius: 6px; text-transform: uppercase; }
        .bg-profit { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .bg-loss { background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2); }

        .section-tag { font-size: 12px; font-weight: 800; color: var(--accent-blue); text-transform: uppercase; margin-bottom: 20px; display: block; letter-spacing: 1px; }
        
        .audit-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid var(--border-dim);
        }
        .audit-row:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">
            
            <div class="filter-bar">
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; margin: 0; color: var(--text-main);">Game Statistics</h1>
                    <p style="font-size: 13px; color: var(--text-dim); margin: 0;">Financial Audit: <?php echo date('d M', $from_ts); ?> - <?php echo date('d M Y', $to_ts); ?></p>
                </div>
                <form action="" method="POST" class="d-flex gap-2 align-items-center">
                    <div class="d-flex align-items-center gap-2 bg-dark-subtle p-1 rounded-3 px-2 border border-secondary-subtle" style="background: rgba(255,255,255,0.03);">
                        <span style="font-size: 10px; font-weight: 800; color: var(--text-dim);">FROM</span>
                        <input type="date" name="f_from" value="<?php echo $f_from; ?>" class="date-inp">
                        <span style="font-size: 10px; font-weight: 800; color: var(--text-dim);">TO</span>
                        <input type="date" name="f_to" value="<?php echo $f_to; ?>" class="date-inp">
                    </div>
                    <button type="submit" class="btn-action btn-search"><i class='bx bx-filter-alt'></i> Audit Range</button>
                    <button type="submit" name="reset" class="btn-action" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim);">Today</button>
                </form>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="glass-card stat-mini">
                        <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class='bx bx-group'></i></div>
                        <div>
                            <div class="label-text">Total Players</div>
                            <div class="value-text"><?php echo number_format($anlyt_total_users); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card stat-mini">
                        <div class="icon-box" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class='bx bx-user-check'></i></div>
                        <div>
                            <div class="label-text">Active in Range</div>
                            <div class="value-text"><?php echo number_format($anlyt_today_active); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card stat-mini">
                        <div class="icon-box" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"><i class='bx bx-plus-circle'></i></div>
                        <div>
                            <div class="label-text">Total Recharges (<?php echo $anlyt_number_recharge; ?>)</div>
                            <div class="value-text">INR <?php echo number_format($anlyt_total_recharge, 2); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card stat-mini">
                        <div class="icon-box" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e;"><i class='bx bx-minus-circle'></i></div>
                        <div>
                            <div class="label-text">Total Withdrawals (<?php echo $anlyt_number_withdraw; ?>)</div>
                            <div class="value-text">INR <?php echo number_format($anlyt_total_withdraw, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-12">
                    <div class="glass-card h-100">
                        <span class="section-tag">Audit Breakdown Index</span>
                        
                        <div class="audit-row">
                            <div class="label-text">New User Balance Debt (Joined in Range)</div>
                            <div class="value-text" style="color: var(--text-dim);">INR <?php echo number_format($anlyt_total_balance, 2); ?></div>
                        </div>

                        <div class="audit-row">
                            <div class="label-text">Net Profit (Recharge - Withdrawal)</div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="value-text" style="color: <?php echo $anlyt_p_and_l >= 0 ? '#10b981' : '#f43f5e'; ?>">
                                    INR <?php echo number_format($anlyt_p_and_l, 2); ?>
                                </div>
                                <span class="p-badge <?php echo $anlyt_p_and_l >= 0 ? 'bg-profit' : 'bg-loss'; ?>">
                                    <?php echo $anlyt_p_and_l >= 0 ? 'Profit' : 'Loss'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 p-4 rounded-4" style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-dim);">
                            <div class="label-text mb-2">Final Performance Index</div>
                            <div class="value-text" style="font-size: 32px; color: <?php echo $anlyt_final_result >= 0 ? '#10b981' : '#f43f5e'; ?>">
                                INR <?php echo number_format($anlyt_final_result, 2); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 10px; opacity: 0.7;">
                                <i class='bx bx-info-circle'></i> Formula: Total Recharge - (Total Withdraw + New User Debt) for the selected period.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>