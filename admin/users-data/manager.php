<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); // Disable PHP's automatic session cache headers

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_users_data") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
}

if (!isset($_GET['id'])) {
    echo "invalid request";
    return;
}
$user_id = mysqli_real_escape_string($conn, $_GET['id']);

$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='$user_id' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if (mysqli_num_rows($select_result) > 0) {
    $select_res_data = mysqli_fetch_assoc($select_result);
    $user_mobile_num = $select_res_data['tbl_mobile_num'];
    $user_username = $select_res_data['tbl_user_name'] ?? 'N/A';
    $user_full_name = $select_res_data['tbl_full_name'];
    $user_email_id = $select_res_data['tbl_email_id'];
    $user_balance = $select_res_data['tbl_balance'];
    $user_refered_by = $select_res_data['tbl_joined_under'];
    $user_last_active_date = $select_res_data['tbl_last_active_date'];
    $user_last_active_time = $select_res_data['tbl_last_active_time'];
    $account_level = $select_res_data['tbl_account_level'];
    $user_status = $select_res_data['tbl_account_status'];
    $user_joined = $select_res_data['tbl_user_joined'];
    $user_bonus_balance = $select_res_data['tbl_bonus_balance'] ?? 0;
    $user_sports_bonus = $select_res_data['tbl_sports_bonus'] ?? 0;
} else {
    echo 'Invalid user-id!';
    return;
}

$user_reward_balance = 0;
$reward_sql = "SELECT SUM(tbl_transaction_amount) as total FROM tblotherstransactions WHERE tbl_user_id='{$user_id}' ";
if ($reward_res = mysqli_query($conn, $reward_sql)) {
    $reward_row = mysqli_fetch_assoc($reward_res);
    $user_reward_balance = $reward_row['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title>Manage: <?php echo htmlspecialchars($user_full_name); ?></title>
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
            min-height: 100vh;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-dim);
            margin-bottom: 16px;
        }

        .dash-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: var(--table-row-hover);
            transform: translateX(-3px);
        }

        .dash-breadcrumb {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--status-info);
        }

        .dash-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
        }

        .manager-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 24px;
        }

        .info-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            height: fit-content;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .info-label {
            color: var(--text-dim);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: var(--text-main);
            font-weight: 700;
            font-size: 13px;
        }

        .actions-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-action {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s;
            color: #fff;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-action i {
            font-size: 20px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-status-toggle {
            background: linear-gradient(135deg, var(--status-info), #2563eb);
        }

        .btn-status-ban {
            background: linear-gradient(135deg, var(--status-danger), #e11d48);
        }

        .btn-status-active {
            background: linear-gradient(135deg, var(--status-success), #059669);
        }

        .btn-nav {
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            color: var(--text-main);
        }

        .btn-nav:hover {
            background: var(--table-row-hover);
            border-color: var(--status-info);
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--status-success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-banned {
            background: rgba(244, 63, 94, 0.15);
            color: var(--status-danger);
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        @media (max-width: 900px) {
            .manager-container {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 10px 16px;
            }

            .dash-header {
                padding: 12px 16px;
            }

            .dash-title {
                font-size: 18px;
            }
        }
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
                        <span class="dash-breadcrumb">User Manager > Controls</span>
                        <span class="dash-title"><?php echo htmlspecialchars($user_full_name); ?></span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <?php if ($user_status == "true"): ?>
                        <span class="status-pill status-active">Account Active</span>
                    <?php else: ?>
                        <span class="status-pill status-banned">Account Restricted</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="manager-container">

                <div class="info-card">
                    <h4 style="font-weight: 800; margin-bottom: 25px; color: var(--accent-blue);">Account Identity</h4>

                    <div class="info-item"><span class="info-label">Mobile Number</span><span
                            class="info-value"><?php echo $user_mobile_num; ?></span></div>
                    <div class="info-item"><span class="info-label">Username</span><span class="info-value"
                            style="color: var(--status-info);"><?php echo htmlspecialchars($user_username); ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Email ID</span><span
                            class="info-value"><?php echo $user_email_id; ?></span></div>
                    <div class="info-item"><span class="info-label">Main Balance</span><span class="info-value"
                            style="color: var(--status-success);">₹<?php echo number_format($user_balance, 2); ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Casino Bonus</span><span class="info-value"
                            style="color: var(--status-info);">₹<?php echo number_format($user_bonus_balance, 2); ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Sports Bonus</span><span class="info-value"
                            style="color: var(--status-warning);">₹<?php echo number_format($user_sports_bonus, 2); ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Rewards</span><span
                            class="info-value">₹<?php echo number_format($user_reward_balance, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Account Level</span><span class="info-value">Lv.
                            <?php echo $account_level; ?></span></div>
                    <div class="info-item"><span class="info-label">Referrer</span><span
                            class="info-value"><?php echo $user_refered_by ?: 'Organic'; ?></span></div>
                    <div class="info-item"><span class="info-label">Last Active</span><span
                            class="info-value"><?php echo $user_last_active_date . ' ' . $user_last_active_time; ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Join Date</span><span
                            class="info-value"><?php echo $user_joined; ?></span></div>
                </div>

                <div class="actions-panel">
                    <h4 style="font-weight: 800; margin-bottom: 15px; color: var(--accent-blue);">Management Tools</h4>

                    <?php if ($user_status == "true"): ?>
                        <button class="btn-action btn-status-ban" onclick="BanAccount()">
                            <span>Restrict/Ban Account</span>
                            <i class='bx bx-block'></i>
                        </button>
                    <?php else: ?>
                        <button class="btn-action btn-status-active" onclick="ActiveAccount()">
                            <span>Restore Account Access</span>
                            <i class='bx bx-check-shield'></i>
                        </button>
                    <?php endif; ?>

                    <a href="update-account.php?user-id=<?php echo $user_id; ?>" class="btn-action btn-nav">
                        <span>Edit Profile Details</span>
                        <i class='bx bx-edit-alt'></i>
                    </a>

                    <a href="view-referals.php?id=<?php echo $user_id; ?>" class="btn-action btn-nav">
                        <span>Performance & Referrals</span>
                        <i class='bx bx-group'></i>
                    </a>

                    <a href="view-activities.php?user-id=<?php echo $user_id; ?>" class="btn-action btn-nav">
                        <span>Full Activities Logs</span>
                        <i class='bx bx-history'></i>
                    </a>

                    <a href="all-notices?user-id=<?php echo $user_id; ?>" class="btn-action btn-nav">
                        <span>Direct Notifications</span>
                        <i class='bx bx-bell'></i>
                    </a>
                </div>

            </div>

        </div>
    </div>

    <script>
        function BanAccount() {
            if (confirm("Are you sure you want to restrict this account? Access will be revoked immediately.")) {
                window.open("update-request.php?request-type=ban&user-id=<?php echo $user_id; ?>");
                setTimeout(() => window.location.reload(), 1000);
            }
        }

        function ActiveAccount() {
            if (confirm("Are you sure you want to activate this account? All privileges will be restored.")) {
                window.open("update-request.php?request-type=true&user-id=<?php echo $user_id; ?>");
                setTimeout(() => window.location.reload(), 1000);
            }
        }
    </script>
</body>

</html>