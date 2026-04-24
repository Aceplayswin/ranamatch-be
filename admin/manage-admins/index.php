<?php
header("Cache-Control: no cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_admins")=="false"){
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
    <title><?php echo $APP_NAME; ?>: Admins Management</title>
    <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
<?php include "../components/theme-variables.php"; ?>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

        .btn-green-modern { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid var(--accent-emerald); }
        .btn-green-modern:hover { background: var(--accent-emerald); color: #fff !important; }

        /* r-table system */
        .r-table-wrapper {
            background: rgba(255,255,255,0.02); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: hidden;
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: var(--table-header-bg); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
        }
        .r-table td {
            padding: 16px 20px; font-size: 14px; color: var(--text-main);
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
            cursor: pointer;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: var(--table-row-hover); }

        .user-id-badge {
            background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);
            padding: 4px 10px; border-radius: 6px; font-family: monospace; font-weight: 700;
        }
        .time-text { font-size: 12px; color: var(--text-dim); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">System Control</span>
                <h1>Admins</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="add-admin" class="btn-modern btn-green-modern">
                    <i class='bx bx-plus'></i> Add Admin
                </a>
                <button class="btn-modern p-0" style="background:transparent; color:var(--text-dim)" onclick="window.location.reload()">
                    <i class='bx bx-refresh ft-sz-25'></i>
                </button>
            </div>
        </div>

        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 80px">No</th>
                        <th>Admin ID</th>
                        <th>Created Date</th>
                        <th style="width: 100px; text-align: right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $indexVal = 1;
                    $settings_records_sql = "SELECT * FROM tbladmins ORDER BY id DESC";
                    $settings_records_result = mysqli_query($conn, $settings_records_sql);
                
                    if ($settings_records_result && mysqli_num_rows($settings_records_result) > 0){
                        while ($row = mysqli_fetch_assoc($settings_records_result)){
                            $request_uniq_id = $row['tbl_user_id'];
                        ?>
                        <tr onclick="window.location.href='manager.php?user-id=<?php echo $request_uniq_id; ?>'">
                            <td><span style="color: var(--text-dim); font-weight: 700;">#<?php echo $indexVal; ?></span></td>
                            <td><span class="user-id-badge"><?php echo $row['tbl_user_id']; ?></span></td>
                            <td><span class="time-text"><?php echo $row['tbl_date_time']; ?></span></td>
                            <td style="text-align: right;">
                                <i class='bx bx-chevron-right' style="font-size: 20px; color: var(--text-dim);"></i>
                            </td>
                        </tr>
                        <?php $indexVal++; 
                        }
                    } else { ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                <i class='bx bx-ghost' style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                No admins found!
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../script.js?v=2"></script>
</body>
</html>