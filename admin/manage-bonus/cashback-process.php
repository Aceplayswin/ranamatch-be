<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()!="true"){
    header('location:../logout-account');
    exit;
}

$rule_sql = "SELECT * FROM tbl_cashback_config WHERE id = 1";
$rule_res = mysqli_query($conn, $rule_sql);
$rule = mysqli_fetch_assoc($rule_res);

$message = "";
if(isset($_POST['process_manual'])){
    // Trigger the cron script logic manually for a custom date range
    $date_from = mysqli_real_escape_string($conn, $_POST['date_from']);
    $date_to = mysqli_real_escape_string($conn, $_POST['date_to']);
    
    // EXECUTE THE CALCULATION ENGINE
    include 'calculate_cashback_engine.php';
}

$history_sql = "SELECT l.*, u.tbl_full_name, u.tbl_mobile_num 
               FROM tbl_cashback_logs l 
               JOIN tblusersdata u ON l.user_id = u.tbl_uniq_id 
               ORDER BY l.created_at DESC LIMIT 50";
$history_res = mysqli_query($conn, $history_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Cashback Process & History</title>
    <link href='../style.css' rel='stylesheet'>
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body { background-color: var(--page-bg); color: var(--text-main); font-family: var(--font-body); }
        .process-card { background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 20px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .history-table th { text-align: left; padding: 12px 15px; background: rgba(255,255,255,0.02); color: var(--text-dim); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-dim); }
        .history-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-main); }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-credited { background: rgba(16, 185, 129, 0.1); color: var(--status-success); }
        .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--status-warning); }
        .btn-process { padding: 12px 25px; border-radius: 12px; border: none; background: var(--status-info); color: white; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-process:hover { opacity: 0.9; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content">
            
            <div class="process-card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-weight: 800; margin-bottom: 5px;">Process <span style="color: #8b5cf6;">Cashback</span></h2>
                        <p style="font-size: 12px; color: var(--text-dim);">Current Formula: <b style="color: var(--text-main);">Net Loss = (Deposit - Withdrawal - Balance)</b></p>
                    </div>
                </div>
                
                <form method="POST" style="margin-top: 25px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display:block; font-size: 10px; font-weight: 800; margin-bottom: 8px; color: var(--text-dim); text-transform: uppercase;">From Date</label>
                        <input type="date" name="date_from" style="width: 100%; padding: 10px; border-radius: 10px; background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);" required>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display:block; font-size: 10px; font-weight: 800; margin-bottom: 8px; color: var(--text-dim); text-transform: uppercase;">To Date</label>
                        <input type="date" name="date_to" style="width: 100%; padding: 10px; border-radius: 10px; background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <button type="submit" name="process_manual" class="btn-process">Calculate & Log</button>
                    <p style="font-size: 11px; color: var(--status-warning); flex: 100%; margin-top: 10px;">Note: In "Manual" mode, players must claim themselves from their profile.</p>
                </form>
            </div>

            <div class="process-card">
                <h3 style="font-weight: 800; font-size: 16px; margin-bottom: 20px;">Cashback <span style="color: var(--status-info);">Execution History</span></h3>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>User Details</th>
                                <th>Net Loss</th>
                                <th>Bonus</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($history_res) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($history_res)): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($row['period_start'])).' - '.date('M d', strtotime($row['period_end'])); ?></td>
                                        <td>
                                            <div style="font-weight: 700;"><?php echo $row['tbl_full_name']; ?></div>
                                            <div style="font-size: 11px; color: var(--text-dim);"><?php echo $row['tbl_mobile_num']; ?></div>
                                        </td>
                                        <td style="font-weight: 700;">₹<?php echo number_format($row['calculated_loss'], 2); ?></td>
                                        <td style="color: var(--status-success); font-weight: 800;">₹<?php echo number_format($row['cashback_amount'], 2); ?></td>
                                        <td>
                                            <?php if($row['status'] == 'credited'): ?>
                                                <span class="status-pill status-credited">Credited</span>
                                            <?php else: ?>
                                                <span class="status-pill status-pending">Pending Claim</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 11px; color: var(--text-dim);"><?php echo $row['created_at']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-dim);">No cashback history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
