<?php
include '../../security/config.php';

// Fetch monthly recharge and withdrawal data
data = [];
$labels = [];
$rechargeData = [];
$withdrawData = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $labels[] = date('M Y', strtotime("-$i months"));
    
    $rechargeQuery = "SELECT SUM(tbl_recharge_amount) AS total FROM tblusersrecharge WHERE tbl_request_status='success' AND DATE_FORMAT(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p'), '%Y-%m') = '$month'";
    $withdrawQuery = "SELECT SUM(tbl_withdraw_amount) AS total FROM tbluserswithdraw WHERE tbl_request_status='success' AND DATE_FORMAT(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i:%s %p'), '%Y-%m') = '$month'";
    
    $rechargeResult = mysqli_query($conn, $rechargeQuery);
    $withdrawResult = mysqli_query($conn, $withdrawQuery);
    
    $rechargeRow = mysqli_fetch_assoc($rechargeResult);
    $withdrawRow = mysqli_fetch_assoc($withdrawResult);
    
    $rechargeData[] = $rechargeRow['total'] ?: 0;
    $withdrawData[] = $withdrawRow['total'] ?: 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Dashboard Charts</title>
</head>
<body>
    <canvas id="rechargeWithdrawChart"></canvas>
    <script>
        const ctx = document.getElementById('rechargeWithdrawChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Monthly Recharge',
                        data: <?php echo json_encode($rechargeData); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Monthly Withdraw',
                        data: <?php echo json_encode($withdrawData); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
