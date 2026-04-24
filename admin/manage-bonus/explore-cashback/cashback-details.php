<?php
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

if(!isset($_GET['id'])){
    header('location:../cashback-list.php');
    exit;
}

$promo_id = (int)$_GET['id'];

// 1. Fetch Cashback Promotion Info
$p_sql = "SELECT * FROM tbl_cashback_bonuses WHERE id = $promo_id";
$p_res = mysqli_query($conn, $p_sql);
if(mysqli_num_rows($p_res) == 0){
    header('location:../cashback-list.php');
    exit;
}
$promo = mysqli_fetch_assoc($p_res);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Cashback Details - <?php echo $promo['name']; ?></title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../../components/theme-variables.php"; ?></style>
<style>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .admin-main-content {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(139, 92, 246, 0.05), transparent);
            padding: 12px 20px;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 15px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 8px;
        }
        .dash-title h1 { font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 9px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim); border-radius: 12px;
            padding: 15px; width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4); margin-bottom: 15px;
            position: relative; overflow: hidden;
        }

        .stat-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); border-color: var(--accent-blue); }
        .stat-card .label { font-size: 9px; color: var(--text-dim); text-transform: uppercase; font-weight: 800; margin-bottom: 3px; }
        .stat-card .value { font-size: 16px; font-weight: 800; color: var(--text-main); }

        .btn-list {
            background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim);
            border-radius: 8px; padding: 6px 15px; font-size: 11px; font-weight: 700;
            transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-list:hover { background: var(--accent-blue); border-color: var(--accent-blue); color: #fff; }

    </style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Cashback Management / <a href="../cashback-list.php" style="color: inherit; text-decoration: none;">Promotions</a></span>
                <h1>Promotion Details: <?php echo $promo['name']; ?></h1>
            </div>
            <div class="d-flex gap-2">
                <a href="../cashback-list.php" class="btn-list"><i class='bx bx-list-ul'></i> Cashback List</a>
                <a href="../cashback-list.php" class="btn-list" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2);">
                    <i class='bx bx-arrow-back'></i> Back
                </a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <?php
            // Fetch stats from tbl_cashback_logs for this promotion
            $stats_sql = "SELECT 
                          COUNT(DISTINCT user_id) as total_users,
                          COUNT(*) as total_distributions,
                          SUM(cashback_amount) as total_distributed
                          FROM tbl_cashback_logs WHERE promo_id = $promo_id";
            $stats_res = mysqli_query($conn, $stats_sql);
            $stats = mysqli_fetch_assoc($stats_res);
            
            // Fetch enrollments
            $enroll_sql = "SELECT COUNT(*) as total_enrolled FROM tbl_bonus_redemptions WHERE bonus_id = $promo_id AND status NOT IN ('cancelled', 'expired')";
            $enroll_res = mysqli_query($conn, $enroll_sql);
            $enroll_stats = mysqli_fetch_assoc($enroll_res);
            ?>
            <div class="col-md-8">
                <div class="glass-card h-100 mb-0">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <i class='bx bxs-pie-chart-alt-2' style="font-size: 120px; color: var(--accent-blue);"></i>
                    </div>
                    <div style="font-size: 10px; font-weight: 800; color: var(--accent-blue); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px;">Rule Configuration</div>
                    <div class="row g-2">
                        <div class="col-6 col-md-3 border-end border-secondary">
                            <div style="font-size: 9px; color: var(--text-dim); text-transform: uppercase;">Cashback %</div>
                            <div style="font-weight: 800; color: var(--accent-blue); font-size: 16px;"><?php echo $promo['percentage']; ?>%</div>
                        </div>
                        <div class="col-6 col-md-3 border-end border-secondary">
                            <div style="font-size: 9px; color: var(--text-dim); text-transform: uppercase;">Min Net Loss</div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 16px;">₹<?php echo number_format($promo['min_loss']); ?></div>
                        </div>
                        <div class="col-6 col-md-3 border-end border-secondary">
                            <div style="font-size: 9px; color: var(--text-dim); text-transform: uppercase;">Max Cap</div>
                            <div style="font-weight: 800; color: #10b981; font-size: 16px;">₹<?php echo number_format($promo['max_cashback']); ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="font-size: 9px; color: var(--text-dim); text-transform: uppercase;">Claim Mode</div>
                            <div class="badge bg-primary mt-1" style="font-size: 10px; text-transform: uppercase;"><?php echo $promo['claim_mode']; ?></div>
                        </div>
                    </div>
                    <div class="mt-2 p-2 rounded-xl" style="background: var(--input-bg); font-size: 10px; color: var(--text-dim); border: 1px solid var(--border-dim);">
                        <div class="row">
                            <div class="col-md-6">
                                <i class='bx bx-calendar'></i> <strong>Validity:</strong> 
                                <?php echo date("d M Y", strtotime($promo['start_at'])); ?> - 
                                <?php echo ($promo['end_at'] && $promo['end_at'] != '0000-00-00 00:00:00') ? date("d M Y", strtotime($promo['end_at'])) : 'Ongoing'; ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <i class='bx bx-time-five'></i> <strong>Last Run:</strong> 
                                <?php echo $promo['last_run'] ? date("d M Y, H:i", strtotime($promo['last_run'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="row g-3">
                    <div class="col-12 col-md-12">
                        <div class="stat-card">
                            <div class="label">Total Distributed</div>
                            <div class="value" style="color: #10b981; font-size: 20px;">₹<?php echo number_format($stats['total_distributed'] ?: 0, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="stat-card px-2">
                                    <div class="label text-[9px]">Payouts</div>
                                    <div class="value"><?php echo number_format($stats['total_distributions'] ?: 0); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card px-2">
                                    <div class="label text-[9px]">Paid Users</div>
                                    <div class="value"><?php echo number_format($stats['total_users'] ?: 0); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card px-2">
                                    <div class="label text-[9px]">Enrolled</div>
                                    <div class="value text-warning"><?php echo number_format($enroll_stats['total_enrolled'] ?: 0); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden">
            <div class="px-3 py-2 border-b border-white/5 bg-white/[0.02] flex justify-between align-items-center">
                <h3 style="font-size: 11px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 1px;">Distribution History</h3>
                <span style="font-size: 9px; color: var(--text-dim); font-weight: 700;">Showing last 100 records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 11px; color: var(--text-main);">
                    <thead style="background: var(--table-header-bg);">
                        <tr>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Period Range</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">User Identity</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Net Loss</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Cashback</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Status</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Processed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch Logs for this promo
                        $l_sql = "SELECT l.*, u.tbl_full_name, u.tbl_mobile_num
                                  FROM tbl_cashback_logs l
                                  JOIN tblusersdata u ON l.user_id = u.tbl_uniq_id
                                  WHERE l.promo_id = $promo_id
                                  ORDER BY l.created_at DESC LIMIT 100";
                        $l_res = mysqli_query($conn, $l_sql);
                        
                        if(mysqli_num_rows($l_res) > 0):
                            while($log = mysqli_fetch_assoc($l_res)):
                                 $status = $log['status'];
                                 $is_credited = ($status === 'credited');
                         ?>
                         <tr>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 700; font-size: 10px; color: var(--text-main);"><?php echo date("d M", strtotime($log['period_start'])); ?> - <?php echo date("d M", strtotime($log['period_end'])); ?></div>
                                 <div style="font-size: 9px; color: var(--text-dim);"><?php echo date("Y", strtotime($log['period_start'])); ?> Session</div>
                             </td>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 700; color: var(--text-main);"><?php echo $log['tbl_full_name']; ?></div>
                                 <div style="font-size: 9px; color: var(--text-dim);"><?php echo $log['tbl_mobile_num']; ?> (ID: <?php echo $log['user_id']; ?>)</div>
                             </td>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 700; color: var(--text-main);">₹<?php echo number_format($log['calculated_loss'], 2); ?></div>
                             </td>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 800; color: #10b981;">₹<?php echo number_format($log['cashback_amount'], 2); ?></div>
                             </td>
                             <td class="px-3 py-2">
                                 <?php if($is_credited): ?>
                                     <span class="badge bg-success-subtle text-success border border-success/20" style="font-size: 9px; text-transform: uppercase;">Credited</span>
                                 <?php else: ?>
                                     <span class="badge bg-warning-subtle text-warning border border-warning/20" style="font-size: 9px; text-transform: uppercase;">Pending Claim</span>
                                 <?php endif; ?>
                             </td>
                            <td class="px-3 py-2" style="color: var(--text-dim); font-size: 10px;">
                                <?php echo date("d M Y, H:i", strtotime($log['created_at'])); ?>
                            </td>
                         </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center p-4" style="color: var(--text-dim);">No distributions have been processed for this promotion yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="glass-card p-0 overflow-hidden mt-3">
            <div class="px-3 py-2 border-b border-white/5 bg-white/[0.02] flex justify-between align-items-center">
                <h3 style="font-size: 11px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 1px;">Enrolled Players</h3>
                <span style="font-size: 9px; color: var(--text-dim); font-weight: 700;">Players waiting for calculation</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 11px; color: var(--text-main);">
                    <thead style="background: var(--table-header-bg);">
                        <tr>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">User Identity</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Current Balance</th>
                            <th class="px-3 py-2" style="font-size: 9px; text-transform: uppercase; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);">Enrolled At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch Enrollments
                        $e_sql = "SELECT r.*, u.tbl_full_name, u.tbl_mobile_num, u.tbl_balance
                                  FROM tbl_bonus_redemptions r
                                  JOIN tblusersdata u ON r.user_id = u.tbl_uniq_id
                                  WHERE r.bonus_id = $promo_id AND r.status NOT IN ('cancelled', 'expired')
                                  ORDER BY r.created_at DESC LIMIT 100";
                        $e_res = mysqli_query($conn, $e_sql);
                        
                        if(mysqli_num_rows($e_res) > 0):
                            while($enroll = mysqli_fetch_assoc($e_res)):
                         ?>
                         <tr>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 700; color: var(--text-main);"><?php echo $enroll['tbl_full_name']; ?></div>
                                 <div style="font-size: 9px; color: var(--text-dim);"><?php echo $enroll['tbl_mobile_num']; ?> (ID: <?php echo $enroll['user_id']; ?>)</div>
                             </td>
                             <td class="px-3 py-2">
                                 <div style="font-weight: 700; color: var(--text-main);">₹<?php echo number_format($enroll['tbl_balance'], 2); ?></div>
                             </td>
                            <td class="px-3 py-2" style="color: var(--text-dim); font-size: 10px;">
                                <?php echo date("d M Y, H:i", strtotime($enroll['created_at'])); ?>
                            </td>
                         </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center p-4" style="color: var(--text-dim);">No players have enrolled in this promotion yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

</body>
</html>
