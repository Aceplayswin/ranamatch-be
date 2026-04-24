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
if($accessObj->validate()!="true"){
    header('location:../logout-account');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "../header_contents.php" ?>
  <title><?php echo $APP_NAME; ?>: Control Matches</title>
  <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* Page specific variable overrides only if needed */
:root {
  --font-display: 'Archivo Black', sans-serif;
  --accent-blue:  var(--status-info);
  --accent-green: var(--status-success);
}

body { 
    font-family: var(--font-body) !important;
    background-color: var(--page-bg) !important;
    min-height: 100vh;
    color: var(--text-main);
}

.dash-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px; padding: 20px 14px 18px;
  border-bottom: 1px solid transparent;
  border-image: linear-gradient(90deg, #3b82f6, #06b6d4, #10b981) 1;
  margin-bottom: 2px;
}
.dash-header-left  { display: flex; align-items: center; gap: 14px; }
.dash-header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }


.dash-breadcrumb {
  font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase;
  background:linear-gradient(90deg, #3b82f6, #06b6d4);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.dash-title {
  font-size:26px; font-weight:700; letter-spacing:-0.5px;
  color: var(--text-main); line-height:1.2; display:block;
  font-family:var(--font-body);
}

.dash-badge {
  display:flex; align-items:center; gap:6px;
  background: var(--input-bg);
  border:1px solid var(--border-dim);
  border-radius:22px; padding:6px 14px;
  font-size:12px; font-weight:600; color: var(--text-dim);
}
.dash-badge i { color:#3b82f6; font-size:14px; }

.game-section {
  background: var(--panel-bg);
  border: 1px solid var(--border-dim);
  border-radius: 16px;
  padding: 24px;
  margin-top: 10px;
  box-shadow: var(--card-shadow);
}

.section-title {
  font-size: 14px; font-weight: 700; color: var(--text-main);
  display: flex; align-items: center; gap: 10px; margin-bottom: 24px;
  text-transform: uppercase; letter-spacing: 1px;
}
.title-bar {
  width: 4px; height: 20px; border-radius: 4px;
  background: linear-gradient(180deg, #3b82f6, #06b6d4); flex-shrink: 0;
}

.r-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
.r-table thead th {
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--text-dim); padding: 0 16px 8px;
  border-bottom: 1px solid var(--border-dim);
}
.r-table tbody td {
  padding: 14px 16px; font-size: 14px; font-weight: 500; color: var(--text-main);
  background: var(--table-header-bg);
  border-top: 1px solid var(--border-dim);
  border-bottom: 1px solid var(--border-dim);
  transition: all 0.2s;
  cursor: pointer;
}
.r-table tbody td:first-child { border-radius: 12px 0 0 12px; border-left: 1px solid var(--border-dim); }
.r-table tbody td:last-child  { border-radius: 0 12px 12px 0; border-right: 1px solid var(--border-dim); }
.r-table tbody tr:hover td { 
    background: rgba(59, 130, 246, 0.08); 
    color: #fff;
    border-color: rgba(59, 130, 246, 0.2);
}

.rn {
  display:inline-flex; align-items:center; justify-content:center;
  width:28px; height:28px; border-radius:8px;
  background: var(--input-bg); font-size:12px; font-weight:700; color: var(--status-info);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
.status-inactive { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

.advanced-section {
    margin-top: 30px;
    padding: 24px;
    background: var(--input-bg);
    border: 1px solid var(--border-dim);
    border-radius: 16px;
}
.advanced-text { font-size: 14px; color: #94a3b8; margin-bottom: 16px; font-weight: 500; }
.action-btn {
    background: var(--input-bg);
    border: 1px solid var(--border-dim);
    color: var(--text-main);
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.action-btn:hover {
    background: var(--accent-blue);
    border-color: var(--accent-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    color: #fff;
}
.btn-restart { border-color: rgba(239, 68, 68, 0.2); }
.btn-restart:hover { background: #ef4444; border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

</style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div>
                    <span class="dash-breadcrumb">Admin Panel > Control</span>
                    <span class="dash-title">Control Matches</span>
                </div>
            </div>
            <div class="dash-header-right">
                <div class="dash-badge"><i class='bx bx-calendar'></i>&nbsp;<?php echo date('D, M j Y'); ?></div>
                <div class="dash-badge"><i class='bx bx-time-five'></i>&nbsp;<?php echo date('h:i A'); ?></div>
            </div>
        </div>
           
        <div class="game-section">
            <div class="section-title">
                <span class="title-bar"></span>
                All Games List
            </div>
            
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width:8%">No</th>
                        <th>Game Name</th>
                        <th style="width:20%">Run Time</th>
                        <th style="width:15%">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                  $indexVal = 1;
                  $games_sql = "SELECT * FROM tblgamecontrols WHERE tbl_service_status='true'";
                  $games_result = mysqli_query($conn, $games_sql) or die('search failed');
              
                  if (mysqli_num_rows($games_result) > 0){
                    while ($row = mysqli_fetch_assoc($games_result)){
                   
                     $service_name = $row['tbl_service_name'];
                     $service_time = $row['tbl_service_times'];
                     $service_status = $row['tbl_service_status'];
                     $service_time_arr = explode(",", $service_time);
                    ?>
                     <tr onclick="window.location.href='control-game.php?game=<?php echo $service_name; ?>'">
                        <td><span class="rn"><?php echo $indexVal; ?></span></td>
                        <td style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($service_name); ?></td>
                        <td>
                            <i class='bx bx-timer' style='color: var(--accent-blue);'></i> 
                            <?php if(((float)$service_time_arr[0])/60 < 1){ echo $service_time_arr[0].' sec'; }else{ echo (((float)$service_time_arr[0])/60).' min'; } ?>
                        </td>
                        <td>
                            <?php if($service_status=="true"): ?>
                                <span class="status-badge status-active"><i class='bx bxs-check-circle'></i> Active</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive"><i class='bx bxs-x-circle'></i> In-Active</span>
                            <?php endif; ?>
                        </td>
                     </tr>
                <?php $indexVal++; }} ?>
                </tbody>
            </table>
        </div>

        <div class="advanced-section">
            <div class="advanced-text">
                <i class='bx bx-shield-quarter' style='color: var(--accent-blue); font-size: 18px;'></i>
                System Maintenance & Advanced Configuration
            </div>
            <div class="d-flex gap-3">
                <button class="action-btn btn-restart" onclick="restartGame('global')">
                    <i class='bx bx-refresh'></i> Restart All Games
                </button>
                <a href="update-game-settings.php" class="action-btn">
                    <i class='bx bx-cog'></i> Advanced Settings
                </a>
            </div>
        </div>

    </div>
</div>

<?php include '../scripts/script-control-game.php'; ?>
<script src="../script.js?v=1"></script>
</body>
</html>