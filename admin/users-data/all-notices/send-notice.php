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
    if($accessObj->isAllowed("access_settings")=="false"){
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

date_default_timezone_set('Asia/Kolkata');
$curr_date = date('d-m-Y');
$curr_time = date('h:i:s a');
$curr_date_time = $curr_date.' '.$curr_time;

// update settings btn
if (isset($_POST['submit'])){
  $notice_status = "true";
  $notice_title = $_POST['notice_title'];
  $notice_note = $_POST['notice_note'];
  
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }

  $insert_sql = "INSERT INTO tblallnotices(tbl_user_id,tbl_notice_title,tbl_notice_note,tbl_notice_status,tbl_time_stamp) VALUES('{$user_id}','{$notice_title}','{$notice_note}','{$notice_status}','{$curr_date_time}')";
  $insert_result = mysqli_query($conn, $insert_sql) or die('query failed');

  if ($insert_result){ ?>
  <script>
    alert('Notice Sent!!');
    window.location.href = 'index.php?user-id=<?php echo $user_id; ?>';
  </script>
<?php }else{ ?>
  <script>alert('Failed to send Notice!');</script>
<?php } } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title>Manage: Send Notice</title>
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

        .form-container {
            max-width: 600px; margin: 40px auto; padding: 0 20px;
        }

        .glass-card {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 24px;
            padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); position: relative;
        }

        .form-group { margin-bottom: 24px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 700; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;
        }

        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 16px; color: var(--text-dim); font-size: 18px; }
        
        .cus-inp {
            width: 100%; height: 52px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 14px !important;
            padding: 0 16px 0 46px !important; color: var(--text-main) !important; font-size: 14px !important;
            font-weight: 600 !important; transition: all 0.2s !important;
        }
        .cus-inp:focus {
            border-color: var(--accent-blue) !important; background: var(--input-bg) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important; outline: none;
        }

        .textarea-inp {
            height: 180px !important; padding: 16px 16px 16px 46px !important; resize: none;
        }

        .btn-submit {
            width: 100%; height: 56px; background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none; border-radius: 14px; color: #fff; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: all 0.2s; margin-top: 10px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4); }
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
                    <span class="dash-breadcrumb">Communications > New Message</span>
                    <h1 class="dash-title">Send Notice</h1>
                </div>
            </div>
        </div>

        <div class="form-container">
            <form class="glass-card" action="<?php echo $_SERVER['PHP_SELF'].'?user-id='.$user_id; ?>" method="POST">
                
                <h4 style="font-weight: 800; margin-bottom: 30px; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bx-message-square-add' style="color: var(--accent-blue);"></i> Compose Notification
                </h4>

                <div class="form-group">
                    <label class="form-label">Notice Title</label>
                    <div class="input-wrapper">
                        <i class='bx bx-heading'></i>
                        <input type="text" name="notice_title" placeholder="Enter notification title..." class="cus-inp" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notice Description</label>
                    <div class="input-wrapper">
                        <i class='bx bx-text' style="top: 18px;"></i>
                        <textarea name="notice_note" placeholder="Write your message here..." class="cus-inp textarea-inp" required></textarea>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-submit">
                    <i class='bx bx-paper-plane'></i> Send Notice
                </button>
            </form>
        </div>

    </div>
</div>
</body>
</html>