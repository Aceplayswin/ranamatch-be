<?php

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_recharge") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
}

$selectedDate = isset($_POST['selected_date']) ? $_POST['selected_date'] : date('Y-m-d');
$totalAmount = 0;
$error = '';

if (isset($_POST['submit'])) {
    $sql = "SELECT COALESCE(SUM(tbl_recharge_amount), 0) AS total_amount 
            FROM tblusersrecharge 
            WHERE DATE(tbl_time_stamp) = '$selectedDate' 
            AND tbl_request_status = 'success'";

    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalAmount = isset($row['total_amount']) ? $row['total_amount'] : 0;
    } else {
        $error = "Error executing query: " . mysqli_error($conn);
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "../header_contents.php" ?>
  <title><?php echo $APP_NAME; ?>: Total Recharge Amount</title>
  <link href='../style.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style><?php include "../components/theme-variables.php"; ?></style>
  <style>
    body { font-family: var(--font-body) !important; background-color: var(--page-bg) !important; color: var(--text-main); }
    .premium-card {
        background: var(--panel-bg); border: 1px solid var(--border-dim);
        border-radius: 12px; padding: 20px; margin-bottom: 20px;
        box-shadow: var(--card-shadow);
    }
    .dash-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 12px; padding: 16px 14px 14px;
        border-bottom: 1px solid var(--border-dim);
        margin-bottom: 16px;
    }
    .dash-title { font-size: 22px; font-weight: 700; color: var(--text-main); }
    .dash-breadcrumb { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--status-info); }
    
    .cus-inp {
        background: var(--input-bg); border: 1px solid var(--border-dim);
        border-radius: 10px; padding: 10px 15px; color: var(--text-main); width: 100%;
    }
    .action-btn {
        background: linear-gradient(135deg, var(--status-success), var(--status-info));
        color: white; border: none; border-radius: 8px; padding: 10px 20px;
        font-weight: 600; cursor: pointer; transition: transform 0.2s;
    }
    .action-btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
  </style>
</head>
<body class="bg-light">
    
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div>
                <span class="dash-breadcrumb">Dashboard > Statistics</span>
                <h1 class="dash-title">Total Recharge Amount</h1>
            </div>
            <div class="dash-badge"><i class='bx bx-calendar'></i>&nbsp;<?php echo date('M d, Y'); ?></div>
        </div>

        <div class="premium-card">
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                <div class="mb-4">
                    <label for="selected_date" class="form-label" style="font-weight: 600; color: var(--text-dim); text-transform: uppercase; font-size: 11px; letter-spacing: 1px;">Select Date:</label>
                    <input type="date" id="selected_date" name="selected_date" value="<?php echo $selectedDate; ?>" class="cus-inp" required>
                </div>
                <button type="submit" name="submit" class="action-btn">
                    <i class='bx bx-search'></i> Get Total Amount
                </button>
            </form>
        </div>

        <?php if (isset($_POST['submit'])): ?>
        <div class="premium-card text-center" style="border-top: 4px solid var(--status-success);">
            <?php if ($error): ?>
                <p class="text-danger"><?php echo $error; ?></p>
            <?php else: ?>
                <span class="text-dim" style="text-transform: uppercase; font-size: 12px; font-weight: 700; letter-spacing: 1px; color: var(--text-dim);">Total Amount for <?php echo date('F j, Y', strtotime($selectedDate)); ?></span>
                <div style="font-size: 32px; font-weight: 800; color: var(--text-main); margin-top: 8px;">
                    <span style="font-size: 18px; vertical-align: middle; color: var(--status-success); margin-right: 5px;">₹</span><?php echo number_format($totalAmount, 2); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="../script.js?v=1"></script>
</body>
</html>