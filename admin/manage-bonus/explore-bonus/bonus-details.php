<?php
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_gift") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../../logout-account');
    exit;
}

if (!isset($_GET['id'])) {
    header('location:index.php');
    exit;
}

$bonus_id = (int) $_GET['id'];

// 1. Fetch Bonus Info
$b_sql = "SELECT * FROM tbl_bonuses WHERE id = $bonus_id";
$b_res = mysqli_query($conn, $b_sql);
if (mysqli_num_rows($b_res) == 0) {
    header('location:index.php');
    exit;
}
$bonus = mysqli_fetch_assoc($b_res);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Bonus Redemption Details</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../../components/theme-variables.php"; ?>
    </style>
    <style>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .main-panel {
            flex-grow: 1;
            height: 100vh;
            overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-dim);
            padding-bottom: 15px;
        }

        .dash-title h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
        }

        .dash-breadcrumb {
            font-size: 10px;
            font-weight: 700;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .glass-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            margin-bottom: 24px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-dim);
            border-radius: 15px;
            padding: 15px;
            text-align: center;
        }

        .stat-card .label {
            font-size: 10px;
            color: var(--text-dim);
            text-transform: uppercase;
            font-weight: 800;
        }

        .stat-card .value {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
        }
    </style>
</head>

<body>
    <div class="admin-layout-wrapper">
        <?php include "../../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">
            <div class="dash-header">
                <div class="dash-title">
                    <span class="dash-breadcrumb">Bonus Management / <a href="index.php"
                            style="color: inherit; text-decoration: none;">Explore</a></span>
                    <h1>Redemption Details: <?php echo $bonus['name']; ?></h1>
                </div>
                <a href="index.php" class="btn btn-sm btn-outline-light"><i class='bx bx-arrow-back'></i> Back to
                    List</a>
            </div>

            <div class="row g-4 mb-4">
                <?php
                // Fetch All Enabled Provider Rules for this bonus
                $providers_data = [];
                $p_sql = "SELECT p.provider_name, p.wagering_multiplier 
                      FROM tbl_bonus_providers p 
                      WHERE p.bonus_id = $bonus_id AND p.is_wagering_enabled = 1";
                $p_res = mysqli_query($conn, $p_sql);
                while ($p_row = mysqli_fetch_assoc($p_res)) {
                    $providers_data[] = $p_row;
                }

                // Global/Default multiplier if no specific providers, or from main bonus
                $default_multiplier = isset($bonus['wagering_multiplier']) ? (float) $bonus['wagering_multiplier'] : 0;

                $stats_sql = "SELECT 
                          COUNT(*) as total_claims,
                          SUM(bonus_amount) as total_distributed
                          FROM tbl_bonus_redemptions WHERE bonus_id = $bonus_id AND status NOT IN ('cancelled', 'expired')";
                $stats_res = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_res);
                ?>
                <div class="col-md-8">
                    <div class="glass-card h-100 mb-0">
                        <div
                            style="font-size: 11px; font-weight: 800; color: var(--accent-blue); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.5px;">
                            Bonus Rules & Configuration</div>
                        <div class="row g-3">
                            <div class="col-6 col-md-3 border-end border-secondary">
                                <div style="font-size: 10px; color: var(--text-dim); text-transform: uppercase;">Bonus
                                    Reward</div>
                                <div style="font-weight: 800; color: #10b981;">
                                    ₹<?php echo number_format((float) ($bonus['amount'] ?? 0), 2); ?></div>
                            </div>
                            <div class="col-6 col-md-5 border-end border-secondary">
                                <div style="font-size: 10px; color: var(--text-dim); text-transform: uppercase;">
                                    Wagering Rules (Provider x)</div>
                                <div class="mt-1">
                                    <?php if (count($providers_data) > 0): ?>
                                        <?php foreach ($providers_data as $p): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-1"
                                                style="font-size: 11px;">
                                                <span style="color: #94a3b8;"><?php echo $p['provider_name']; ?>:</span>
                                                <span
                                                    style="font-weight: 800; color: #f59e0b;"><?php echo (float) $p['wagering_multiplier']; ?>x</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="font-weight: 800; color: #f59e0b;"><?php echo $default_multiplier; ?>x
                                            (Global)</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-6 col-md-2 border-end border-secondary">
                                <div style="font-size: 10px; color: var(--text-dim); text-transform: uppercase;">Expires
                                    On</div>
                                <div style="font-weight: 800; color: #ef4444;">
                                    <?php echo date("d M Y", strtotime($bonus['end_at'])); ?></div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div style="font-size: 10px; color: var(--text-dim); text-transform: uppercase;">
                                    Milestone</div>
                                <div style="font-weight: 800; color: var(--text-main);">
                                    <?php
                                    if ($bonus['is_first_deposit'] == 'yes')
                                        echo "1st Dep";
                                    else if ($bonus['is_second_deposit'] == 'yes')
                                        echo "2nd Dep";
                                    else if ($bonus['is_third_deposit'] == 'yes')
                                        echo "3rd Dep";
                                    else
                                        echo "Standard";
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 p-2 rounded"
                            style="background: var(--input-bg); font-size: 10px; color: var(--text-dim); border-left: 3px solid var(--accent-blue);">
                            <i class='bx bx-info-circle'></i> This bonus is currently
                            <strong><?php echo strtoupper($bonus['status']); ?></strong> and categorized as
                            <strong><?php echo strtoupper($bonus['bonus_category']); ?></strong>.
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="row g-3">
                        <?php
                        $stats_sql = "SELECT 
                                  COUNT(*) as total_claims,
                                  SUM(bonus_amount) as total_distributed
                                  FROM tbl_bonus_redemptions WHERE bonus_id = $bonus_id AND status NOT IN ('cancelled', 'expired')";
                        $stats_res = mysqli_query($conn, $stats_sql);
                        $stats = mysqli_fetch_assoc($stats_res);
                        ?>
                        <div class="col-6 col-md-12">
                            <div class="stat-card">
                                <div class="label">Total Active Claims</div>
                                <div class="value"><?php echo number_format((float) ($stats['total_claims'] ?? 0)); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-12">
                            <div class="stat-card">
                                <div class="label">Distributed Funds</div>
                                <div class="value" style="color: #10b981;">
                                    ₹<?php echo number_format((float) ($stats['total_distributed'] ?? 0), 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 13px; color: var(--text-main);">
                        <thead style="background: var(--table-header-bg);">
                            <tr>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    User Info</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Reward</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Turnover Status</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Conversion Status</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Date Claimed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch Redemptions with User Wagering Info
                            $r_sql = "SELECT r.*, u.tbl_full_name, u.tbl_mobile_num, u.tbl_requiredplay_balance, u.tbl_active_bonus_id, u.tbl_is_bonus_locked, u.tbl_balance
                                  FROM tbl_bonus_redemptions r
                                  JOIN tblusersdata u ON r.user_id = u.tbl_uniq_id
                                  WHERE r.bonus_id = $bonus_id
                                  ORDER BY r.created_at DESC";
                            $r_res = mysqli_query($conn, $r_sql);

                            if (mysqli_num_rows($r_res) > 0):
                                while ($redeem = mysqli_fetch_assoc($r_res)):
                                    // Use the explicit status from tbl_bonus_redemptions
                                    $status = $redeem['status']; // active, completed, cancelled, expired
                                    $is_active_now = ($status === 'active');
                                    $is_settled = ($status === 'completed');
                                    $is_cancelled = ($status === 'cancelled');
                                    $is_expired = ($status === 'expired');
                                    ?>
                                    <tr>
                                        <td class="p-3">
                                            <div style="font-weight: 700; color: var(--text-main);">
                                                <?php echo $redeem['tbl_full_name']; ?></div>
                                            <div style="font-size: 10px; color: var(--text-dim);">
                                                <?php echo $redeem['tbl_mobile_num']; ?> (ID: <?php echo $redeem['user_id']; ?>)
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <div style="font-weight: 700; color: #10b981;">
                                                ₹<?php echo number_format((float) ($redeem['bonus_amount'] ?? 0), 2); ?></div>
                                            <div style="font-size: 10px; color: var(--text-dim);">Orig. Wage:
                                                ₹<?php echo $redeem['wagering_required']; ?></div>
                                        </td>
                                        <td class="p-3">
                                            <?php if ($is_active_now): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="font-weight: 800; color: var(--accent-orange);">
                                                        ₹<?php echo number_format((float) ($redeem['tbl_requiredplay_balance'] ?? 0), 2); ?>
                                                    </div>
                                                    <div style="font-size: 10px; color: var(--text-dim);">REM.</div>
                                                </div>
                                                <?php
                                                $progress = 100 - (($redeem['tbl_requiredplay_balance'] / ($redeem['wagering_required'] ?: 1)) * 100);
                                                $progress = max(0, min(100, $progress));
                                                ?>
                                                <div class="progress mt-1"
                                                    style="height: 3px; width: 80px; background: var(--input-bg);">
                                                    <div class="progress-bar bg-warning" style="width: <?php echo $progress; ?>%">
                                                    </div>
                                                </div>
                                            <?php elseif ($is_settled): ?>
                                                <div style="color: #10b981;"><i class='bx bx-check-circle'></i> Met Wagering</div>
                                            <?php elseif ($is_cancelled): ?>
                                                <div style="color: #ef4444;"><i class='bx bx-x-circle'></i> Cancelled by User</div>
                                            <?php elseif ($is_expired): ?>
                                                <div style="color: #64748b;"><i class='bx bx-time-five'></i> Expired</div>
                                            <?php else: ?>
                                                <div style="color: #64748b;"><i class='bx bx-minus-circle'></i> Inactive</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3">
                                            <?php if ($is_settled): ?>
                                                <span class="badge bg-success" style="font-size: 10px;"><i class='bx bx-money'></i>
                                                    REAL MONEY</span>
                                            <?php elseif ($is_active_now): ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 10px;"><i
                                                        class='bx bx-time'></i> IN PROGRESS</span>
                                            <?php elseif ($is_cancelled): ?>
                                                <span class="badge bg-danger" style="font-size: 10px;"><i class='bx bx-block'></i>
                                                    CANCELLED</span>
                                            <?php elseif ($is_expired): ?>
                                                <span class="badge bg-secondary" style="font-size: 10px;"><i
                                                        class='bx bx-ghost'></i> EXPIRED</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary" style="font-size: 10px;">OVERWRITTEN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3" style="color: var(--text-dim);">
                                            <?php echo date("d M Y, H:i", strtotime($redeem['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="5" class="text-center p-4">No users have claimed this bonus yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>

</html>