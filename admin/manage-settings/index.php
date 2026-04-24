<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); // Disable PHP's automatic session cache headers

define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_settings")=="false"){
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
    <title><?php echo $APP_NAME; ?>: System Settings</title>
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
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100%; border-radius: 20px; border: 1px solid var(--border-dim);
            background: var(--panel-bg); box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            padding: 24px; overflow-y: scroll;
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

        /* r-table system */
        .r-table-wrapper {
            background: rgba(255,255,255,0.02); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: hidden;
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(255,255,255,0.03); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
        }
        .r-table td {
            padding: 16px 20px; font-size: 14px; color: var(--text-main);
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
            cursor: pointer;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: rgba(255,255,255,0.02); }

        .status-pill {
            padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-active { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid var(--accent-emerald); }
        .status-inactive { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid var(--accent-rose); }
        
        .service-name { font-weight: 700; color: var(--text-main); }
        .service-value-text { font-family: monospace; font-size: 13px; color: var(--text-dim); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Configuration Control</span>
                <h1>All Settings</h1>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-modern btn-primary-modern" onclick="window.location.reload()">
                    <i class='bx bx-refresh'></i> Refresh Settings
                </button>
            </div>
        </div>

        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 80px">No</th>
                        <th>Service Name</th>
                        <th>Current Value</th>
                        <th style="width: 100px; text-align: right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $indexVal = 1;
                    $settings_records_sql = "SELECT * FROM tblservices ORDER BY id DESC";
                    $settings_records_result = mysqli_query($conn, $settings_records_sql);
                
                    if ($settings_records_result && mysqli_num_rows($settings_records_result) > 0){
                        while ($row = mysqli_fetch_assoc($settings_records_result)){
                            $setting_id = $row['tbl_service_name'];
                        ?>
                        <tr onclick="window.location.href='manager.php?id=<?php echo $setting_id; ?>'">
                            <td><span style="color: var(--text-dim); font-weight: 700;">#<?php echo $indexVal; ?></span></td>
                            <td><span class="service-name"><?php echo $row['tbl_service_name']; ?></span></td>
                            <td>
                                <?php
                                $val = $row['tbl_service_value'];
                                if($val == "true") {
                                    echo '<span class="status-pill status-active">ON</span>';
                                } else if($val == "false") {
                                    echo '<span class="status-pill status-inactive">OFF</span>';
                                } else {
                                    $displayVal = (strlen($val) > 40) ? substr($val, 0, 40) . '...' : $val;
                                    echo '<span class="service-value-text">' . htmlspecialchars($displayVal) . '</span>';
                                }
                                ?>
                            </td>
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
                                No settings records found!
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