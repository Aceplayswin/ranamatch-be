<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_settings")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
    exit;
}

if(!isset($_GET['id'])){
  echo "invalid request";
  return;
}else{
  $service_name = mysqli_real_escape_string($conn,$_GET['id']);
}


function getNumberFormat($number = 0, $decimalPoint=2){
    $multiplier = pow(10, $decimalPoint);
          
    // Truncate without rounding
    $truncated = floor($number * $multiplier) / $multiplier;
    return number_format($truncated, $decimalPoint, '.', '');
}

// update settings btn
if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }
  
  $service_value = mysqli_real_escape_string($conn,$_POST['service_value']);
  // Remove extra charcters
  if($service_name!="IMP_MESSAGE"){
    $service_value = str_replace(' ', '', $service_value);
    $service_value = str_replace('%', '', $service_value); 
  }

  if($service_name=="WITHDRAW_TAX"){
    $service_value = getNumberFormat(($service_value/100), 2);
  }

  $update_sql = "UPDATE tblservices SET tbl_service_value='{$service_value}' WHERE tbl_service_name='{$service_name}'";
  $update_result = mysqli_query($conn, $update_sql) or die('error');
  if ($update_result){ ?>
  <script>
    alert('Settings updated!');
    window.location.href='index.php';
  </script>

<?php }else{ ?>
  <script>
    alert('Failed to update setting!');
  </script>
<?php } }

$service_description = "";
$service_on_off = false;

$select_sql = "SELECT * FROM tblservices WHERE tbl_service_name='{$service_name}' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $select_res_data = mysqli_fetch_assoc($select_result);

  $service_name = $select_res_data['tbl_service_name'];
  $service_value = $select_res_data['tbl_service_value'];
  
  if($service_name=="APP_STATUS"){
    $service_on_off = true;
    $service_description = "Update App Status";
  }else if($service_name=="GAME_STATUS"){
    $service_on_off = true;
    $service_description = "Update Game Status";
  }else if($service_name=="DEPOSIT_BONUS"){
    $service_on_off = true;
    $service_description = "Update Deposit Bonus";
  }else if($service_name=="DEPOSIT_BONUS_OPTIONS"){
    $service_description = "Add values with comma separated.";      
  }else if($service_name=="OTP_ALLOWED"){
    $service_on_off = true;
    $service_description = "Update OTP Allowed";
  }else if($service_name=="SIGNUP_ALLOWED"){
    $service_on_off = true;
    $service_description = "Update SignUp (New Account) Allowed";
  }else if($service_name=="COMISSION_BONUS"){
    $service_description = "Comma separated (Level 1, Level 2, Level 3) or One value for all"; 
  }else if($service_name=="SALLARY_PERCENT"){
    $service_description = "Comma separated (Level 1, Level 2, Level 3) or One value for all";
  }else if($service_name=="RECHARGE_OPTIONS"){
    $service_description = "Add values with comma separated.";  
  }else if($service_name=="WITHDRAW_TAX"){
    $service_value = $service_value*100;
    $service_description = "Tax in percentage";
  }else if($service_name=="SMS_TOKEN"){
    $service_description = "Support: DV HOSTING API";
  }
}else{
  echo 'Invalid Service-id!';
  return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Manage Setting</title>
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
        
        textarea.cus-inp { height: 120px !important; padding: 16px !important; resize: none; }
        select.cus-inp { cursor: pointer; -webkit-appearance: none; appearance: none; }

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

        .info-card {
            background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 14px; padding: 16px; margin-bottom: 24px;
            font-size: 13px; color: #cbd5e1; display: flex; align-items: flex-start; gap: 12px;
        }
        .info-card i { font-size: 18px; color: var(--accent-blue); margin-top: 2px; }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <div class="back-link" onclick="window.location.href='index.php'">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Configuration Management</span>
                <h1>Manage Setting</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $service_name; ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label">Configuration Key</label>
                        <input type="text" value="<?php echo $service_name; ?>" class="cus-inp" disabled>
                    </div>

                    <?php if($service_description){ ?>
                        <div class="info-card">
                            <i class='bx bx-info-circle'></i>
                            <span><?php echo $service_description; ?></span>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label class="form-label">Setting Value</label>
                        <?php if($service_on_off){ ?>
                            <select class="cus-inp" name="service_value">
                                <option value="true" <?php if($service_value=="true"){ ?>selected<?php } ?>>ENABLED (ON)</option>
                                <option value="false" <?php if($service_value=="false"){ ?>selected<?php } ?>>DISABLED (OFF)</option>
                            </select>
                        <?php }else{ ?>
                            <textarea name="service_value" class="cus-inp" placeholder="Enter configuration value" required><?php echo $service_value; ?></textarea>
                        <?php } ?>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-save'></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../script.js?v=2"></script>
</body>
</html>