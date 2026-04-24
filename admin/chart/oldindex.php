<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_pandl")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
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
    
    // Get recharge data
    $recharge_sql = "SELECT SUM(tbl_recharge_amount) as total_recharge 
                    FROM tblusersrecharge 
                    WHERE tbl_request_status = 'success' 
                    AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p') 
                    BETWEEN STR_TO_DATE('$start_date', '%Y-%m-%d %H:%i:%s') 
                    AND STR_TO_DATE('$end_date', '%Y-%m-%d %H:%i:%s')";
    
    $recharge_result = mysqli_query($conn, $recharge_sql);
    $recharge_row = mysqli_fetch_assoc($recharge_result);
    $recharge_data[] = $recharge_row['total_recharge'] ? (float)$recharge_row['total_recharge'] : 0;
    
    // Get withdrawal data
    $withdraw_sql = "SELECT SUM(tbl_withdraw_amount) as total_withdraw 
                    FROM tbluserswithdraw 
                    WHERE tbl_request_status = 'success' 
                    AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p') 
                    BETWEEN STR_TO_DATE('$start_date', '%Y-%m-%d %H:%i:%s') 
                    AND STR_TO_DATE('$end_date', '%Y-%m-%d %H:%i:%s')";
    
    $withdraw_result = mysqli_query($conn, $withdraw_sql);
    $withdraw_row = mysqli_fetch_assoc($withdraw_result);
    $withdraw_data[] = $withdraw_row['total_withdraw'] ? (float)$withdraw_row['total_withdraw'] : 0;
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
    
    $profit_sql = "SELECT 
                    SUM(CASE WHEN tbl_match_status = 'profit' THEN tbl_match_profit ELSE 0 END) as total_profit,
                    SUM(CASE WHEN tbl_match_status = 'loss' THEN tbl_match_profit ELSE 0 END) as total_loss,
                    SUM(tbl_match_cost) as total_cost
                  FROM tblmatchplayed 
                  WHERE STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') 
                  BETWEEN STR_TO_DATE('$start_date', '%Y-%m-%d %H:%i:%s') 
                  AND STR_TO_DATE('$end_date', '%Y-%m-%d %H:%i:%s')";
    
    $profit_result = mysqli_query($conn, $profit_sql);
    $profit_row = mysqli_fetch_assoc($profit_result);
    
    $profit_data[] = $profit_row['total_profit'] ? (float)$profit_row['total_profit'] : 0;
    $loss_data[] = $profit_row['total_loss'] ? (float)$profit_row['total_loss'] : 0;
    $cost_data[] = $profit_row['total_cost'] ? (float)$profit_row['total_cost'] : 0;
}
// Fetch users with deposits (last 7 days)
$deposit_sql = "SELECT u.tbl_full_name, r.tbl_recharge_amount, r.tbl_time_stamp
                FROM tblusersrecharge r
                JOIN tblusersdata u ON r.tbl_uniq_id = u.tbl_uniq_id
                WHERE r.tbl_request_status = 'success' 
                AND STR_TO_DATE(r.tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p') >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY r.tbl_recharge_amount DESC";

$deposit_result = mysqli_query($conn, $deposit_sql);

// Fetch users with withdrawals (last 7 days)
$withdraw_sql = "SELECT u.tbl_full_name, w.tbl_withdraw_amount, w.tbl_time_stamp
                 FROM tbluserswithdraw w
                 JOIN tblusersdata u ON w.tbl_uniq_id = u.tbl_uniq_id
                 WHERE w.tbl_request_status = 'success' 
                 AND STR_TO_DATE(w.tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p') >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY w.tbl_withdraw_amount DESC";

$withdraw_result = mysqli_query($conn, $withdraw_sql);

$sql = "SELECT tbl_mobile_num, tbl_balance, tbl_user_joined 
FROM tblusersdata
WHERE tbl_account_status = 'true' 
AND STR_TO_DATE(tbl_user_joined, '%d-%m-%Y %h:%i %p') >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY STR_TO_DATE(tbl_user_joined, '%d-%m-%Y %h:%i %p') DESC";

$result = $conn->query($sql);

// Close database connection

$date_labels_json = json_encode($date_labels);
$recharge_data_json = json_encode($recharge_data);
$withdraw_data_json = json_encode($withdraw_data);
$profit_data_json = json_encode($profit_data);
$loss_data_json = json_encode($loss_data);
$cost_data_json = json_encode($cost_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard Charts</title>
    <body>
    <button onclick="window.location.href='../'" style="margin: 15px; padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
        ← Back to Dashboard
    </button>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
         table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        button {
            margin: 15px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        
        canvas {
            width: 100% !important;
            height: 300px !important;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                padding: 10px;
            }
            
            canvas {
                height: 250px !important;
            }
        }
    </style>
</head>
<body>
    <div class="chart-container">
        <div class="chart-title">Recharge vs Withdrawal (Last 7 Days)</div>
        <canvas id="transactionChart"></canvas>
    </div>
    <div class="chart-container">
        <div class="chart-title">Profit/Loss Analysis</div>
        <canvas id="profitLossChart"></canvas>
    </div>

    
    <script>
        // Transaction Chart
const transactionCtx = document.getElementById('transactionChart').getContext('2d');
new Chart(transactionCtx, {
    type: 'bar',
    data: {
        labels: <?php echo $date_labels_json; ?>,
        datasets: [
            {
                label: 'Recharge',
                data: <?php echo $recharge_data_json; ?>,
                backgroundColor: 'rgba(76, 175, 80, 0.7)',
                borderColor: 'rgba(76, 175, 80, 1)',
                borderWidth: 1
            },
            {
                label: 'Withdrawal',
                data: <?php echo $withdraw_data_json; ?>,
                backgroundColor: 'rgba(244, 67, 54, 0.7)',
                borderColor: 'rgba(244, 67, 54, 1)',
                borderWidth: 1
            },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₹' + context.raw.toLocaleString();
                    }
                }
            },
            legend: {
                position: 'top'
            }
        }
    }
});
        
        // Profit/Loss Chart
        const profitLossCtx = document.getElementById('profitLossChart').getContext('2d');
        new Chart(profitLossCtx, {
            type: 'line',
            data: {
                labels: <?php echo $date_labels_json; ?>,
                datasets: [
                    {
                        label: 'Profit',
                        data: <?php echo $profit_data_json; ?>,
                        backgroundColor: 'rgba(76, 175, 80, 0.2)',
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Loss',
                        data: <?php echo $loss_data_json; ?>,
                        backgroundColor: 'rgba(244, 67, 54, 0.2)',
                        borderColor: 'rgba(244, 67, 54, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Total Cost',
                        data: <?php echo $cost_data_json; ?>,
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        borderColor: 'rgba(33, 150, 243, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₹' + context.raw.toLocaleString();
                            }
                        }
                    },
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
<h2>Top User Deposits (Last 7 Days)</h2>
<table>
    <tr>
        <th>User Name</th>
        <th>Deposit Amount</th>
        <th>Date</th>
    </tr>
    <?php 
    if ($deposit_result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($deposit_result)) { 
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['tbl_full_name']); ?></td>
        <td>₹<?php echo number_format($row['tbl_recharge_amount'], 2); ?></td>
        <td><?php echo htmlspecialchars($row['tbl_time_stamp']); ?></td>
    </tr>
    <?php }} else {
            echo "<tr><td colspan='3'>No records found</td></tr>";
        } ?>
</table>

<h2>Top User Withdrawals (Last 7 Days)</h2>
<table>
    <tr>
        <th>User Name</th>
        <th>Withdrawal Amount</th>
        <th>Date</th>
    </tr>
    <?php 
    if ($withdraw_result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($withdraw_result)) { 
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['tbl_full_name']); ?></td>
        <td>₹<?php echo number_format($row['tbl_withdraw_amount'], 2); ?></td>
        <td><?php echo htmlspecialchars($row['tbl_time_stamp']); ?></td>
    </tr>
    <?php }} else {
            echo "<tr><td colspan='3'>No records found</td></tr>";
        } ?>
</table>
    <h2>Recent Signups (Last 7 Days)</h2>
    
    <table>
        <tr>
            <th>Mobile</th>
            <th>Balance</th>
            <th>Date Joined</th>
        </tr>

        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>" . htmlspecialchars($row["tbl_mobile_num"]) . "</td>
                        <td>₹" . number_format($row["tbl_balance"], 2) . "</td>
                        <td>" . $row["tbl_user_joined"] . "</td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='3'>No records found</td></tr>";
        }
        ?>
 </table>   
</body>
</html>