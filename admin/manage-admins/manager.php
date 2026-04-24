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

if(!isset($_GET['user-id'])){
  echo "invalid request";
  return;
}else{
  $user_id = mysqli_real_escape_string($conn,$_GET['user-id']);
}


$select_sql = "SELECT * FROM tbladmins WHERE tbl_user_id='$user_id' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $select_res_data = mysqli_fetch_assoc($select_result);

  $uniq_id = $select_res_data['tbl_uniq_id'];
  $user_joined = $select_res_data['tbl_date_time'];
  
}else{
  echo 'Invalid user-id!';
  return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Admin Details</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
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
            margin-bottom: 40px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: #f1f5f9; margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: rgba(22, 27, 34, 0.6); backdrop-filter: blur(12px);
            border: 1px solid var(--border-dim); border-radius: 24px;
            padding: 40px; width: 100%; max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 0 auto;
            text-align: center;
        }

        .profile-avatar {
            width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue); border-radius: 20px; display: flex;
            align-items: center; justify-content: center; font-size: 40px;
            margin: 0 auto 24px; border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .metric-group {
            display: grid; grid-template-columns: 1fr; gap: 16px;
            margin-bottom: 32px; text-align: left;
        }
        .metric-item {
            padding: 16px; background: rgba(0,0,0,0.2); border-radius: 16px;
            border: 1px solid var(--border-dim);
        }
        .metric-label {
            font-size: 10px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
        }
        .metric-value { font-size: 15px; font-weight: 700; color: #f1f5f9; font-family: monospace; }

        .action-btn {
            width: 100%; height: 52px; background: var(--accent-blue);
            color: #fff; border: none; border-radius: 14px; font-weight: 800;
            font-size: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            text-decoration: none;
        }
        .action-btn:hover {
            transform: translateY(-2px); background: #2563eb;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
            color: #fff;
        }
        
        .btn-danger-modern { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .btn-danger-modern:hover { background: var(--accent-rose); color: #fff; box-shadow: 0 8px 20px rgba(244, 63, 94, 0.3); }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-dim);
            text-decoration: none; font-weight: 700; font-size: 11px; text-transform: uppercase;
            margin-bottom: 15px; cursor: pointer; transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <div class="back-link" onclick="window.history.back()">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Access Management</span>
                <h1>Admin Details</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <div class="profile-avatar">
                    <i class='bx bx-shield-quarter'></i>
                </div>
                
                <div class="metric-group">
                    <div class="metric-item">
                        <div class="metric-label">Account Mobile</div>
                        <div class="metric-value"><?php echo $user_id; ?></div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-label">Security Unique ID</div>
                        <div class="metric-value"><?php echo $uniq_id; ?></div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-label">Account Created</div>
                        <div class="metric-value"><?php echo $user_joined; ?></div>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    <a href="update-account.php?uniq-id=<?php echo $uniq_id; ?>" class="action-btn">
                        <i class='bx bx-key'></i> Change Password
                    </a>
                    <button class="action-btn btn-danger-modern" onclick="removeAccount('<?php echo $uniq_id; ?>')">
                        <i class='bx bx-user-x'></i> Remove Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../script.js?v=2"></script>
<script>
  function removeAccount(admin_uniq_id){
    if(confirm("Are you sure you want to remove this account?")){
        window.open("remove-account.php?uniq-id="+admin_uniq_id);
        setTimeout(() => { window.location.href = 'index.php'; }, 500);
    }
  }
</script>
</body>
</html>