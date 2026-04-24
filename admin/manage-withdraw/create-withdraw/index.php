<?php
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_settings") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}
else {
    header('location:../../logout-account');
    exit;
}

function generateUniqId($length = 6)
{
    $characters = "0123456789ABCDEFGHIJKLMNOPRSTUZX";
    $charactersLength = strlen($characters);
    $randomString = "";
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$uniq_reward_id = generateUniqId();

// Update settings btn
if (isset($_POST['submit'])) {
    if (!isset($IS_PRODUCTION_MODE) || !$IS_PRODUCTION_MODE) {
        echo "Game is under Demo Mode. So, you cannot add or modify.";
        return;
    }

    if (!isset($conn)) {
        die("Database connection error.");
    }

    $salary_userid = trim($_POST['salary_userid']);
    $salary_amount = trim($_POST['salary_amount']);

    if (empty($salary_userid) || empty($salary_amount)) {
        echo "<script>alert('User ID and Withdraw Amount are required!'); window.history.back();</script>";
        exit;
    }

    date_default_timezone_set('Asia/Kolkata');
    $curr_date_time = date('d-m-Y h:i:s a');

    // Using prepared statements to prevent SQL injection
    $check_sql = "SELECT * FROM tblotherstransactions WHERE tbl_user_id = ? AND tbl_transaction_type = 'Play Matched' AND tbl_transaction_amount = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $salary_userid, $salary_amount);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows == 0) {
        $insert_sql = "INSERT INTO tblotherstransactions (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
                       VALUES (?, 'app', 'Play Matched', ?, 'Play Matched', ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sss", $salary_userid, $salary_amount, $curr_date_time);
        $insert_result = $stmt->execute();

        if ($insert_result) {
            echo "<script>alert('Updated!'); window.location.href='../index.php';</script>";
            exit;
        }
        else {
            echo "<script>alert('Error while inserting!'); window.history.back();</script>";
        }
    }
    else {
        echo "<script>alert('Already Exists!'); window.history.back();</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: New Withdraw</title>
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
        .cus-inp::placeholder { color: rgba(255, 255, 255, 0.3); }

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
            text-decoration: none; font-weight: 700; font-size: 13px; margin-bottom: 24px;
            transition: color 0.2s; cursor: pointer;
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
                <div class="back-link" onclick="window.history.back()" style="margin-bottom: 10px; font-size: 11px; text-transform: uppercase;">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Withdrawal Control</span>
                <h1>Create Withdraw</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px);">
            <div class="glass-card">
                <div class="back-link" onclick="window.history.back()">
                    <i class='bx bx-left-arrow-alt ft-sz-20'></i>
                    Back to List
                </div>
                
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label">User ID</label>
                        <input type="text" name="salary_userid" class="cus-inp" placeholder="Enter target user unique ID" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Withdraw Amount</label>
                        <input type="number" name="salary_amount" class="cus-inp" placeholder="₹ 0.00" step="0.01" required>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-plus-circle'></i> Add Withdraw
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../script.js?v=2"></script>
</body>
</html>
