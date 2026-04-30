<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_gift")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Cashback Promotions</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
<?php include "../components/theme-variables.php"; ?>
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

    </style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Cashback Management</span>
                <h1>Cashback Promotions</h1>
            </div>
        </div>

        <!-- Legend / Info -->
        <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 15px; background: rgba(59, 130, 246, 0.05); padding: 12px 20px; border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.1);">
            <i class='bx bx-info-circle' style="font-size: 20px; color: var(--accent-blue);"></i>
            <span style="font-size: 12px; color: var(--text-dim); font-weight: 600;">
                <b style="color: #fff;">Tip:</b> Use the <span style="color: #10b981;"><i class='bx bx-play-circle'></i> Process</span> button to manually calculate and distribute payouts to players based on their net losses.
            </span>
        </div>

        <!-- Filter Card -->
        <div class="glass-card">
            <form action="" method="GET">
                <div class="row g-4 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search Promotion Name</label>
                        <input type="text" name="name" class="cus-inp" placeholder="e.g. Weekly 10%" value="<?php echo $_GET['name']??''; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn-search w-100">Apply Filters</button>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn-search w-100" style="background: #10b981;" onclick="processCashback(0)">
                            <i class='bx bx-play-circle'></i> Process All Now
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Status Tabs -->
        <?php
        $current_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
        $count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_cashback_bonuses"))['c'];
        $count_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_cashback_bonuses WHERE status='active'"))['c'];
        $count_inactive = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_cashback_bonuses WHERE status='inactive'"))['c'];
        
        $params = $_GET;
        unset($params['status_filter']);
        $base_url = '?' . http_build_query($params) . (count($params) > 0 ? '&' : '');
        ?>
        <div class="status-tabs">
            <a href="<?php echo $base_url; ?>status_filter=all" class="status-tab <?php echo $current_filter == 'all' ? 'active' : ''; ?>">
                All <span class="tab-count"><?php echo $count_all; ?></span>
            </a>
            <a href="<?php echo $base_url; ?>status_filter=active" class="status-tab <?php echo $current_filter == 'active' ? 'active' : ''; ?>">
                Active <span class="tab-count"><?php echo $count_active; ?></span>
            </a>
            <a href="<?php echo $base_url; ?>status_filter=inactive" class="status-tab <?php echo $current_filter == 'inactive' ? 'active' : ''; ?>">
                Inactive <span class="tab-count"><?php echo $count_inactive; ?></span>
            </a>
        </div>

        <!-- List Card -->
        <div class="glass-card">
            <div class="r-table-wrap">
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Banner</th>
                            <th>ID</th>
                            <th>Promotion Name</th>
                            <th>%</th>
                            <th>Min Loss (₹)</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th>Last Run</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where = "WHERE 1=1";
                        if(!empty($_GET['name'])) {
                            $name = mysqli_real_escape_string($conn, $_GET['name']);
                            $where .= " AND name LIKE '%$name%'";
                        }
                        if ($current_filter == 'active') {
                            $where .= " AND status='active'";
                        } elseif ($current_filter == 'inactive') {
                            $where .= " AND status='inactive'";
                        }

                        $res = mysqli_query($conn, "SELECT * FROM tbl_cashback_bonuses $where ORDER BY id DESC");
                        while ($s = mysqli_fetch_assoc($res)) {
                            $is_active = ($s['status'] == 'active');
                            $pattern = json_decode($s['redemption_pattern'] ?? '{}', true);
                            $readable_days = array_keys($pattern);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <a href="explore-cashback/cashback-details.php?id=<?php echo $s['id']; ?>" class="btn-edit" title="View Details" style="color: var(--status-info);">
                                            <i class='bx bx-search-alt'></i>
                                        </a>
                                        <a href="create-cashback/index.php?id=<?php echo $s['id']; ?>" class="btn-edit ms-2" title="Edit">
                                            <i class='bx bxs-edit-alt'></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="processCashback(<?php echo $s['id']; ?>)" class="btn-edit ms-2" style="color: #10b981;" title="Process Now">
                                            <i class='bx bx-play-circle'></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="deleteCashback(<?php echo $s['id']; ?>)" class="btn-delete" title="Delete">
                                            <i class='bx bxs-trash'></i>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($s['image_path'])): ?>
                                        <img src="../../<?php echo $s['image_path']; ?>" style="width: 50px; height: 25px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-dim);">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 25px; background: rgba(255,255,255,0.05); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: var(--text-dim);">No Img</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $s['id']; ?></td>
                                <td style="font-weight: 700; color: #fff;"><?php echo $s['name']; ?></td>
                                <td><span style="color: var(--accent-blue); font-weight: 800;"><?php echo $s['percentage']; ?>%</span></td>
                                <td>₹<?php echo number_format($s['min_loss']); ?></td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="badge-status badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-expired">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11px;">
                                    <div style="font-weight: 700; color: var(--text-main); margin-bottom: 2px;">
                                        <?php echo count($readable_days) > 0 ? implode(', ', $readable_days) : 'No days set'; ?>
                                    </div>
                                    <div style="color: var(--text-dim); font-size: 10px;">
                                        Valid: <?php echo (!empty($s['start_at']) && $s['start_at'] != '0000-00-00 00:00:00') ? date('d M', strtotime($s['start_at'])) : 'Evergreen'; ?> 
                                        - <?php echo (!empty($s['end_at']) && $s['end_at'] != '0000-00-00 00:00:00') ? date('d M, Y', strtotime($s['end_at'])) : 'Open'; ?>
                                    </div>
                                </td>
                                <td><?php echo $s['last_run'] ? date('d M, H:i', strtotime($s['last_run'])) : '-'; ?></td>
                            </tr>
                            <?php
                        }
                        if(mysqli_num_rows($res) == 0) {
                            echo "<tr><td colspan='8' class='text-center' style='padding: 40px; color: var(--text-dim);'>No cashback promotions found.</td></tr>";
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
function deleteCashback(id) {
    Swal.fire({
        title: 'Delete Promotion?',
        text: "This will remove the cashback rule entirely.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete_cashback.php',
                type: 'POST',
                data: { id: id },
                success: function(response) {
                    location.reload();
                }
            });
        }
    });
}

function processCashback(id) {
    const title = id === 0 ? 'Process ALL Cashback?' : 'Process This Cashback?';
    const text = id === 0 ? 'Calculations will run for ALL active players.' : 'Calculations will run for this specific promotion.';

    Swal.fire({
        title: title,
        text: text,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Yes, start Now'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            $.ajax({
                url: 'ajax_process_cashback.php' + (id > 0 ? '?id=' + id : ''),
                type: 'GET',
                success: function(response) {
                    Swal.fire({
                        title: 'Finished!',
                        text: 'Engine finished successfully.',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Failed to connect to the processing engine.', 'error');
                }
            });
        }
    });
}
</script>

</body>
</html>
