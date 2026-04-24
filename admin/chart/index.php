<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); // Disable PHP's automatic session cache headers

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

// Get data for Recharge vs Withdrawal chart (last 7 days)
$recharge_data = [];
$withdraw_data = [];
$date_labels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_labels[] = date('d M', strtotime("-$i days"));

    // Format date for SQL query
    $start_date = $date . " 00:00:00";
    $end_date = $date . " 23:59:59";

    $date_formatted = date('d-m-Y', strtotime($date));

    // Get recharge data
    $recharge_sql = "SELECT SUM(tbl_recharge_amount) as total_recharge 
                    FROM tblusersrecharge 
                    WHERE tbl_request_status = 'success' 
                    AND LEFT(tbl_time_stamp, 10) = '$date_formatted'";

    $recharge_result = mysqli_query($conn, $recharge_sql);
    $recharge_row = mysqli_fetch_assoc($recharge_result);
    $recharge_data[] = $recharge_row['total_recharge'] ? (float) $recharge_row['total_recharge'] : 0;

    // Get withdrawal data
    $withdraw_sql = "SELECT SUM(tbl_withdraw_amount) as total_withdraw 
                    FROM tbluserswithdraw 
                    WHERE tbl_request_status = 'success' 
                    AND LEFT(tbl_time_stamp, 10) = '$date_formatted'";

    $withdraw_result = mysqli_query($conn, $withdraw_sql);
    $withdraw_row = mysqli_fetch_assoc($withdraw_result);
    $withdraw_data[] = $withdraw_row['total_withdraw'] ? (float) $withdraw_row['total_withdraw'] : 0;
}

// Get data for Profit/Loss Analysis
$profit_data = [];
$loss_data = [];
$cost_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    // Format date for SQL query
    $start_date = $date . " 00:00:00";
    $end_date = $date . " 23:59:59";

    $date_formatted = date('d-m-Y', strtotime($date));

    $profit_sql = "SELECT 
                    SUM(CASE WHEN LOWER(tbl_match_status) = 'profit' THEN tbl_match_profit ELSE 0 END) as total_profit,
                    SUM(CASE WHEN LOWER(tbl_match_status) = 'loss' THEN tbl_match_cost ELSE 0 END) as total_loss,
                    SUM(tbl_match_cost) as total_cost
                  FROM tblmatchplayed 
                  WHERE LEFT(tbl_time_stamp, 10) = '$date_formatted'";

    $profit_result = mysqli_query($conn, $profit_sql);
    $profit_row = mysqli_fetch_assoc($profit_result);

    $profit_data[] = $profit_row['total_profit'] ? (float) $profit_row['total_profit'] : 0;
    $loss_data[] = $profit_row['total_loss'] ? (float) $profit_row['total_loss'] : 0;
    $cost_data[] = $profit_row['total_cost'] ? (float) $profit_row['total_cost'] : 0;
}

$last_7_days = [];
for ($i = 0; $i < 7; $i++) {
    $last_7_days[] = date('d-m-Y', strtotime("-$i days"));
}
$date_range_list = "'" . implode("','", $last_7_days) . "'";

// Fetch top users with deposits (last 7 days)
$deposit_sql = "SELECT u.tbl_full_name, u.tbl_user_name, r.tbl_user_id, r.tbl_recharge_amount, r.tbl_time_stamp
                FROM tblusersrecharge r
                JOIN tblusersdata u ON r.tbl_user_id = u.tbl_uniq_id
                WHERE r.tbl_request_status = 'success' 
                AND LEFT(r.tbl_time_stamp, 10) IN ($date_range_list)
                ORDER BY r.tbl_recharge_amount DESC LIMIT 10";

$deposit_result = mysqli_query($conn, $deposit_sql);

// Fetch top users with withdrawals (last 7 days)
$withdraw_sql = "SELECT u.tbl_full_name, u.tbl_user_name, w.tbl_user_id, w.tbl_withdraw_amount, w.tbl_time_stamp
                 FROM tbluserswithdraw w
                 JOIN tblusersdata u ON w.tbl_user_id = u.tbl_uniq_id
                 WHERE w.tbl_request_status = 'success' 
                 AND LEFT(w.tbl_time_stamp, 10) IN ($date_range_list)
                 ORDER BY w.tbl_withdraw_amount DESC LIMIT 10";

$withdraw_result = mysqli_query($conn, $withdraw_sql);

// Fetch top users with deposits (Current Month)
$current_month = date('m-Y');
$deposit_month_sql = "SELECT u.tbl_full_name, u.tbl_user_name, r.tbl_user_id, r.tbl_recharge_amount, r.tbl_time_stamp
                FROM tblusersrecharge r
                JOIN tblusersdata u ON r.tbl_user_id = u.tbl_uniq_id
                WHERE r.tbl_request_status = 'success' 
                AND r.tbl_time_stamp LIKE '%$current_month%'
                ORDER BY r.tbl_recharge_amount DESC LIMIT 10";
$deposit_month_result = mysqli_query($conn, $deposit_month_sql);

// Fetch top users with withdrawals (Current Month)
$withdraw_month_sql = "SELECT u.tbl_full_name, u.tbl_user_name, w.tbl_user_id, w.tbl_withdraw_amount, w.tbl_time_stamp
                 FROM tbluserswithdraw w
                 JOIN tblusersdata u ON w.tbl_user_id = u.tbl_uniq_id
                 WHERE w.tbl_request_status = 'success' 
                 AND w.tbl_time_stamp LIKE '%$current_month%'
                 ORDER BY w.tbl_withdraw_amount DESC LIMIT 10";
$withdraw_month_result = mysqli_query($conn, $withdraw_month_sql);

// Fetch top users with deposits (All Time)
$deposit_all_sql = "SELECT u.tbl_full_name, u.tbl_user_name, r.tbl_user_id, r.tbl_recharge_amount, r.tbl_time_stamp
                FROM tblusersrecharge r
                JOIN tblusersdata u ON r.tbl_user_id = u.tbl_uniq_id
                WHERE r.tbl_request_status = 'success' 
                ORDER BY r.tbl_recharge_amount DESC LIMIT 10";
$deposit_all_result = mysqli_query($conn, $deposit_all_sql);

// Fetch top users with withdrawals (All Time)
$withdraw_all_sql = "SELECT u.tbl_full_name, u.tbl_user_name, w.tbl_user_id, w.tbl_withdraw_amount, w.tbl_time_stamp
                 FROM tbluserswithdraw w
                 JOIN tblusersdata u ON w.tbl_user_id = u.tbl_uniq_id
                 WHERE w.tbl_request_status = 'success' 
                 ORDER BY w.tbl_withdraw_amount DESC LIMIT 10";
$withdraw_all_result = mysqli_query($conn, $withdraw_all_sql);

$signup_sql = "SELECT tbl_mobile_num, tbl_full_name, tbl_user_name, tbl_uniq_id, tbl_balance, tbl_user_joined 
FROM tblusersdata
WHERE tbl_account_status = 'true' 
ORDER BY id DESC LIMIT 10";

$signup_result = $conn->query($signup_sql);

// Get data for Signups chart (last 7 days)
$signup_chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_formatted = date('d-m-Y', strtotime($date));

    $s_sql = "SELECT COUNT(*) as total FROM tblusersdata WHERE LEFT(tbl_user_joined, 10) = '$date_formatted'";
    $s_res = mysqli_query($conn, $s_sql);
    $s_row = mysqli_fetch_assoc($s_res);
    $signup_chart_data[] = (int) $s_row['total'];
}

$date_labels_json = json_encode($date_labels);
$recharge_data_json = json_encode($recharge_data);
$withdraw_data_json = json_encode($withdraw_data);
$profit_data_json = json_encode($profit_data);
$loss_data_json = json_encode($loss_data);
$cost_data_json = json_encode($cost_data);
$signup_chart_data_json = json_encode($signup_chart_data);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Financial Analytics</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        <?php include "../components/theme-variables.php"; ?>
    </style>
    <style>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 14px 14px;
            border-bottom: 1px solid var(--border-dim);
            margin-bottom: 16px;
        }

        .dash-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .dash-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }


        .dash-breadcrumb {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 4px;
        }

        .dash-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-main);
            line-height: 1.2;
            display: block;
            font-family: var(--font-body);
        }

        .dash-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 22px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
        }

        .dash-badge i {
            color: #3b82f6;
            font-size: 14px;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
            padding: 0 10px;
        }

        .chart-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.30);
            height: 400px;
            display: flex;
            flex-direction: column;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-bar {
            width: 4px;
            height: 20px;
            border-radius: 4px;
            background: linear-gradient(180deg, #3b82f6, #06b6d4);
            flex-shrink: 0;
        }

        .chart-wrapper {
            flex-grow: 1;
            position: relative;
            min-height: 250px;
        }

        .analytics-section {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.30);
        }

        .r-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .r-table thead th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #475569;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .r-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.03);
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: all 0.2s;
        }

        /* Column Alignments */
        .text-left {
            text-align: left !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .r-table tbody td:first-child {
            border-radius: 12px 0 0 12px;
            border-left: 1px solid rgba(255, 255, 255, 0.04);
        }

        .r-table tbody td:last-child {
            border-radius: 0 12px 12px 0;
            border-right: 1px solid rgba(255, 255, 255, 0.04);
        }

        .r-table tr:hover td {
            background: rgba(59, 130, 246, 0.08);
            color: #fff;
            border-color: rgba(59, 130, 246, 0.2);
        }

        .amount-pos {
            color: var(--accent-emerald);
            font-weight: 700;
        }

        .amount-neg {
            color: var(--accent-rose);
            font-weight: 700;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-danger {
            background: rgba(244, 63, 94, 0.15);
            color: #f43f5e;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-dim);
        }

        @media (max-width: 992px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sleek Modern Scrollbar */
        .w-100::-webkit-scrollbar {
            width: 5px;
        }
        .w-100::-webkit-scrollbar-track {
            background: transparent;
        }
        .w-100::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        .w-100::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }
        .w-100 {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.05) transparent;
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
                        <span class="dash-breadcrumb">Analytics > Dashboard</span>
                        <span class="dash-title">Financial Performance</span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <div class="dash-badge"><i class='bx bx-calendar'></i>&nbsp;<?php echo date('D, M j Y'); ?></div>
                    <div class="dash-badge"><i class='bx bx-time-five'></i>&nbsp;<?php echo date('h:i A'); ?></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="section-title">
                        <span class="title-bar"></span>
                        Transaction Flow (Last 7 Days)
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="transactionChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="section-title">
                        <span class="title-bar"
                            style="background: linear-gradient(180deg, var(--accent-emerald), #06b6d4);"></span>
                        Profit & Loss Trends
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="profitLossChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="section-title">
                        <span class="title-bar" style="background: #eab308;"></span>
                        User Registrations (Last 7 Days)
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="signupChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="chart-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar"></span>
                        Top User Deposits (Last 7 Days)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th class="text-left">User</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Date</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deposit_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($deposit_result)): ?>
                                                <tr>
                                                    <td class="text-left" style="font-weight: 600;">
                                                        <?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="text-right amount-pos">
                                                        ₹<?php echo number_format($row['tbl_recharge_amount'], 2); ?>
                                                    </td>
                                                    <td class="text-right" style="font-size: 13px; color: var(--text-dim);">
                                                        <?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td class="text-center"><span class="status-badge badge-success"><i
                                                                class='bx bxs-check-circle'></i>
                                                            Success</span></td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="3" class="empty-state">No deposits found</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar" style="background: var(--accent-rose);"></span>
                        Top User Withdrawals (Last 7 Days)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th class="text-left">User</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Date</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($withdraw_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($withdraw_result)): ?>
                                                <tr>
                                                    <td class="text-left" style="font-weight: 600;">
                                                        <?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="text-right amount-neg">
                                                        ₹<?php echo number_format($row['tbl_withdraw_amount'], 2); ?>
                                                    </td>
                                                    <td class="text-right" style="font-size: 13px; color: var(--text-dim);">
                                                        <?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td class="text-center"><span class="status-badge badge-danger"><i
                                                                class='bx bxs-bolt'></i> Paid</span>
                                                    </td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="3" class="empty-state">No withdrawals found</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="chart-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar" style="background: linear-gradient(180deg, #3b82f6, #6366f1);"></span>
                        Top User Deposits (This Month)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deposit_month_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($deposit_month_result)): ?>
                                                <tr>
                                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="amount-pos">₹<?php echo number_format($row['tbl_recharge_amount'], 2); ?>
                                                    </td>
                                                    <td style="font-size: 13px; color: var(--text-dim);">
                                                        <?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td><span class="status-badge badge-success"><i class='bx bxs-check-circle'></i>
                                                            Success</span></td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">No deposits found this month</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar" style="background: linear-gradient(180deg, #f43f5e, #881337);"></span>
                        Top User Withdrawals (This Month)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($withdraw_month_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($withdraw_month_result)): ?>
                                                <tr>
                                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="amount-neg">₹<?php echo number_format($row['tbl_withdraw_amount'], 2); ?>
                                                    </td>
                                                    <td style="font-size: 13px; color: var(--text-dim);">
                                                        <?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td><span class="status-badge badge-danger"><i class='bx bxs-bolt'></i> Paid</span>
                                                    </td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">No withdrawals found this month</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="chart-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar" style="background: linear-gradient(180deg, var(--accent-emerald), #059669);"></span>
                        Top User Deposits (All Time)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th class="text-left">User</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Date</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deposit_all_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($deposit_all_result)): ?>
                                                <tr>
                                                    <td class="text-left" style="font-weight: 600;"><?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="text-right amount-pos">₹<?php echo number_format($row['tbl_recharge_amount'], 2); ?>
                                                    </td>
                                                    <td class="text-right" style="font-size: 13px; color: var(--text-dim);"><?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td class="text-center"><span class="status-badge badge-success"><i class='bx bxs-check-circle'></i>
                                                            Success</span></td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">No record found</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="section-title">
                        <span class="title-bar" style="background: linear-gradient(180deg, var(--accent-rose), #9f1239);"></span>
                        Top User Withdrawals (All Time)
                    </div>
                    <div class="w-100" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th class="text-left">User</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Date</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($withdraw_all_result->num_rows > 0):
                                    while ($row = mysqli_fetch_assoc($withdraw_all_result)): ?>
                                                <tr>
                                                    <td class="text-left" style="font-weight: 600;"><?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                        <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                            <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_user_id'] ?? $row['tbl_uniq_id'] ?? 'N/A'); ?>)</div>
                                                    </td>
                                                    <td class="text-right amount-neg">₹<?php echo number_format($row['tbl_withdraw_amount'], 2); ?>
                                                    </td>
                                                    <td class="text-right" style="font-size: 13px; color: var(--text-dim);"><?php echo substr($row['tbl_time_stamp'], 0, 10); ?></td>
                                                    <td class="text-center"><span class="status-badge badge-danger"><i class='bx bxs-bolt'></i> Paid</span>
                                                    </td>
                                                </tr>
                                        <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">No record found</td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="analytics-section" style="margin-bottom: 40px;">
                <div class="section-title">
                    <span class="title-bar" style="background: #eab308;"></span>
                    Recent Signups
                </div>
                <table class="r-table">
                    <thead>
                        <tr>
                            <th class="text-left">Mobile Number</th>
                            <th class="text-left">Name</th>
                            <th class="text-right">Wallet Balance</th>
                            <th class="text-right">Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($signup_result->num_rows > 0):
                            while ($row = $signup_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-left" style="letter-spacing: 1px;">
                                                <?php echo htmlspecialchars($row["tbl_mobile_num"]); ?></td>
                                            <td class="text-left" style="font-weight:600;">
                                                <?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                                <div style="font-size: 9px; color: var(--text-dim); font-weight: 500;">
                                                    <?php echo htmlspecialchars($row['tbl_full_name']); ?> (<?php echo htmlspecialchars($row['tbl_uniq_id']); ?>)</div>
                                            </td>
                                            <td class="text-right" style="font-weight: 700; color: var(--text-main);">
                                                ₹<?php echo number_format($row["tbl_balance"], 2); ?></td>
                                            <td class="text-right" style="color: var(--text-dim); font-size: 13px;">
                                                <?php echo $row["tbl_user_joined"]; ?></td>
                                        </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="3" class="empty-state">No recent signups</td>
                                </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#8b949e';
        Chart.defaults.font.family = "'DM Sans', sans-serif";

        document.addEventListener('DOMContentLoaded', function () {
            // Transaction Chart (Bar)
            const transactionCtx = document.getElementById('transactionChart');
            if (transactionCtx) {
                new Chart(transactionCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo $date_labels_json; ?>,
                        datasets: [
                            {
                                label: 'Recharge',
                                data: <?php echo $recharge_data_json; ?>,
                                backgroundColor: '#3b82f6',
                                borderRadius: 6,
                                barThickness: 12
                            },
                            {
                                label: 'Withdrawal',
                                data: <?php echo $withdraw_data_json; ?>,
                                backgroundColor: '#f43f5e',
                                borderRadius: 6,
                                barThickness: 12
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'center',
                                labels: {
                                    boxWidth: 10,
                                    usePointStyle: true,
                                    padding: 20,
                                    font: { size: 11, weight: 'bold' }
                                }
                            },
                            tooltip: {
                                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--panel-bg').trim() || '#161b22',
                                titleColor: getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#fff',
                                bodyColor: getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#c9d1d9',
                                borderColor: 'rgba(255,255,255,0.1)',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: true,
                                callbacks: {
                                    label: function (context) { return ' ₹' + context.raw.toLocaleString(); }
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                                ticks: { callback: function (value) { return '₹' + value; } }
                            },
                            x: { grid: { display: false, drawBorder: false } }
                        }
                    }
                });
            }

            // Profit/Loss Chart (Line)
            const profitLossCtx = document.getElementById('profitLossChart');
            if (profitLossCtx) {
                new Chart(profitLossCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo $date_labels_json; ?>,
                        datasets: [
                            {
                                label: 'Profit',
                                data: <?php echo $profit_data_json; ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#10b981'
                            },
                            {
                                label: 'Loss',
                                data: <?php echo $loss_data_json; ?>,
                                borderColor: '#f43f5e',
                                backgroundColor: 'rgba(244, 63, 94, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#f43f5e'
                            },
                            {
                                label: 'Cost',
                                data: <?php echo $cost_data_json; ?>,
                                borderColor: '#3b82f6',
                                borderDash: [5, 5],
                                borderWidth: 2,
                                fill: false,
                                tension: 0.4,
                                pointRadius: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'center',
                                labels: {
                                    boxWidth: 10,
                                    usePointStyle: true,
                                    padding: 20,
                                    font: { size: 11, weight: 'bold' }
                                }
                            },
                            tooltip: {
                                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--panel-bg').trim() || '#161b22',
                                padding: 12,
                                borderWidth: 1,
                                borderColor: 'rgba(255,255,255,0.1)',
                                callbacks: {
                                    label: function (context) { return ' ' + context.dataset.label + ': ₹' + context.raw.toLocaleString(); }
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                                ticks: { callback: function (value) { return '₹' + value; } }
                            },
                            x: { grid: { display: false, drawBorder: false } }
                        }
                    }
                });
            }

            // Signup Chart (Line)
            const signupCtx = document.getElementById('signupChart');
            if (signupCtx) {
                new Chart(signupCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo $date_labels_json; ?>,
                        datasets: [{
                            label: 'New Signups',
                            data: <?php echo $signup_chart_data_json; ?>,
                            borderColor: '#eab308',
                            backgroundColor: 'rgba(234, 179, 8, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: '#eab308'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'center',
                                labels: { boxWidth: 12, usePointStyle: true, padding: 15 }
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                                ticks: { stepSize: 1, precision: 0 }
                            },
                            x: { grid: { display: false, drawBorder: false } }
                        }
                    }
                });
            }
        });
    </script>

    <script src="../script.js"></script>
</body>

</html>