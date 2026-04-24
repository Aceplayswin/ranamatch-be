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


function generateUniqId($length = 6){
    $characters = "0123456789ABCDEFGHIJKLMNOPRSTUZX";
    $charactersLength = strlen($characters);
    $randomString = "";
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$uniq_reward_id = generateUniqId();

// update settings btn
if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }

  $gift_card_id = mysqli_real_escape_string($conn,$_POST['gift_card_id']);
  $gift_card_reward = mysqli_real_escape_string($conn,$_POST['gift_card_reward']);
  $gift_card_limit = mysqli_real_escape_string($conn,$_POST['gift_card_limit']);
  
  $input_single_user_id = mysqli_real_escape_string($conn,$_POST['gift_card_targeted_id']);
  $input_balance_required = mysqli_real_escape_string($conn,$_POST['gift_card_balance_limit']);
  
  if($input_single_user_id==""){
    $input_single_user_id = "none";  
  }
  
  if($input_balance_required==""){
    $input_balance_required = "none";  
  }
  
  
  // current date & time
  date_default_timezone_set('Asia/Kolkata');
  $curr_date_time = date('d-m-Y h:i:s a');
  
  $pre_sql = "SELECT * FROM tblgiftcards WHERE tbl_giftcard='{$gift_card_id}' ";
  $pre_result = mysqli_query($conn, $pre_sql) or die('error');

  if (mysqli_num_rows($pre_result) > 0){
     echo "<script>alert('Sorry, Giftcard Already Exist!');window.history.back();</script>";
  }else{
    $insert_sql = "INSERT INTO tblgiftcards(tbl_giftcard,tbl_giftcard_bonus,tbl_giftcard_limit,tbl_giftcard_targeted_id,tbl_giftcard_balance_limit,tbl_giftcard_status,gift_date_time) VALUES('{$gift_card_id}','{$gift_card_reward}','{$gift_card_limit}','{$input_single_user_id}','{$input_balance_required}','true','{$curr_date_time}')";
    $insert_result = mysqli_query($conn, $insert_sql) or die('query failed');
    
    if($insert_result){
      echo "<script>alert('Giftcard Created!'); window.location.href='../index.php';</script>";
      exit;
    }   
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: New Giftcard</title>
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

        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .cus-inp {
            width: 100%; height: 48px; background: rgba(0,0,0,0.2) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 12px !important;
            padding: 0 16px !important; color: #fff !important; font-size: 14px !important;
            transition: all 0.3s ease;
        }
        .cus-inp:focus {
            border-color: var(--accent-blue) !important; background: rgba(0,0,0,0.3) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
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
                <span class="dash-breadcrumb">Reward Control</span>
                <h1>Create Giftcard</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label">GiftCard Id</label>
                        <input type="text" name="gift_card_id" class="cus-inp" value="<?php echo $uniq_reward_id; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">GiftCard Reward</label>
                        <input type="text" name="gift_card_reward" class="cus-inp" placeholder="Reward Amount (e.g. 50)" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">GiftCards Limit</label>
                        <input type="number" name="gift_card_limit" class="cus-inp" placeholder="Max uses limit" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">GiftCards Targeted ID (Optional)</label>
                        <input type="text" name="gift_card_targeted_id" class="cus-inp" placeholder="Specific User ID (none for all)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Giftcards Balance Limit (Optional)</label>
                        <input type="number" name="gift_card_balance_limit" class="cus-inp" placeholder="Required minimum balance">
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-plus-circle'></i> Create GiftCard
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../script.js?v=2"></script>
</body>
</html>