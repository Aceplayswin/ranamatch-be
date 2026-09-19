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

// Affiliate / Sponsor Attribution
$internal_uid = $select_res_data['id'];
$aff_info_q = mysqli_query($conn, "
    SELECT af.id AS aff_id, af.affiliate_code, af.full_name, af.parent_id,
           paf.id AS parent_aff_id, paf.affiliate_code AS parent_aff_code, paf.full_name AS parent_aff_name
    FROM affiliate_referrals ar
    JOIN affiliates af ON af.id = ar.affiliate_id
    LEFT JOIN affiliates paf ON paf.id = af.parent_id
    WHERE ar.user_id = '{$internal_uid}' OR ar.user_id = '{$user_id}'
    LIMIT 1
");
$aff_display = "<span style='color: var(--text-dim); opacity: 0.6;'>Direct / Organic</span>";
if ($aff_info = mysqli_fetch_assoc($aff_info_q)) {
    $aff_id = (int)$aff_info['aff_id'];
    $aff_display = "<a href='../affiliates/detail/index.php?id={$aff_id}' style='color: #38bdf8; text-decoration: none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">";
    $aff_display .= "<i class='bx bx-link-alt'></i> " . htmlspecialchars($aff_info['full_name']) . " (" . htmlspecialchars($aff_info['affiliate_code']) . ")</a>";
    if (!empty($aff_info['parent_id'])) {
        $parent_aff_id = (int)$aff_info['parent_aff_id'];
        $aff_display .= " <br><span style='font-size: 10px; color: #a855f7;'><i class='bx bx-git-repo-forked'></i> Sub-Aff via <a href='../affiliates/detail/index.php?id={$parent_aff_id}' style='color: #a855f7; text-decoration: none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">" . htmlspecialchars($aff_info['parent_aff_name'] ?: $aff_info['parent_aff_code']) . "</a></span>";
    }
} elseif (!empty($user_refered_by)) {
    $j_code = mysqli_real_escape_string($conn, $user_refered_by);
    $af_d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, affiliate_code, full_name FROM affiliates WHERE affiliate_code = '$j_code' LIMIT 1"));
    if ($af_d) {
        $aff_id = (int)$af_d['id'];
        $aff_display = "<a href='../affiliates/detail/index.php?id={$aff_id}' style='color: #38bdf8; text-decoration: none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">";
        $aff_display .= "<i class='bx bx-link-alt'></i> " . htmlspecialchars($af_d['full_name']) . " (" . htmlspecialchars($af_d['affiliate_code']) . ")</a>";
    } else {
        $ag_d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, agent_code, username FROM agents WHERE agent_code = '$j_code' OR id = '$j_code' LIMIT 1"));
        if ($ag_d) {
            $ag_id = (int)$ag_d['id'];
            $ag_code = urlencode($ag_d['agent_code'] ?: $j_code);
            $aff_display = "<a href='../agents/detail/index.php?id={$ag_id}&code={$ag_code}' style='color: #34d399; text-decoration: none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">";
            $aff_display .= "<i class='bx bx-user-pin'></i> Agent: " . htmlspecialchars($ag_d['username'] ?: $ag_d['agent_code']) . "</a>";
        } else {
            $enc_code = urlencode($user_refered_by);
            $aff_display = "<a href='../agents/detail/index.php?code={$enc_code}' style='color: #34d399; text-decoration: none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">";
            $aff_display .= "<i class='bx bx-user-pin'></i> " . htmlspecialchars($user_refered_by) . "</a>";
        }
    }
}

// Financial Metrics
$dep_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(tbl_recharge_amount), 0) AS total FROM tblusersrecharge WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success'"));
$total_deposits = (float)($dep_res['total'] ?? 0);

$wd_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(tbl_withdraw_amount), 0) AS total FROM tbluserswithdraw WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success'"));
$total_withdrawals = (float)($wd_res['total'] ?? 0);

$bet_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    COALESCE(SUM(tbl_match_cost), 0) AS total_bet,
    COALESCE(SUM(tbl_match_profit), 0) AS total_win,
    COALESCE(SUM(CASE WHEN tbl_match_profit = 0 THEN tbl_match_cost WHEN tbl_match_profit < tbl_match_cost THEN (tbl_match_cost - tbl_match_profit) ELSE 0 END), 0) AS total_loss
FROM tblmatchplayed WHERE tbl_user_id='{$user_id}'"));
$total_bets = (float)($bet_res['total_bet'] ?? 0);
$total_wins = (float)($bet_res['total_win'] ?? 0);
$total_losses = (float)($bet_res['total_loss'] ?? 0);

$comm_res = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(acl.amount), 0) AS total_comm 
    FROM affiliate_commission_ledger acl 
    JOIN affiliate_referrals ar ON ar.id = acl.referral_id 
    WHERE ar.user_id = '{$internal_uid}' OR ar.user_id = '{$user_id}'
"));
$total_aff_earning = (float)($comm_res['total_comm'] ?? 0);
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
                    <div class="back-btn" onclick="if(document.referrer && document.referrer !== location.href){ history.back(); } else { window.location.href='index.php'; }" title="Go Back"><i class='bx bx-left-arrow-alt'></i></div>
                    <div>
                        <span class="dash-breadcrumb">User Manager > Controls</span>
                        <span class="dash-title"><?php echo htmlspecialchars($user_full_name); ?></span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <?php if ($user_status == "true" || $user_status == "active"): ?>
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
                    <div class="info-item"><span class="info-label">Account Level</span><span class="info-value">Lv. <?php echo $account_level; ?></span></div>
                    <div class="info-item"><span class="info-label">Affiliate / Sponsor</span><span class="info-value" style="color: #38bdf8; font-weight: 700;"><?php echo $aff_display; ?></span></div>
                    <div class="info-item"><span class="info-label">Total Deposits</span><span class="info-value" style="color: var(--status-success);">₹<?php echo number_format($total_deposits, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Total Withdrawals</span><span class="info-value" style="color: var(--status-danger);">₹<?php echo number_format($total_withdrawals, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Total Bets (Turnover)</span><span class="info-value">₹<?php echo number_format($total_bets, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Winning</span><span class="info-value" style="color: var(--status-success);">₹<?php echo number_format($total_wins, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Loss</span><span class="info-value" style="color: var(--status-danger);">₹<?php echo number_format($total_losses, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Affiliate Earning</span><span class="info-value" style="color: #38bdf8; font-weight: 800;">₹<?php echo number_format($total_aff_earning, 2); ?></span></div>
                    <div class="info-item"><span class="info-label">Last Active</span><span
                            class="info-value"><?php echo $user_last_active_date . ' ' . $user_last_active_time; ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">Join Date</span><span
                            class="info-value"><?php echo $user_joined; ?></span></div>
                </div>

                <div class="actions-panel">
                    <h4 style="font-weight: 800; margin-bottom: 15px; color: var(--accent-blue);">Management Tools</h4>

                    <?php if ($user_status == "true" || $user_status == "active"): ?>
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