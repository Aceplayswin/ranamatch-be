<?php
define("ACCESS_SECURITY","true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_admins")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../../logout-account');
    exit;
}

// update settings btn
if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }
  
  $auth_user_id = mysqli_real_escape_string($conn, $_POST["signup_mobile"] ?? '');
  $auth_user_password = mysqli_real_escape_string($conn, password_hash($_POST["signup_password"] ?? '', PASSWORD_BCRYPT));
  
  $user_access_list = "";
  
  $access_fields = [
    'access_match', 'access_users_data', 'access_recent_played', 'access_recharge',
    'access_withdraw', 'access_template', 'access_help', 'access_message',
    'access_gift', 'access_settings', 'access_pandl', 'access_admins'
  ];

  foreach ($access_fields as $field) {
    if (isset($_POST[$field]) && $_POST[$field] == 'on') {
      $user_access_list .= $field . ',';
    }
  }
  
  $user_access_list = rtrim($user_access_list, ',');

  function generateRandomString($length = 30) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  $unique_id = generateRandomString();
  $tbl_auth_secret = generateRandomString();

  // current date & time
  date_default_timezone_set('Asia/Kolkata');
  $curr_date_time = date('d-M-Y h:i:s a');

  if($auth_user_id != ""){
    $pre_sql = "SELECT * FROM tbladmins";
    $pre_result = mysqli_query($conn, $pre_sql) or die('error');
    
    if(mysqli_num_rows($pre_result) < $ADMIN_ACCOUNTS_LIMIT){
      $pre_sql = "SELECT * FROM tbladmins WHERE tbl_uniq_id=? OR tbl_user_id=?";
      $stmt = mysqli_prepare($conn, $pre_sql);
      mysqli_stmt_bind_param($stmt, "ss", $unique_id, $auth_user_id);
      mysqli_stmt_execute($stmt);
      $pre_result = mysqli_stmt_get_result($stmt);
    
      if (mysqli_num_rows($pre_result) <= 0){
        $insert_sql = "INSERT INTO tbladmins(tbl_uniq_id, tbl_user_id, tbl_user_password, tbl_user_access_list, tbl_date_time, tbl_auth_secret) 
                       VALUES(?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "ssssss", $unique_id, $auth_user_id, $auth_user_password, $user_access_list, $curr_date_time, $tbl_auth_secret);
        $insert_result = mysqli_stmt_execute($stmt);
        if($insert_result){
          echo "<script>alert('New account Created!'); window.location.href='../index.php';</script>";
          exit;
        } else {
          echo "Error creating account: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
      } else {
        echo "<script>alert('Entered mobile or uniqid is already registered!'); window.history.back();</script>";
        exit;
      }
    } else {
      echo "<script>alert('Maximum number of admin accounts reached!'); window.history.back();</script>";
      exit;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: New Admin</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../../components/theme-variables.php"; ?></style>
<style>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 40px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim); border-radius: 24px;
            padding: 40px; width: 100%; max-width: 650px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 0 auto;
        }

        .form-group { margin-bottom: 24px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .cus-inp {
            width: 100%; height: 52px; background: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important; border-radius: 14px !important;
            padding: 0 16px !important; color: var(--text-main) !important; font-size: 15px !important;
            transition: all 0.3s ease;
        }
        .cus-inp:focus {
            border-color: var(--accent-blue) !important; background: var(--table-row-hover) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
        .cus-inp::placeholder { color: var(--text-dim); opacity: 0.5; }

        .access-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
            background: rgba(0,0,0,0.1); padding: 20px; border-radius: 16px;
            border: 1px solid var(--border-dim);
        }
        .access-checkbox {
            display: flex; align-items: center; gap: 10px;
            padding: 10px; border-radius: 10px; transition: background 0.2s;
            cursor: pointer;
        }
        .access-checkbox:hover { background: rgba(255,255,255,0.03); }
        .access-checkbox input {
            width: 18px; height: 18px; accent-color: var(--accent-blue);
            cursor: pointer;
        }
        .access-checkbox label {
            font-size: 13px; font-weight: 500; color: var(--text-main); cursor: pointer;
            margin: 0;
        }

        .action-btn {
            width: 100%; height: 52px; background: var(--accent-emerald);
            color: #fff; border: none; border-radius: 14px; font-weight: 800;
            font-size: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-top: 20px;
        }
        .action-btn:hover {
            transform: translateY(-2px); background: #059669;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-dim);
            text-decoration: none; font-weight: 700; font-size: 11px; text-transform: uppercase;
            margin-bottom: 24px; cursor: pointer; transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }

        .section-divider {
            height: 1px; background: var(--border-dim); margin: 32px 0;
            display: flex; align-items: center; justify-content: center;
        }
        .section-divider span {
            background: var(--panel-bg); padding: 0 16px; font-size: 10px;
            font-weight: 800; color: var(--text-dim); text-transform: uppercase;
            letter-spacing: 2px;
        }

        @media (max-width: 600px) {
            .access-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <div class="back-link" onclick="window.history.back()">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Security Settings</span>
                <h1>Add New Admin</h1>
            </div>
        </div>

        <div style="padding-bottom: 100px;">
            <div class="glass-card">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Mobile Number</label>
                                <input type="text" name="signup_mobile" class="cus-inp" placeholder="Enter Mobile Number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="text" name="signup_password" class="cus-inp" placeholder="Enter Password" required>
                            </div>
                        </div>
                    </div>

                    <div class="section-divider">
                        <span>Permissions Access</span>
                    </div>

                    <div class="form-group">
                        <div class="access-checkbox mb-3" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
                            <input type="checkbox" name="access_all" id="access_all">
                            <label for="access_all" style="color: var(--accent-blue); font-weight: 800;">GRANT ALL PERMISSIONS</label>
                        </div>

                        <div class="access-grid">
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_users_data" id="access_users_data">
                                <label for="access_users_data">Users Database</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_recent_played" id="access_recent_played">
                                <label for="access_recent_played">Activity Logs</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_recharge" id="access_recharge">
                                <label for="access_recharge">Payment/Recharge</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_withdraw" id="access_withdraw">
                                <label for="access_withdraw">Withdrawal Control</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_help" id="access_help">
                                <label for="access_help">Support/Help Desk</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_gift" id="access_gift">
                                <label for="access_gift">Rewards & Gifts</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_message" id="access_message">
                                <label for="access_message">Push Messaging</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_settings" id="access_settings">
                                <label for="access_settings">System Settings</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_admins" id="access_admins">
                                <label for="access_admins">Admin Roles</label>
                            </div>
                            <div class="access-checkbox">
                                <input type="checkbox" name="access_pandl" id="access_pandl">
                                <label for="access_pandl">Financial P&L</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-user-plus'></i> Create Admin Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let access_all = document.querySelector("#access_all");
    let access_checkbox_input = document.querySelectorAll(".access-grid input");
    
    access_all.addEventListener("click", function(){
        let isChecked = access_all.checked;
        access_checkbox_input.forEach(input => {
            input.checked = isChecked;
        });
    });

    access_checkbox_input.forEach(input => {
        input.addEventListener("click", () => {
            if (!input.checked) {
                access_all.checked = false;
            } else {
                // Check if all others are checked
                let allChecked = Array.from(access_checkbox_input).every(i => i.checked);
                if (allChecked) access_all.checked = true;
            }
        });
    });
</script>
</body>
</html>