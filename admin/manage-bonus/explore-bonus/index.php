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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Explore Bonus</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../../components/theme-variables.php"; ?>
    </style>
    <style>
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

        .form-label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .form-label span {
            color: #f43f5e;
            margin-left: 2px;
        }

        .cus-inp {
            width: 100%;
            height: 40px;
            background: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 10px !important;
            padding: 0 12px !important;
            color: var(--text-main) !important;
            font-size: 13px !important;
            transition: all 0.3s ease;
        }

        .cus-inp:focus {
            border-color: var(--accent-blue) !important;
            outline: none;
            background: var(--table-row-hover) !important;
        }

        .action-btn {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 0 32px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3);
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
            max-width: 800px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            margin: 0 auto;
            text-align: center;
        }

        .empty-icon {
            font-size: 64px;
            color: var(--accent-blue);
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .empty-desc {
            font-size: 14px;
            color: var(--text-dim);
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <div class="admin-layout-wrapper">
        <?php include "../../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">
            <div class="dash-header">
                <div class="dash-title">
                    <span class="dash-breadcrumb">Redeem Bonus</span>
                    <h1>Explore Bonus</h1>
                </div>
            </div>

            <?php
            $search_user = isset($_POST['username']) ? mysqli_real_escape_string($conn, $_POST['username']) : '';
            $user_data = null;
            if ($search_user != "") {
                $u_sql = "SELECT tbl_uniq_id, tbl_full_name, tbl_recharge_count, 
                      (SELECT COUNT(*) FROM tbl_bonus_redemptions WHERE user_id = tbl_uniq_id AND status NOT IN ('cancelled', 'expired')) as total_redemptions 
                      FROM tblusersdata WHERE tbl_mobile_num = '$search_user' OR tbl_uniq_id = '$search_user' LIMIT 1";
                $u_res = mysqli_query($conn, $u_sql);
                if (mysqli_num_rows($u_res) > 0) {
                    $user_data = mysqli_fetch_assoc($u_res);
                }
            }
            ?>

            <div class="glass-card mb-4" style="max-width: 100%; text-align: left;">
                <form action="" method="POST">
                    <label class="form-label">Player Intelligence Search</label>
                    <div class="d-flex gap-3">
                        <input type="text" name="username" class="cus-inp" placeholder="Enter Mobile or User ID..."
                            value="<?php echo $search_user; ?>" style="max-width: 300px;">
                        <button type="submit" class="action-btn" style="height: 40px; border-radius: 10px;">Check
                            Eligibility</button>
                        <?php if ($user_data): ?>
                            <div class="ms-auto d-flex align-items-center gap-3">
                                <div class="stat-tag"
                                    style="background: rgba(16,185,129,0.1); color: #10b981; padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700;">
                                    <i class='bx bxs-user'></i> <?php echo $user_data['tbl_full_name']; ?>
                                </div>
                                <div class="stat-tag"
                                    style="background: rgba(59,130,246,0.1); color: #3b82f6; padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700;">
                                    <i class='bx bxs-wallet'></i> Credits:
                                    <?php echo $user_data['tbl_recharge_count'] - $user_data['total_redemptions']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($user_data): ?>
                <div class="glass-card mb-4" style="max-width: 100%; border-color: rgba(59,130,246,0.3);">
                    <div class="d-flex align-items-center mb-3">
                        <i class='bx bxs-history me-2' style="color: var(--accent-blue);"></i>
                        <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Player Claims History</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size: 11px; color: var(--text-main);">
                            <thead>
                                <tr style="color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    <th>Date & Time</th>
                                    <th>Bonus Name</th>
                                    <th>Reward Amount</th>
                                    <th>Wagering Set</th>
                                    <th>Conversion Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $c_sql = "SELECT r.*, b.name FROM tbl_bonus_redemptions r 
                                  JOIN tbl_bonuses b ON r.bonus_id = b.id 
                                  WHERE r.user_id = '{$user_data['tbl_uniq_id']}' ORDER BY r.created_at DESC";
                                $c_res = mysqli_query($conn, $c_sql);
                                if (mysqli_num_rows($c_res) > 0):
                                    while ($claim = mysqli_fetch_assoc($c_res)):
                                        $c_status = $claim['status'];
                                        ?>
                                        <tr>
                                            <td style="color: #94a3b8;"><?php echo $claim['created_at']; ?></td>
                                            <td style="font-weight: 700;"><?php echo $claim['name']; ?></td>
                                            <td style="color: #10b981; font-weight: 700;">₹<?php echo $claim['bonus_amount']; ?>
                                            </td>
                                            <td>₹<?php echo $claim['wagering_required']; ?></td>
                                            <td>
                                                <?php if ($c_status === 'completed'): ?>
                                                    <span class="badge bg-success" style="font-size: 9px;"><i class='bx bx-money'></i>
                                                        REAL MONEY</span>
                                                <?php elseif ($c_status === 'active'): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size: 9px;"><i
                                                            class='bx bx-time'></i> IN PROGRESS</span>
                                                <?php elseif ($c_status === 'cancelled'): ?>
                                                    <span class="badge bg-danger" style="font-size: 9px;"><i class='bx bx-block'></i>
                                                        CANCELLED</span>
                                                <?php elseif ($c_status === 'expired'): ?>
                                                    <span class="badge bg-secondary" style="font-size: 9px;"><i class='bx bx-ghost'></i>
                                                        EXPIRED</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary" style="font-size: 9px;">OVERWRITTEN</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center p-3">No claims found for this user.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="glass-card" style="max-width: 100%; padding: 0; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 13px; color: var(--text-main);">
                        <thead style="background: var(--table-header-bg);">
                            <tr>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Bonus Detail</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Target / Type</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Budget Meter</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Reward</th>
                                <th class="p-3"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Status</th>
                                <th class="p-3 text-end"
                                    style="font-size: 10px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Pre-fetch user claims for fast lookup
                            $user_claims = [];
                            if ($user_data) {
                                $uc_sql = "SELECT bonus_id FROM tbl_bonus_redemptions WHERE user_id = '{$user_data['tbl_uniq_id']}'";
                                $uc_res = mysqli_query($conn, $uc_sql);
                                while ($uc = mysqli_fetch_assoc($uc_res))
                                    $user_claims[] = $uc['bonus_id'];
                            }

                            $sql = "SELECT b.*, 
                                (SELECT COUNT(*) FROM tbl_bonus_redemptions r WHERE r.bonus_id = b.id AND r.status NOT IN ('cancelled', 'expired')) as claimed_count 
                                FROM tbl_bonuses b ORDER BY b.id DESC";
                            $res = mysqli_query($conn, $sql);
                            while ($row = mysqli_fetch_assoc($res)):
                                $is_expired = strtotime($row['end_at']) < time();
                                $already_claimed = in_array($row['id'], $user_claims);
                                $target_id = $row['target_user_id'] ?? '';
                                $id_match = ($target_id == '' || ($user_data && $target_id == $user_data['tbl_uniq_id']));
                                $ratio_match = (!$user_data || ($user_data['tbl_recharge_count'] > $user_data['total_redemptions']));
                                $is_eligible = $id_match && $ratio_match && !$is_expired && ($row['status'] == 'active') && !$already_claimed;

                                $individual_reward = ($row['type'] == 'mass' && $row['limit_total'] > 0) ? $row['amount'] / $row['limit_total'] : $row['amount'];
                                ?>
                                <tr>
                                    <td class="p-3">
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo $row['name']; ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--text-dim);">ID: #<?php echo $row['id']; ?>
                                            | Code: <?php echo $row['coupon_code'] ?: 'None'; ?></div>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($target_id): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 9px;"><i
                                                    class='bx bxs-lock'></i> VIP: <?php echo $target_id; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-info" style="font-size: 9px;"><i class='bx bx-globe'></i>
                                                PUBLIC</span>
                                        <?php endif; ?>
                                        <div style="font-size: 10px; margin-top: 4px;">
                                            <?php echo strtoupper(str_replace('_', ' ', $row['type'])); ?></div>
                                    </td>
                                    <td class="p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span style="font-size: 10px;"><?php echo $row['claimed_count']; ?> /
                                                <?php echo $row['limit_total']; ?></span>
                                            <span
                                                style="font-size: 10px;"><?php echo round(($row['claimed_count'] / ($row['limit_total'] ?: 1)) * 100); ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 4px; background: var(--input-bg);">
                                            <div class="progress-bar <?php echo $row['claimed_count'] >= $row['limit_total'] ? 'bg-danger' : 'bg-primary'; ?>"
                                                style="width: <?php echo ($row['claimed_count'] / ($row['limit_total'] ?: 1)) * 100; ?>%">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div style="font-weight: 700; color: #10b981;">
                                            ₹<?php echo number_format($individual_reward, 2); ?></div>
                                        <?php if ($row['type'] == 'mass'): ?>
                                            <div style="font-size: 10px; color: #64748b;">Pool: ₹<?php echo $row['amount']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($search_user != ""): ?>
                                            <?php if ($already_claimed): ?>
                                                <span class="badge bg-primary" style="font-size: 10px;">REDEEMED</span>
                                            <?php else: ?>
                                                <span class="badge <?php echo $is_eligible ? 'bg-success' : 'bg-secondary'; ?>"
                                                    style="font-size: 10px;">
                                                    <?php echo $is_eligible ? 'ELIGIBLE' : 'INELIGIBLE'; ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span
                                                class="badge <?php echo ($row['status'] == 'active' && !$is_expired) ? 'bg-success' : 'bg-danger'; ?>"
                                                style="font-size: 10px;">
                                                <?php echo ($row['status'] == 'active' && !$is_expired) ? 'ACTIVE' : 'EXPIRED'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-end">
                                        <div class="btn-group">
                                            <a href="bonus-details.php?id=<?php echo $row['id']; ?>"
                                                class="btn btn-sm btn-outline-info" style="padding: 2px 8px;"
                                                title="View Redemptions"><i class='bx bx-list-ul'></i></a>
                                            <a href="../create-bonus/index.php?id=<?php echo $row['id']; ?>"
                                                class="btn btn-sm btn-outline-light" style="padding: 2px 8px;"
                                                title="Edit Promotion"><i class='bx bx-edit-alt'></i></a>
                                            <button onclick="deleteBonus(<?php echo $row['id']; ?>)"
                                                class="btn btn-sm btn-outline-danger" style="padding: 2px 8px;"
                                                title="Delete Promotion"><i class='bx bx-trash'></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>


        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function deleteBonus(id) {
            Swal.fire({
                title: 'Delete Promotion?',
                text: "This will permanently remove the bonus and all its configuration rules.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_bonus.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire('Deleted!', response.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Failed to connect to the server.', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>

</html>