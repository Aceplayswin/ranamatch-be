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

// Ensure at least one config exists
$check_sql = "SELECT id FROM tbl_cashback_config LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);
if(mysqli_num_rows($check_res) == 0){
    mysqli_query($conn, "INSERT INTO tbl_cashback_config (min_loss_threshold, cashback_percentage, frequency, claim_mode) VALUES (500, 5, 'daily', 'automatic')");
}

$message = "";
if(isset($_POST['update_rules'])){
    $min_loss = mysqli_real_escape_string($conn, $_POST['min_loss']);
    $percentage = mysqli_real_escape_string($conn, $_POST['percentage']);
    $max_limit = mysqli_real_escape_string($conn, $_POST['max_limit']);
    $frequency = mysqli_real_escape_string($conn, $_POST['frequency']);
    $claim_mode = mysqli_real_escape_string($conn, $_POST['claim_mode']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_sql = "UPDATE tbl_cashback_config SET 
                  min_loss_threshold = '$min_loss', 
                  cashback_percentage = '$percentage', 
                  max_cashback_limit = '$max_limit', 
                  frequency = '$frequency', 
                  claim_mode = '$claim_mode',
                  status = '$status' 
                  WHERE id = 1";
                  
    if(mysqli_query($conn, $update_sql)){
        $message = "Rules updated successfully!";
    } else {
        $message = "Error updating rules: " . mysqli_error($conn);
    }
}

$rule_sql = "SELECT * FROM tbl_cashback_config WHERE id = 1";
$rule_res = mysqli_query($conn, $rule_sql);
$rule = mysqli_fetch_assoc($rule_res);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Cashback Rules Configuration</title>
    <link href='../style.css' rel='stylesheet'>
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body { background-color: var(--page-bg); color: var(--text-main); font-family: var(--font-body); }
        .config-card { background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 20px; padding: 30px; max-width: 600px; margin: 40px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 8px; }
        input, select { width: 100%; padding: 12px 15px; border-radius: 12px; background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main); font-weight: 600; outline: none; }
        .btn-save { width: 100%; padding: 15px; border-radius: 12px; border: none; background: linear-gradient(135deg, #06b6d4, #0891b2); color: white; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; margin-top: 20px; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; font-weight: 600; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--status-success); border: 1px solid rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body>
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content">
            <div class="config-card">
                <h2 style="font-weight: 800; margin-bottom: 5px;">Cashback <span style="color: #06b6d4;">Rules</span></h2>
                <p style="font-size: 12px; color: var(--text-dim); margin-bottom: 30px;">Define how net losses are rewarded.</p>

                <?php if($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Minimum Loss Threshold (₹)</label>
                        <input type="number" name="min_loss" value="<?php echo $rule['min_loss_threshold']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Cashback Percentage (%)</label>
                        <input type="number" step="0.01" name="percentage" value="<?php echo $rule['cashback_percentage']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Max Cashback Limit (₹)</label>
                        <input type="number" name="max_limit" value="<?php echo $rule['max_cashback_limit']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="frequency">
                            <option value="daily" <?php if($rule['frequency']=='daily') echo 'selected'; ?>>Daily</option>
                            <option value="weekly" <?php if($rule['frequency']=='weekly') echo 'selected'; ?>>Weekly</option>
                            <option value="monthly" <?php if($rule['frequency']=='monthly') echo 'selected'; ?>>Monthly</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Claim Mode</label>
                        <select name="claim_mode">
                            <option value="automatic" <?php if($rule['claim_mode']=='automatic') echo 'selected'; ?>>Automatic (Direct Wallet)</option>
                            <option value="manual" <?php if($rule['claim_mode']=='manual') echo 'selected'; ?>>Manual (Player Must Claim)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>System Status</label>
                        <select name="status">
                            <option value="active" <?php if($rule['status']=='active') echo 'selected'; ?>>Active</option>
                            <option value="inactive" <?php if($rule['status']=='inactive') echo 'selected'; ?>>Disabled</option>
                        </select>
                    </div>

                    <button type="submit" name="update_rules" class="btn-save">Update Rules</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
