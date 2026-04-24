<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_admins")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
    exit;
}

if(!isset($_GET['uniq-id'])){
  echo "invalid request";
  return;
}else{
  $user_uniq_id = mysqli_real_escape_string($conn,$_GET['uniq-id']);
}

// update settings btn
if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }

  $auth_user_password = mysqli_real_escape_string($conn,password_hash($_POST["account_password"],PASSWORD_BCRYPT));
  
  $update_sql = "UPDATE tbladmins SET tbl_user_password='{$auth_user_password}' WHERE tbl_uniq_id='{$user_uniq_id}'";
  $update_result = mysqli_query($conn, $update_sql) or die('error');
  if ($update_result){ ?>
  <script>
    alert('Password updated!');
    window.history.back();
  </script>
<?php }else{ ?>
  <script>
    alert('Failed to update account!');
  </script>
<?php } }

$select_sql = "SELECT * FROM tbladmins WHERE tbl_uniq_id='$user_uniq_id' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $select_res_data = mysqli_fetch_assoc($select_result);
  $user_mobile_num = $select_res_data['tbl_user_id'];
}else{
  echo 'Invalid Id!';
  return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Update Admin Password</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --font-body:    'DM Sans', sans-serif;
            --page-bg:      #0d1117;
            --panel-bg:     #161b22;
            --border-dim:   rgba(255,255,255,0.07);
            --accent-blue:  #3b82f6;
            --text-dim:     #94a3b8;
        }

        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: #e2e8f0; margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 40px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: #f1f5f9; margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: rgba(22, 27, 34, 0.6); backdrop-filter: blur(12px);
            border: 1px solid var(--border-dim); border-radius: 24px;
            padding: 40px; width: 100%; max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 0 auto;
        }

        .form-group { margin-bottom: 24px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .cus-inp {
            width: 100%; height: 52px; background: rgba(0,0,0,0.2) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 14px !important;
            padding: 0 16px !important; color: #fff !important; font-size: 15px !important;
            transition: all 0.3s ease;
        }
        .cus-inp:focus {
            border-color: var(--accent-blue) !important; background: rgba(0,0,0,0.3) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
        .cus-inp:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.05) !important; }
        .cus-inp::placeholder { color: rgba(255, 255, 255, 0.3); }

        .action-btn {
            width: 100%; height: 52px; background: var(--accent-blue);
            color: #fff; border: none; border-radius: 14px; font-weight: 800;
            font-size: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-top: 20px;
        }
        .action-btn:hover {
            transform: translateY(-2px); background: #2563eb;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-dim);
            text-decoration: none; font-weight: 700; font-size: 11px; text-transform: uppercase;
            margin-bottom: 15px; cursor: pointer; transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }

        .form-info {
            font-size: 12px; color: var(--text-dim); margin-top: -16px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 6px;
        }
    </style>
</head>
<body>

<div class="d-flex w-100" style="gap: 24px; padding: 20px; height: 100vh; overflow: hidden; align-items: stretch;">
    <?php include "../components/side-menu.php"; ?>

    <div class="main-panel hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <div class="back-link" onclick="window.history.back()">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Security Update</span>
                <h1>Update Password</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <form action="<?php echo $_SERVER['PHP_SELF'] . '?uniq-id=' . $user_uniq_id; ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label">Admin Account</label>
                        <input type="text" value="<?php echo $user_mobile_num; ?>" class="cus-inp" disabled>
                    </div>
                    <div class="form-info">
                        <i class='bx bx-info-circle'></i> Unique ID: <?php echo $user_uniq_id; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Secure Password</label>
                        <input type="text" name="account_password" class="cus-inp" placeholder="Enter new password" required autofocus>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-check-shield'></i> Confirm Update
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../script.js?v=2"></script>
</body>
</html>