<?php

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_withdraw") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
    exit;
}

// Calculate total withdraw amounts for each status
$statuses = ['success', 'rejected', 'pending', 'approve'];
$total_withdraws = [];

foreach ($statuses as $status) {
    $total_withdraw_sql = "SELECT SUM(tbl_withdraw_amount) as total FROM tbluserswithdraw WHERE tbl_request_status='$status'";
    $total_withdraw_result = mysqli_query($conn, $total_withdraw_sql);
    $total_withdraws[$status] = mysqli_fetch_assoc($total_withdraw_result)['total'] ?? 0;
}

$total_all = array_sum($total_withdraws);

// Column Discovery for Month Aggregation
$timestamp_column = 'tbl_time_stamp'; // Default based on earlier files

// Get monthly data for the chart
$monthly_data_sql = "SELECT 
    SUBSTRING(tbl_time_stamp, 4, 7) as month_label,
    SUM(CASE WHEN tbl_request_status = 'success' THEN tbl_withdraw_amount ELSE 0 END) as success_total,
    SUM(CASE WHEN tbl_request_status = 'rejected' THEN tbl_withdraw_amount ELSE 0 END) as rejected_total,
    SUM(CASE WHEN tbl_request_status = 'pending' THEN tbl_withdraw_amount ELSE 0 END) as pending_total,
    SUM(CASE WHEN tbl_request_status = 'approve' THEN tbl_withdraw_amount ELSE 0 END) as approved_total
FROM tbluserswithdraw
GROUP BY SUBSTRING(tbl_time_stamp, 4, 7)
ORDER BY STR_TO_DATE(CONCAT('01-', SUBSTRING(tbl_time_stamp, 4, 7)), '%d-%m-%Y') DESC
LIMIT 12";

$monthly_data_result = mysqli_query($conn, $monthly_data_sql);
$monthly_labels = [];
$success_chart_data = [];
$rejected_chart_data = [];

if ($monthly_data_result) {
    while ($row = mysqli_fetch_assoc($monthly_data_result)) {
        $monthly_labels[] = $row['month_label'];
        $success_chart_data[] = (float)$row['success_total'];
        $rejected_chart_data[] = (float)$row['rejected_total'];
    }
    $monthly_labels = array_reverse($monthly_labels);
    $success_chart_data = array_reverse($success_chart_data);
    $rejected_chart_data = array_reverse($rejected_chart_data);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Withdraw Statistics</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
/* Page specific variable overrides only if needed */
:root {
  --accent-blue:  var(--status-info);
}

        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100%; border-radius: 12px; border: 1px solid var(--border-dim);
            background: var(--panel-bg); box-shadow: var(--card-shadow);
            padding: 12px; overflow-y: scroll;
        }


        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat-card {
            background: var(--input-bg); border-radius: 16px; border: 1px solid var(--border-dim);
            padding: 20px; position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover { transform: translateY(-4px); border-color: var(--accent-blue); background: var(--table-row-hover); }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 3px; height: 100%; background: var(--card-color);
        }

        .stat-icon {
            width: 42px; height: 42px; border-radius: 10px; background: var(--table-row-hover);
            display: flex; align-items: center; justify-content: center; font-size: 20px;
            color: var(--card-color); margin-bottom: 12px;
        }
        .stat-label { font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 4px; }
        .stat-value { font-size: 20px; font-weight: 800; color: var(--text-main); }

        .chart-container {
            background: var(--panel-bg); border-radius: 12px; border: 1px solid var(--border-dim);
            padding: 16px; box-shadow: var(--card-shadow);
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .chart-title { font-size: 12px; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        
        .btn-outline-modern { 
            background: var(--input-bg); 
            border: 1px solid var(--border-dim); 
            color: var(--text-main); 
        }
        .btn-outline-modern:hover { background: var(--table-row-hover); }

    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div>
                <span class="dash-breadcrumb">Analytics Hub</span>
                <h1 class="dash-title">Withdraw Statistics</h1>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-modern btn-outline-modern" onclick="window.location.reload()"><i class='bx bx-refresh'></i> Refresh Feed</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="--card-color: var(--accent-blue)">
                <div class="stat-icon"><i class='bx bx-wallet'></i></div>
                <div class="stat-label">Total Outflow</div>
                <div class="stat-value">₹<?php echo number_format($total_all, 2); ?></div>
            </div>
            <div class="stat-card" style="--card-color: var(--accent-emerald)">
                <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
                <div class="stat-label">Successful Withdraws</div>
                <div class="stat-value">₹<?php echo number_format($total_withdraws['success'], 2); ?></div>
            </div>
            <div class="stat-card" style="--card-color: var(--accent-rose)">
                <div class="stat-icon"><i class='bx bx-error-circle'></i></div>
                <div class="stat-label">Rejected Payouts</div>
                <div class="stat-value">₹<?php echo number_format($total_withdraws['rejected'], 2); ?></div>
            </div>
            <div class="stat-card" style="--card-color: var(--accent-amber)">
                <div class="stat-icon"><i class='bx bx-time-five'></i></div>
                <div class="stat-label">Pending / Approved</div>
                <div class="stat-value">₹<?php echo number_format($total_withdraws['pending'] + $total_withdraws['approve'], 2); ?></div>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Withdrawal Trends (12 Months)</div>
                <div style="font-size: 10px; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">Historical Data</div>
            </div>
            <canvas id="withdrawChart" height="90"></canvas>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('withdrawChart').getContext('2d');
    
    // Create gradient
    const successGradient = ctx.createLinearGradient(0, 0, 0, 300);
    successGradient.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
    successGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'Success',
                    data: <?php echo json_encode($success_chart_data); ?>,
                    borderColor: '#10b981',
                    borderWidth: 3,
                    backgroundColor: successGradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#10b981'
                },
                {
                    label: 'Rejected',
                    data: <?php echo json_encode($rejected_chart_data); ?>,
                    borderColor: '#f43f5e',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#94a3b8', boxWidth: 12, font: { family: 'DM Sans', size: 10, weight: '700' } }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--panel-bg').trim() || '#161b22',
                    titleColor: getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#fff',
                    bodyColor: getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#e2e8f0',
                    borderColor: 'var(--border-dim)',
                    borderWidth: 1,
                    titleFont: { family: 'DM Sans', weight: '700' },
                    bodyFont: { family: 'DM Sans' }
                }
            },
            scales: {
                y: {
                    grid: { color: 'var(--border-dim)' },
                    ticks: { 
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#94a3b8',
                        font: { size: 10 },
                        callback: function(value) { return '₹' + value.toLocaleString(); }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim() || '#94a3b8', font: { size: 10 } }
                }
            }
        }
    });
</script>

<script src="../script.js?v=2"></script>
</body>
</html>