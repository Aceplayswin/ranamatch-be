<?php
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
    exit;
}


// update settings btn
if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }
  
  $slider_status = "true";
  $slider_action = mysqli_real_escape_string($conn, $_POST['slider_action']);
  $slider_image_url = mysqli_real_escape_string($conn, $_POST['slider_image_url']);
  
  if($slider_action==""){
    $slider_action = "none";
  }

  $insert_sql = "INSERT INTO tblsliders(tbl_slider_img,tbl_slider_action,tbl_slider_status) VALUES('{$slider_image_url}','{$slider_action}','{$slider_status}')";
  $insert_result = mysqli_query($conn, $insert_sql);

  if ($insert_result){ ?>
  <script>
    alert('Slider Created!');
    window.location.href='../index.php';
  </script>
<?php }else{ ?>
  <script>
    alert('Failed to create Slider!');
  </script>
<?php } } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: New Slider</title>
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
            margin-bottom: 40px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--status-info); text-transform: uppercase; letter-spacing: 1px; }

        .main-panel {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .glass-card {
            background: var(--panel-bg);
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

        textarea.cus-inp { height: 120px !important; padding: 16px !important; resize: none; }

        .action-btn {
            width: 100%; height: 52px; background: var(--accent-blue);
            color: #fff; border: none; border-radius: 14px; font-weight: 800;
            font-size: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center; gap: 10px;
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
        .back-link:hover { color: var(--text-main); }
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
                <span style="font-size: 11px; font-weight: 700; color: var(--status-info); text-transform: uppercase; letter-spacing: 1px;">Slider Control</span>
                <h1 style="font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0;">Create Slider</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label">Slider Action URL (Optional)</label>
                        <input type="text" name="slider_action" class="cus-inp" placeholder="e.g. https://example.com/promo">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slider Image URL</label>
                        <textarea name="slider_image_url" class="cus-inp" placeholder="Enter full image URL" required></textarea>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-paper-plane'></i> Create Slider
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../script.js?v=2"></script>
</body>
</html>