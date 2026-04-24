<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY","true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_users_data")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../../logout-account');
}

if(!isset($_GET['user-id'])){
  echo "request block";
  return;
}
$user_id = mysqli_real_escape_string($conn,$_GET['user-id']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title>Manage: All Notices</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
<?php include "../../components/theme-variables.php"; ?>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 20px; border-bottom: 1px solid var(--border-dim);
            margin-bottom: 20px;
        }
        .dash-header-left { display: flex; align-items: center; gap: 14px; }
        .back-btn {
            width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-dim); color: var(--text-main); display: flex; align-items: center;
            justify-content: center; font-size: 24px; cursor: pointer; transition: all 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.1); transform: translateX(-4px); }

        .dash-breadcrumb { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--accent-blue); }
        .dash-title { font-size: 28px; font-weight: 800; color: var(--text-main); }

        .actions-bar {
            padding: 24px 20px; display: flex; gap: 12px; align-items: center;
        }

        .btn-modern {
            padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-outline-modern { background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim); color: var(--text-dim); }
        .btn-outline-modern:hover { background: rgba(255,255,255,0.1); }

        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .r-table thead th {
            font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--text-dim); padding: 0 16px 8px; border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .r-table tbody td {
            padding: 16px; font-size: 14px; font-weight: 500; color: var(--text-dim);
            background: rgba(255,255,255,0.03); border-top: 1px solid rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .r-table tbody td:first-child { border-radius: 12px 0 0 12px; }
        .r-table tbody td:last-child { border-radius: 0 12px 12px 0; }
        .r-table tr:hover td { background: rgba(59, 130, 246, 0.05); color: var(--text-main); }

        .notice-icon {
            width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue); display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
    </style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div class="back-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
                <div>
                    <span class="dash-breadcrumb">User Manager > Communications</span>
                    <h1 class="dash-title">All Notices</h1>
                </div>
            </div>
        </div>

        <div class="actions-bar">
            <a class="btn-modern btn-outline-modern" onclick="window.location.reload()"><i class='bx bx-refresh'></i> Refresh</a>
            <a href="send-notice.php?user-id=<?php echo $user_id; ?>" class="btn-modern btn-primary-modern"><i class='bx bx-message-square-dots'></i> Send Notice</a>
        </div>

        <div style="padding: 0 20px;">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Notice Title</th>
                        <th style="text-align: right;">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM tblallnotices WHERE tbl_user_id='{$user_id}' ORDER BY id DESC";
                    $result = mysqli_query($conn, $sql) or die('search failed');
                  
                    if (mysqli_num_rows($result) > 0){
                        $idx = 1;
                        while ($row = mysqli_fetch_assoc($result)){ ?>
                            <tr>
                                <td style="color: var(--text-dim); font-size: 12px;"><?php echo $idx++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="notice-icon"><i class='bx bx-notification'></i></div>
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo $row['tbl_notice_title']; ?></div>
                                    </div>
                                </td>
                                <td style="text-align: right; color: var(--text-dim); font-size: 12px; font-weight: 600;">
                                    <?php echo $row['tbl_time_stamp']; ?>
                                </td>
                            </tr>
                        <?php } 
                    } else { ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 60px; color: var(--text-dim);">
                                <i class='bx bx-info-circle' style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                No notifications have been sent to this user yet.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>
</body>
</html>