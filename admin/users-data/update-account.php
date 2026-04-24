<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

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

if(!isset($_GET['user-id'])){
  echo "invalid request";
  return;
}
$user_uniq_id = mysqli_real_escape_string($conn,$_GET['user-id']);

function generateOrderID($length = 15) {
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return 'RR0'.$randomString;
}

$uniqId = generateOrderID();

$user_balance = 0;
$account_level = 1;
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='$user_uniq_id' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $select_res_data = mysqli_fetch_assoc($select_result);
  $user_full_name = $select_res_data['tbl_full_name'];
  $user_username = $select_res_data['tbl_user_name'] ?? '';
  $user_mobile_num = $select_res_data['tbl_mobile_num'];
  $user_email_id = $select_res_data['tbl_email_id'];
  $user_balance = $select_res_data['tbl_balance'];
  $account_level = $select_res_data['tbl_account_level'];
}else{
  echo 'Invalid User-Id!';
  return;
}

// update settings btn
if (isset($_POST['submit'])){
    
  $new_user_level = mysqli_real_escape_string($conn,$_POST['new_user_level']);
  $new_user_balance = mysqli_real_escape_string($conn,$_POST['updated_user_balance']);
  $new_user_password = mysqli_real_escape_string($conn,$_POST['new_user_password']);
  $new_username = mysqli_real_escape_string($conn,$_POST['new_username']);
  
  // Check username uniqueness if changed
  if($new_username != "" && $new_username != $user_username){
    $check_q = mysqli_query($conn, "SELECT id FROM tblusersdata WHERE tbl_user_name='$new_username' AND tbl_uniq_id != '$user_uniq_id'");
    if(mysqli_num_rows($check_q) > 0){
        echo "<script>alert('Error: This username is already taken by another user.'); window.history.back();</script>";
        return;
    }
  }
  
  date_default_timezone_set('Asia/Kolkata');
  $curr_date_time = date('d-m-Y h:i a');
  
  if(!$IS_PRODUCTION_MODE && $new_user_balance > 500){
    echo "Game is under Demo Mode. So, you can not add balance more than 500rs";
    return;
  }

  if($new_user_balance < 0 || $new_user_balance == ""){
      echo "Updated Balance can't be less than 0";
      return;
  }
  
  // Logic to determine the delta for tbl_recharge_amount
  $update_balance_delta = $new_user_balance - $user_balance;
  
  if($new_user_password != ""){
    $user_hashed_password = password_hash($new_user_password,PASSWORD_BCRYPT);
    $update_sql = "UPDATE tblusersdata SET tbl_balance='{$new_user_balance}', tbl_user_name='{$new_username}', tbl_password='{$user_hashed_password}', tbl_account_level='{$new_user_level}' WHERE tbl_uniq_id='{$user_uniq_id}'"; 
  }else{
    $update_sql = "UPDATE tblusersdata SET tbl_balance='{$new_user_balance}', tbl_user_name='{$new_username}', tbl_account_level='{$new_user_level}' WHERE tbl_uniq_id='{$user_uniq_id}'";
  }
  
  $update_result = mysqli_query($conn, $update_sql) or die('error');
  
  if ($update_result){
    $request_status = ($update_balance_delta >= 0) ? "success" : "deducted";
    
    $recharge_mode = "Manual";
    $recharge_details = "Manual-Method-Admin";
    
    $insert_sql = $conn->prepare("INSERT INTO tblusersrecharge(tbl_uniq_id,tbl_user_id,tbl_recharge_amount,tbl_recharge_mode,tbl_recharge_details,tbl_request_status,tbl_time_stamp) VALUES(?,?,?,?,?,?,?)");
    $insert_sql->bind_param("sssssss", $uniqId,$user_uniq_id,$update_balance_delta,$recharge_mode, $recharge_details,$request_status,$curr_date_time);
    $insert_sql->execute();
  
    if ($insert_sql->error == "") { ?>
      <script>
        alert('Account updated successfully!');
        window.location.href = 'manager.php?id=<?php echo $user_uniq_id; ?>';
      </script>
  <?php }else{ ?>
    <script>alert('Failed to update recharge record!'); window.history.back();</script>
  <?php } }else{ ?>
    <script>alert('Failed to update account data!');</script>
<?php } } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Edit User: <?php echo htmlspecialchars($user_full_name); ?></title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
<?php include "../components/theme-variables.php"; ?>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
            color-scheme: dark;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1px solid var(--border-dim);
            margin-bottom: 16px;
        }
        .dash-header-left { display: flex; align-items: center; gap: 12px; }
        .back-btn {
            width: 36px; height: 36px; border-radius: 50%; background: var(--panel-bg);
            border: 1px solid var(--border-dim); color: var(--text-main); display: flex; align-items: center;
            justify-content: center; font-size: 20px; cursor: pointer; transition: all 0.2s;
        }
        .back-btn:hover { background: var(--table-row-hover); transform: translateX(-3px); }

        .dash-breadcrumb { font-size: 9px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--accent-blue); }
        .dash-title { font-size: 22px; font-weight: 800; color: var(--text-main); }

        .form-container {
            max-width: 550px; margin: 20px auto; padding: 0 20px;
        }

        .glass-card {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 16px;
            padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); position: relative;
        }

        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block; font-size: 10px; font-weight: 700; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
        }

        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 14px; color: var(--text-dim); font-size: 16px; top: 50%; transform: translateY(-50%); z-index: 5; }
        
        .cus-inp {
            width: 100%; height: 42px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 10px !important;
            padding: 0 12px 0 38px !important; color: var(--text-main) !important; font-size: 13px !important;
            font-weight: 600 !important; transition: all 0.2s !important;
        }
        .cus-inp option { background: var(--panel-bg); color: var(--text-main); }
        .cus-inp:focus {
            border-color: var(--accent-blue) !important; background: var(--input-bg) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; outline: none;
        }
        .cus-inp:disabled { opacity: 0.5; cursor: not-allowed; }

        .balance-preview {
            background: rgba(255,255,255,0.02); border: 1px dashed var(--border-dim);
            border-radius: 10px; padding: 12px; display: flex; align-items: center;
            justify-content: space-between; margin-top: 8px;
        }
        .total-label { font-size: 11px; color: var(--text-dim); font-weight: 500; }
        .total-value { font-size: 16px; font-weight: 800; }

        .btn-submit {
            width: 100%; height: 46px; background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none; border-radius: 10px; color: #ffffff; font-size: 14px; font-weight: 700;
            cursor: pointer; transition: all 0.2s; margin-top: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3); }
        .btn-submit:active { transform: translateY(0); }

        select.cus-inp { appearance: none; cursor: pointer; }
    </style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div class="back-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
                <div>
                    <span class="dash-breadcrumb">User Manager > Profile Edit</span>
                    <span class="dash-title">Update Account</span>
                </div>
            </div>
        </div>

        <div class="form-container">
            <form class="glass-card" action="<?php echo $_SERVER['PHP_SELF'].'?user-id='.$user_uniq_id; ?>" method="POST">
                
                <h4 style="font-weight: 800; margin-bottom: 30px; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bx-user-circle' style="color: var(--accent-blue);"></i> <?php echo htmlspecialchars($user_full_name); ?>
                </h4>

                <div class="form-group">
                    <label class="form-label">User Unique ID</label>
                    <div class="input-wrapper">
                        <i class='bx bx-fingerprint'></i>
                        <input type="text" value="<?php echo $user_uniq_id; ?>" class="cus-inp" disabled>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Username (Unique)</label>
                        <div class="input-wrapper">
                            <i class='bx bx-user'></i>
                            <input type="text" name="new_username" value="<?php echo htmlspecialchars($user_username); ?>" class="cus-inp" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <div class="input-wrapper">
                            <i class='bx bx-phone'></i>
                            <input type="text" value="<?php echo $user_mobile_num; ?>" class="cus-inp" disabled>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Adjust Balance</label>
                    <div class="input-wrapper" style="display: flex; gap: 8px; align-items: center;">
                        <!-- Hidden bait to trap browser autofill -->
                        <input type="text" style="display:none" name="mobile_trap" autocomplete="tel">
                        <div style="position: relative; flex: 1;">
                            <i class='bx bx-wallet'></i>
                            <input type="text" id="inp_balance_adjustment" name="adjustment_amount_<?php echo time(); ?>" inputmode="decimal" autocomplete="one-time-code" placeholder="Add or sub amount (e.g. 500 or -200)" class="cus-inp" onInput="updateBalancePreview()">
                        </div>
                        <button type="button" onclick="clearAdjustment()" style="height: 42px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--border-dim); background: var(--panel-bg); color: var(--text-dim); font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: 0.2s; white-space: nowrap;">Clear</button>
                    </div>
                    <input type="hidden" name="updated_user_balance" id="final_balance_input" value="<?php echo $user_balance; ?>">
                    
                    <div class="balance-preview">
                        <span class="total-label">Projected Final Balance</span>
                        <span id="preview_total_val" class="total-value" style="color: var(--text-main)">₹<?php echo number_format($user_balance, 2); ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Account Level / Role</label>
                    <div class="input-wrapper">
                        <i class='bx bx-shield-quarter'></i>
                        <select class="cus-inp" name="new_user_level">
                            <option value="1" <?php if($account_level=="1"){ echo 'selected'; } ?>>Normal Member</option>
                            <option value="2" <?php if($account_level=="2"){ echo 'selected'; } ?>>Premium Member</option>
                            <option value="3" <?php if($account_level=="3"){ echo 'selected'; } ?>>Platform Agent</option>
                        </select>
                        <i class='bx bx-chevron-down' style="left: auto; right: 16px;"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Security Override (New Password)</label>
                    <div class="input-wrapper">
                        <i class='bx bx-lock-alt'></i>
                        <input type="password" name="new_user_password" placeholder="Leave blank to keep current" class="cus-inp">
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-submit">Save Changes & Notify User</button>
            </form>
        </div>

    </div>
</div>

<script>
    const baseBalance = parseFloat(<?php echo $user_balance; ?>);
    const previewVal = document.getElementById('preview_total_val');
    const finalInp = document.getElementById('final_balance_input');
    const adjustInp = document.getElementById('inp_balance_adjustment');
    
    function clearAdjustment() {
        adjustInp.value = '';
        updateBalancePreview();
    }



    function updateBalancePreview() {
        let adjustment = parseFloat(adjustInp.value) || 0;
        let final = (baseBalance + adjustment).toFixed(2);
        
        if (final < 0) {
            previewVal.style.color = 'var(--accent-rose)';
        } else if (adjustment > 0) {
            previewVal.style.color = 'var(--accent-emerald)';
        } else if (adjustment < 0) {
            previewVal.style.color = 'var(--accent-amber)';
        } else {
            previewVal.style.color = 'var(--text-main)';
        }

        previewVal.innerText = '₹' + parseFloat(final).toLocaleString('en-IN', {minimumFractionDigits: 2});
        finalInp.value = final;
    }
</script>
</body>
</html>