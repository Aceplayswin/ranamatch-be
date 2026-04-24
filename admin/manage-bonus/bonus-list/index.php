<?php
session_cache_limiter(""); // Disable PHP's automatic session cache headers

define("ACCESS_SECURITY","true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_gift")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../../logout-account');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Bonus List</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            margin-bottom: 30px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 22px; font-weight: 800; color: #f1f5f9; margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: var(--card-bg); backdrop-filter: blur(12px);
            border: 1px solid var(--border-dim); border-radius: 16px;
            padding: 20px; width: 100%; max-width: 1200px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4); margin: 0 auto 20px;
        }

        .form-label {
            display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }

        .cus-inp, .cus-sel {
            width: 100%; height: 40px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 8px !important;
            padding: 0 12px !important; color: #fff !important; font-size: 14px !important;
            transition: all 0.3s ease;
        }
        .cus-sel option { background-color: #161b22; color: #fff; }
        .cus-inp:focus, .cus-sel:focus {
            border-color: var(--accent-blue) !important; outline: none;
            background: rgba(0,0,0,0.3) !important;
        }

        .btn-search {
            height: 40px; background: var(--accent-blue); color: #fff; border: none;
            border-radius: 8px; padding: 0 24px; font-weight: 700; font-size: 14px;
            transition: all 0.3s; margin-top: 4px;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3); }

        /* Table Styling */
        .r-table-wrap { overflow-x: auto; }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            padding: 12px; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--border-dim);
            text-align: left;
        }
        .r-table td {
            padding: 12px; font-size: 13px; color: #cbd5e1;
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: rgba(255,255,255,0.02); }

        .btn-edit {
            color: var(--accent-blue); font-size: 18px; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-edit:hover { color: #fff; transform: scale(1.1); }
        .btn-delete {
            color: #f43f5e; font-size: 18px; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; justify-content: center; margin-left: 10px;
        }
        .btn-delete:hover { color: #fff; transform: scale(1.1); }

        /* Status Tabs */
        .status-tabs {
            display: flex; gap: 0; margin-bottom: 24px; background: rgba(0,0,0,0.2);
            border-radius: 12px; padding: 4px; width: fit-content;
        }
        .status-tab {
            padding: 10px 28px; border-radius: 10px; font-size: 13px; font-weight: 700;
            color: var(--text-dim); cursor: pointer; transition: all 0.3s;
            text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .status-tab:hover { color: #fff; }
        .status-tab.active {
            background: var(--accent-blue); color: #fff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .status-tab .tab-count {
            background: rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 6px;
            font-size: 11px; font-weight: 800;
        }
        .status-tab.active .tab-count { background: rgba(255,255,255,0.25); }

        /* Badge */
        .badge-status {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge-expired { background: rgba(244, 63, 94, 0.15); color: #f43f5e; }

        /* Custom SweetAlert2 Styles */
        .swal2-popup {
            background: rgba(22, 27, 34, 0.9) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 24px !important;
            color: #fff !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        }
        .swal2-title { color: #fff !important; font-weight: 800 !important; font-family: var(--font-body) !important; }
        .swal2-html-container { color: #94a3b8 !important; font-family: var(--font-body) !important; }
        .swal2-confirm {
            background: var(--accent-blue) !important;
            border-radius: 12px !important;
            padding: 12px 30px !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3) !important;
        }
        .swal2-cancel {
            background: rgba(255,255,255,0.05) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 12px !important;
            padding: 12px 30px !important;
            font-weight: 700 !important;
            color: #fff !important;
        }
        .swal2-icon { border-color: rgba(255,255,255,0.1) !important; }

        /* Light Mode SWAL Overrides */
        [data-theme="light"] .swal2-popup {
            background: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            color: #0f172a !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12) !important;
        }
        [data-theme="light"] .swal2-title { color: #0f172a !important; }
        [data-theme="light"] .swal2-html-container { color: #475569 !important; }
        [data-theme="light"] .swal2-cancel {
            background: rgba(0,0,0,0.04) !important;
            border-color: rgba(0,0,0,0.08) !important;
            color: #475569 !important;
        }

    </style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Bonus Management</span>
                <h1>Bonus List</h1>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="glass-card">
            <form action="" method="GET">
                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Bonus Code</label>
                        <input type="text" name="code" class="cus-inp" placeholder="Enter code...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bonus Name</label>
                        <input type="text" name="name" class="cus-inp" placeholder="Enter name...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bonus Type</label>
                        <select name="type" class="cus-sel">
    <option value="">Select Bonus Type</option>
    <option value="mass">Mass</option>
    <option value="single_account">Single account</option>
    <option value="single_use">Single use</option>
    <option value="cashback">Cashback</option>
    <option value="redeposit_bonus">Redeposit Bonus</option>
    <option value="multiple_accounts">Multiple Accounts</option>
</select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bonus Redemption Type</label>
                        <select name="redemption_type" class="cus-sel">
                            <option value="">Select Bonus Redemption Type</option>
                             <option value="" hidden>Select Redemption Type</option>
        <option value="percent_deposit">% of deposit</option>
        <option value="fixed_deposit">Fixed amount on deposit</option>
        <option value="fixed_redemption">Fixed amount on redemption</option>
     
        <option value="cashback_bonus">Cashback bonus</option>
        <option value="referral_bonus">Referral bonus</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Affiliate ID</label>
                        <input type="text" name="affiliate_id" class="cus-inp" placeholder="Enter ID...">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn-search w-100">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Status Tabs -->
        <?php
        $current_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
        
        // Count for tabs
        $count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_bonuses"))['c'];
        $count_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_bonuses WHERE (end_at IS NULL OR end_at >= NOW()) AND status='active'"))['c'];
        $count_expired = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_bonuses WHERE end_at < NOW() OR status='inactive'"))['c'];
        
        // Build tab URLs preserving other GET params
        $params = $_GET;
        unset($params['status_filter']);
        $base_query = http_build_query($params);
        $base_url = '?' . ($base_query ? $base_query . '&' : '');
        ?>
        <div class="status-tabs">
            <a href="<?php echo $base_url; ?>status_filter=all" class="status-tab <?php echo $current_filter == 'all' ? 'active' : ''; ?>">
                All <span class="tab-count"><?php echo $count_all; ?></span>
            </a>
            <a href="<?php echo $base_url; ?>status_filter=active" class="status-tab <?php echo $current_filter == 'active' ? 'active' : ''; ?>">
                Active <span class="tab-count"><?php echo $count_active; ?></span>
            </a>
            <a href="<?php echo $base_url; ?>status_filter=expired" class="status-tab <?php echo $current_filter == 'expired' ? 'active' : ''; ?>">
                Expired <span class="tab-count"><?php echo $count_expired; ?></span>
            </a>
        </div>

        <!-- List Card -->
        <div class="glass-card">
            <div class="r-table-wrap">
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Promotion Name</th>
                            <th>Type</th>
                            <th>Bonus (₹)</th>
                            <th>Wager (x)</th>
                            <th>Status</th>
                            <th>Validity</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where = "WHERE 1=1";
                        if(!empty($_GET['name'])) {
                            $name = mysqli_real_escape_string($conn, $_GET['name']);
                            $where .= " AND name LIKE '%$name%'";
                        }
                        if(!empty($_GET['type'])) {
                            $type = mysqli_real_escape_string($conn, $_GET['type']);
                            $where .= " AND type = '$type'";
                        }
                        // Status filter
                        if ($current_filter == 'active') {
                            $where .= " AND (end_at IS NULL OR end_at >= NOW()) AND status='active'";
                        } elseif ($current_filter == 'expired') {
                            $where .= " AND (end_at < NOW() OR status='inactive')";
                        }

                        $res = mysqli_query($conn, "SELECT *, 
                            (SELECT wagering_multiplier FROM tbl_bonus_providers WHERE bonus_id = tbl_bonuses.id LIMIT 1) as wagering_multiplier 
                            FROM tbl_bonuses $where ORDER BY id DESC");
                        while ($s = mysqli_fetch_assoc($res)) {
                            $is_active = ($s['status'] == 'active' && (is_null($s['end_at']) || strtotime($s['end_at']) >= time()));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <a href="../explore-bonus/index.php?id=<?php echo $s['id']; ?>" class="btn-edit" title="View Details" style="color: var(--status-info);">
                                            <i class='bx bx-search-alt'></i>
                                        </a>
                                        <a href="../create-bonus/index.php?id=<?php echo $s['id']; ?>" class="btn-edit ms-2" title="Edit">
                                            <i class='bx bxs-edit-alt'></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="deleteBonus(<?php echo $s['id']; ?>)" class="btn-delete" title="Delete">
                                            <i class='bx bxs-trash'></i>
                                        </a>
                                    </div>
                                </td>
                                <td><?php echo $s['id']; ?></td>
                                <td style="font-weight: 700; color: #fff;"><?php echo $s['name']; ?></td>
                                <td><span style="text-transform: capitalize; color: var(--accent-blue);"><?php echo str_replace('_', ' ', $s['type']); ?></span></td>
                                <td>₹<?php echo number_format($s['amount']); ?></td>
                                <td><?php echo (float)$s['wagering_multiplier']; ?>x</td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="badge-status badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-expired">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 10px; color: var(--text-dim);">
                                    <?php echo $s['end_at'] ? date('d M, Y', strtotime($s['end_at'])) : 'Ongoing'; ?>
                                </td>
                                <td style="font-size: 10px; color: var(--text-dim);">
                                    <?php echo date('d M, Y', strtotime($s['created_at'])); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        if(mysqli_num_rows($res) == 0) {
                            echo "<tr><td colspan='9' class='text-center' style='padding: 40px; color: var(--text-dim);'>No bonus promotions found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="height: 100px;"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
function deleteBonus(id) {
    Swal.fire({
        title: 'Delete Promotion?',
        text: "Are you sure you want to delete this bonus promotion? This will remove access for all players.",
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
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            title: 'Deleted!',
                            text: res.message,
                            icon: 'success'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error!', 'Could not connect to server', 'error');
                }
            });
        }
    });
}
</script>

</body>
</html>
