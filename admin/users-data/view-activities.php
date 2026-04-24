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
    if($accessObj->isAllowed("access_users_data")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}
$total_recharge_amnt = 0;
if(!isset($_GET['user-id'])){
  echo "request block";
  return;
}else{
  $user_id = mysqli_real_escape_string($conn,$_GET['user-id']);
}

function checkSetData($number){
  $returnVal = $number;
  if(fmod($number, 1) !== 0.00){
    $decimalCount = (int) strpos(strrev($number), ".");
    
    if($decimalCount > 2){
      $modifiedVal = number_format($number, 2, '.', '');
      $returnVal = $modifiedVal;
    }
  }
  
  return $returnVal;
}

$userdata_sql = "SELECT * FROM tblusersdata where tbl_uniq_id='{$user_id}'";
$userdata_result = mysqli_query($conn, $userdata_sql) or die('search failed');

$others_balance = 0;
if (mysqli_num_rows($userdata_result) > 0){
  $res_data = mysqli_fetch_assoc($userdata_result);
  $user_balance = $res_data['tbl_balance'];
  $user_name = $res_data['tbl_full_name']; 
  $user_username = $res_data['tbl_user_name'] ?? 'N/A';
  $others_balance = 0;
}

// $othersdata_sql = "SELECT * FROM tblusersdata where tbl_uniq_id='{$user_id}'";
// $othersdata_result = mysqli_query($conn, $othersdata_sql) or die('search failed');

// if (mysqli_num_rows($othersdata_result) > 0){
//   while ($row = mysqli_fetch_assoc($othersdata_result)){
//     $others_balance += $row['user_bonus_sended'];   
//   }
// }

// Pagination settings
$records_per_page = 10;
$p_rec = isset($_GET['p_rec']) ? (int)$_GET['p_rec'] : 1;
$p_wit = isset($_GET['p_wit']) ? (int)$_GET['p_wit'] : 1;
$p_mat = isset($_GET['p_mat']) ? (int)$_GET['p_mat'] : 1;
$p_spo = isset($_GET['p_spo']) ? (int)$_GET['p_spo'] : 1;
$p_msm = isset($_GET['p_msm']) ? (int)$_GET['p_msm'] : 1;
$p_fsm = isset($_GET['p_fsm']) ? (int)$_GET['p_fsm'] : 1;

if (!function_exists('render_pagination')) {
    function render_pagination($current_page, $total_pages, $param_name, $anchor) {
        if ($total_pages <= 1) return "";
        
        $html = '<nav class="mt-3"><ul class="pagination justify-content-center flex-wrap gap-1" style="border:none;">';
        
        // Previous
        $prev_link = "?" . http_build_query(array_merge($_GET, [$param_name => max(1, $current_page - 1)])) . "#" . $anchor;
        $html .= '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '">
                    <a class="page-link" href="' . $prev_link . '" style="background:var(--input-bg); border:1px solid var(--border-dim); color:var(--text-dim); border-radius:6px; padding:6px 12px; font-size:12px; text-decoration:none;">Prev</a>
                  </li>';
        
        // Page Numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || ($i >= $current_page - 1 && $i <= $current_page + 1)) {
                $link = "?" . http_build_query(array_merge($_GET, [$param_name => $i])) . "#" . $anchor;
                $active_style = ($i == $current_page) ? 'background:linear-gradient(135deg, #10b981, #06b6d4); color:white; border:none;' : 'background:var(--input-bg); border:1px solid var(--border-dim); color:var(--text-dim);';
                $html .= '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">
                            <a class="page-link" href="' . $link . '" style="border-radius:6px; display:flex; align-items:center; justify-content:center; width:30px; height:30px; font-size:12px; padding:0; text-decoration:none; ' . $active_style . '">' . $i . '</a>
                          </li>';
            } elseif ($i == $current_page - 2 || $i == $current_page + 2) {
                $html .= '<li class="page-item disabled"><span class="page-link" style="background:transparent; border:none; color:var(--text-dim); padding: 0 4px; font-size: 11px;">...</span></li>';
            }
        }
        
        // Next
        $next_link = "?" . http_build_query(array_merge($_GET, [$param_name => min($total_pages, $current_page + 1)])) . "#" . $anchor;
        $html .= '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '">
                    <a class="page-link" href="' . $next_link . '" style="background:var(--input-bg); border:1px solid var(--border-dim); color:var(--text-dim); border-radius:6px; padding:6px 12px; font-size:12px; text-decoration:none;">Next</a>
                  </li>';
                  
        $html .= '</ul></nav>';
        return $html;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "../header_contents.php" ?>
  <title><?php echo $APP_NAME; ?> | User Activities</title>
  <link href='../style.css' rel='stylesheet'>
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
<?php include "../components/theme-variables.php"; ?>
body { font-family: var(--font-body) !important; background-color: var(--page-bg) !important; color: var(--text-main); }

.dash-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; padding: 12px 14px;
  border-bottom: 1px solid var(--border-dim);
  margin-bottom: 10px;
}
.dash-title { font-size: 21px; font-weight: 700; color: var(--text-main); font-family: var(--font-body); }
.dash-breadcrumb { font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--status-info); }

.premium-card {
  background: var(--panel-bg); border: 1px solid var(--border-dim);
  border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;
  box-shadow: var(--card-shadow);
}

.r-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
.r-table thead th { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--text-dim); padding: 0 12px 6px; border-bottom: 1px solid var(--border-dim); }
.r-table tbody td { padding: 10px 12px; font-size: 13px; background: var(--table-header-bg); border-top: 1px solid var(--border-dim); border-bottom: 1px solid var(--border-dim); color: var(--text-main); }
.r-table tbody td:first-child { border-radius: 10px 0 0 10px; border-left: 1px solid var(--border-dim); }
.r-table tbody td:last-child { border-radius: 0 10px 10px 0; border-right: 1px solid var(--border-dim); }
.r-table tbody tr:hover td { background: var(--table-row-hover); }

.badge-profit { background: rgba(16,185,129,0.15); color: var(--status-success); border: 1px solid rgba(16,185,129,0.3); border-radius: 20px; padding: 2px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.badge-loss { background: rgba(239,68,68,0.15); color: var(--status-danger); border: 1px solid rgba(239,68,68,0.3); border-radius: 20px; padding: 2px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }

.action-btn-premium {
  background: linear-gradient(135deg, var(--status-success), var(--status-info));
  color: white; border: none; border-radius: 8px; padding: 6px 14px;
  font-weight: 600; font-size: 12px; transition: transform 0.2s;
}
.action-btn-premium:hover { transform: translateY(-2px); filter: brightness(1.1); color: white; }

.back-btn {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--panel-bg); border: 1px solid var(--border-dim);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: var(--text-main); cursor: pointer;
  transition: all 0.2s;
}
.back-btn:hover { background: var(--table-row-hover); transform: translateX(-3px); }

.user-detail-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.user-detail-label { font-size: 11px; color: var(--text-dim); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; width: 90px; }
.user-detail-value { font-size: 15px; font-weight: 700; color: var(--text-main); }


.dash-badge {
  display:flex; align-items:center; gap:5px;
  background: var(--input-bg);
  border:1px solid var(--border-dim);
  border-radius:20px; padding:4px 10px;
  font-size:11px; font-weight:600; color: var(--text-dim);
}
.dash-badge i { color: var(--status-info); font-size:12px; }
.dash-live-dot {
  display:inline-block; width:6px; height:6px; border-radius:50%;
  background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.22);
  animation:livepulse 1.6s infinite;
}
@keyframes livepulse {
  0%,100% { box-shadow:0 0 0 3px rgba(16,185,129,.22); }
  50% { box-shadow:0 0 0 6px rgba(16,185,129,.05); }
}

</style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
  <?php include "../components/side-menu.php"; ?>
  <div class="admin-main-content hide-native-scrollbar">

    <div class="dash-header">
      <div class="d-flex align-items-center gap-3">
        <div class="back-btn" onclick="window.location.href='manager.php?id=<?php echo $user_id; ?>'"><i class='bx bx-left-arrow-alt'></i></div>
        <div>
          <span class="dash-breadcrumb">Admin Panel</span>
          <h1 class="dash-title">User Activity Records</h1>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="dash-badge"><span class="dash-live-dot"></span>&nbsp;Live</div>
        <div class="dash-badge"><i class='bx bx-calendar'></i>&nbsp;<?php echo date('D, M j Y'); ?></div>
      </div>
    </div>
    
    <div class="premium-card" style="padding: 12px 16px; margin-bottom: 12px;">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div style="width: 3px; height: 18px; background: linear-gradient(180deg, var(--accent-teal), var(--accent-cyan)); border-radius: 4px;"></div>
        <h2 class="dash-title" style="font-size: 15px; margin: 0; opacity: 0.8;">User Profile Summary</h2>
      </div>
      
      <div class="row g-2">
        <div class="col-md-4">
          <div class="user-detail-item" style="margin-bottom: 0;">
            <span class="user-detail-label">User ID</span>
            <span class="user-detail-value"><?php echo $user_id; ?></span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="user-detail-item" style="margin-bottom: 0;">
            <span class="user-detail-label">Username</span>
            <span class="user-detail-value" style="color: var(--accent-blue);"><?php echo htmlspecialchars($user_username); ?></span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="user-detail-item" style="margin-bottom: 0;">
            <span class="user-detail-label">Balance</span>
            <span class="user-detail-value" style="color: var(--status-success);">&#8377;<?php echo checkSetData($user_balance); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recharge Activities Section -->
    <div class="premium-card" id="recharge_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: var(--accent-teal); border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Recharge Activities</h2>
        </div>
      </div>

      <div class="table-responsive">
        <table id="recharge_activities_table" class="r-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Amount</th>
              <th>Bank Information</th>
              <th>Status</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count_sql = "SELECT COUNT(*) as total FROM tblusersrecharge WHERE tbl_user_id='{$user_id}'";
            $count_res = mysqli_query($conn, $count_sql);
            $total_rec = mysqli_fetch_assoc($count_res)['total'];
            $total_rec_pages = ceil($total_rec / $records_per_page);
            $offset_rec = ($p_rec - 1) * $records_per_page;

            $sql = "SELECT * FROM tblusersrecharge WHERE tbl_user_id='{$user_id}' ORDER BY id DESC LIMIT {$offset_rec}, {$records_per_page}";
            $result = mysqli_query($conn, $sql) or die('search failed');
        
            if (mysqli_num_rows($result) > 0){
              while ($row = mysqli_fetch_assoc($result)){
                $status_class = ($row['tbl_request_status'] == 'success') ? 'badge-profit' : 'badge-loss';
                ?>
                <tr onclick="window.location.href='../recharge-records/manager.php?uniq-id=<?php echo $row['tbl_uniq_id']; ?>'" style="cursor: pointer;">
                  <td style="font-weight: 600; color: var(--accent-blue);"><i class='bx bx-link-external'></i> <?php echo $row['tbl_uniq_id']; ?></td>
                  <td style="font-weight: 700; color: var(--accent-teal);">&#8377;<?php echo number_format($row['tbl_recharge_amount'], 2); ?></td>
                  <td>
                    <div style="font-size: 12px; font-weight: 600;"><?php echo $row['tbl_recharge_details']; ?></div>
                  </td>
                  <td><span class="<?php echo $status_class; ?>"><?php echo strtoupper($row['tbl_request_status']); ?></span></td>
                  <td style="color: var(--text-dim); font-size: 13px;"><?php echo $row['tbl_time_stamp']; ?></td>
                </tr>
              <?php }
            }else{ ?>
              <tr>
                <td colspan="5" class="text-center py-5" style="color: var(--text-dim);">No recharge records found.</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_rec, $total_rec_pages, 'p_rec', 'recharge_section'); ?>
    </div>

    <!-- Recharge Consolidated Report -->
    <div class="premium-card">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: var(--accent-cyan); border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Recharge Consolidated Report</h2>
        </div>
        <div class="d-flex gap-2">
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #06b6d4, #0891b2);" onclick="exportPDF('<?php echo $user_name; ?>-recharge-summary', 'recharge_summary_table')">
            <i class='bx bxs-file-pdf'></i>
          </button>
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #22d3ee, #06b6d4);" onclick="exportExcel('recharge_summary_table', '<?php echo $user_name; ?>-recharge-summary.xlsx')">
            <i class='bx bxs-spreadsheet'></i>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="recharge_summary_table" class="r-table">
          <thead>
            <tr>
              <th>Total Success Recharge</th>
              <th>Grand Total Recharge Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql = "SELECT count(*) as total_recharge, SUM(tbl_recharge_amount) as total_amount FROM tblusersrecharge WHERE tbl_request_status='success' AND tbl_user_id='{$user_id}'";
            $result = mysqli_query($conn, $sql) or die('search failed');
            $row = mysqli_fetch_assoc($result);
            $total_recharge_count = $row['total_recharge'];
            $total_recharge_amnt = $row['total_amount'] ?? 0;
            ?>
            <tr>
              <td style="font-weight: 600; padding: 20px;"><?php echo $total_recharge_count; ?> Records</td>
              <td style="font-weight: 800; font-size: 18px; color: var(--accent-teal); padding: 20px;">&#8377;<?php echo number_format($total_recharge_amnt, 2); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Withdrawal Activities Section -->
    <div class="premium-card" id="withdrawal_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #f43f5e; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Withdrawal Activities</h2>
        </div>
      </div>

      <div class="table-responsive">
        <table id="withdrawal_activities_table" class="r-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Amount</th>
              <th>Bank Information</th>
              <th>Status</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count_sql = "SELECT COUNT(*) as total FROM tbluserswithdraw WHERE tbl_user_id='{$user_id}'";
            $count_res = mysqli_query($conn, $count_sql);
            $total_wit = mysqli_fetch_assoc($count_res)['total'];
            $total_wit_pages = ceil($total_wit / $records_per_page);
            $offset_wit = ($p_wit - 1) * $records_per_page;

            $sql = "SELECT * FROM tbluserswithdraw WHERE tbl_user_id='{$user_id}' ORDER BY id DESC LIMIT {$offset_wit}, {$records_per_page}";
            $result = mysqli_query($conn, $sql) or die('search failed');
        
            if (mysqli_num_rows($result) > 0){
              while ($row = mysqli_fetch_assoc($result)){
                $status_class = ($row['tbl_request_status'] == 'success') ? 'badge-profit' : 'badge-loss';
                ?>
                <?php
                $wit_details = explode(',', $row['tbl_withdraw_details'] ?? "");
                $wit_bank = $wit_details[3] ?? "N/A";
                $wit_acc = $wit_details[1] ?? "N/A";
                ?>
                <tr onclick="window.location.href='../manual-withdraw-records/manager.php?uniq-id=<?php echo $row['tbl_uniq_id']; ?>'" style="cursor: pointer;">
                  <td style="font-weight: 600; color: var(--status-danger);"><i class='bx bx-link-external'></i> <?php echo $row['tbl_uniq_id']; ?></td>
                  <td style="font-weight: 700; color: #f43f5e;">&#8377;<?php echo number_format($row['tbl_withdraw_amount'], 2); ?></td>
                  <td>
                    <div style="font-size: 11px; color: #94a3b8;"><?php echo $wit_bank; ?></div>
                    <div style="font-size: 12px; font-weight: 600;"><?php echo $wit_acc; ?></div>
                  </td>
                  <td><span class="<?php echo $status_class; ?>"><?php echo strtoupper($row['tbl_request_status']); ?></span></td>
                  <td style="color: var(--text-dim); font-size: 13px;"><?php echo $row['tbl_time_stamp']; ?></td>
                </tr>
              <?php }
            }else{ ?>
              <tr>
                <td colspan="5" class="text-center py-5" style="color: var(--text-dim);">No withdrawal records found.</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_wit, $total_wit_pages, 'p_wit', 'withdrawal_section'); ?>
    </div>

    <!-- Withdrawal Consolidated Report -->
    <div class="premium-card" id="withdrawal_summary_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #fb7185; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Withdrawal Consolidated Report</h2>
        </div>
        <div class="d-flex gap-2">
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #ef4444, #dc2626);" onclick="exportPDF('<?php echo $user_name; ?>-withdrawl-summary', 'withdrawal_summary_table')">
            <i class='bx bxs-file-pdf'></i>
          </button>
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #f87171, #ef4444);" onclick="exportExcel('withdrawal_summary_table', '<?php echo $user_name; ?>-withdrawl-summary.xlsx')">
            <i class='bx bxs-spreadsheet'></i>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="withdrawal_summary_table" class="r-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Transactions</th>
              <th>Withdraw Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql = "SELECT * FROM tbluserswithdraw 
                    WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL 
                    ORDER BY tbl_time_stamp DESC";
            $result = mysqli_query($conn, $sql) or die('Search failed');

            $total_withdraw_amnt_all = 0;
            $grouped_wit_data = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $date_key = date("d M Y", strtotime($row['tbl_time_stamp']));
                $grouped_wit_data[$date_key][] = $row;
            }

            // Paginate grouped withdrawal data
            $total_wsm = count($grouped_wit_data);
            $total_wsm_pages = ceil($total_wsm / $records_per_page);
            $p_wsm = isset($_GET['p_wsm']) ? (int)$_GET['p_wsm'] : 1;
            $offset_wsm = ($p_wsm - 1) * $records_per_page;
            $paginated_wit_data = array_slice($grouped_wit_data, $offset_wsm, $records_per_page, true);

            if (!empty($paginated_wit_data)) {
                foreach ($paginated_wit_data as $date => $records) {
                    $daily_total = 0;
                    foreach ($records as $row) {
                        $daily_total += $row['tbl_withdraw_amount'];
                    }
                    ?>
                    <tr>
                      <td style="font-weight: 600; color: var(--text-main);"><?php echo $date; ?></td>
                      <td><span class="badge bg-dark"><?php echo count($records); ?> transaction<?php echo count($records) > 1 ? 's' : ''; ?></span></td>
                      <td style="font-weight: 700; color: #f87171;">&#8377;<?php echo number_format($daily_total, 2); ?></td>
                    </tr>
                    <?php
                }
                
                // Recalculate grand total for ALL records
                $total_sql = "SELECT SUM(tbl_withdraw_amount) as grand_total FROM tbluserswithdraw WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success'";
                $total_res = mysqli_query($conn, $total_sql);
                $grand_total_row = mysqli_fetch_assoc($total_res);
                echo "<tr style='background: var(--table-header-bg);'><td colspan='2' class='text-end py-3'><strong style='font-size: 12px; text-transform: uppercase; color: var(--text-dim);'>Overall Grand Total:</strong></td><td><strong style='font-size: 20px; color: #f87171;'>&#8377;" . number_format($grand_total_row['grand_total'] ?? 0, 2) . "</strong></td></tr>";
            } else {
                echo "<tr><td colspan='3' class='text-center py-5' style='color: var(--text-dim);'>No consolidated withdrawal data found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_wsm, $total_wsm_pages, 'p_wsm', 'withdrawal_summary_section'); ?>
    </div>
    <!-- Match Activities Section -->
    <div class="premium-card" id="match_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #fca90e; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Match Activities</h2>
        </div>
        <div class="d-flex gap-2">
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #fca90e, #da840b);" onclick="exportPDF('<?php echo $user_name; ?>-match-data', 'match_activities_table')">
            <i class='bx bxs-file-pdf'></i>
          </button>
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #feba3b, #fca90e);" onclick="exportExcel('match_activities_table', '<?php echo $user_name; ?>-match-data.xlsx')">
            <i class='bx bxs-spreadsheet'></i>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="match_activities_table" class="r-table">
          <thead>
            <tr>
              <th>Game Name</th>
              <th>Choice</th>
              <th>P&L</th>
              <th>Bet Amount</th>
              <th>Result</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count_sql = "SELECT COUNT(*) as total FROM tblmatchplayed WHERE tbl_user_id='{$user_id}'";
            $count_res = mysqli_query($conn, $count_sql);
            $total_mat = mysqli_fetch_assoc($count_res)['total'];
            $total_mat_pages = ceil($total_mat / $records_per_page);
            $offset_mat = ($p_mat - 1) * $records_per_page;

            $sql = "SELECT * FROM tblmatchplayed WHERE tbl_user_id='{$user_id}' ORDER BY id DESC LIMIT {$offset_mat}, {$records_per_page}";
            $result = mysqli_query($conn, $sql) or die('search failed');
        
            if (mysqli_num_rows($result) > 0){
              while ($row = mysqli_fetch_assoc($result)){
                $bet_amount = $row['tbl_match_cost'];
                $profit_amount = $row['tbl_match_profit'];
                $result_value = $profit_amount - $bet_amount;
                
                if ($result_value > 0) {
                  $result_label = "Profit " . number_format($result_value, 2);
                  $result_badge = "badge-profit";
                } elseif ($result_value < 0) {
                  $result_label = "Loss " . number_format(abs($result_value), 2);
                  $result_badge = "badge-loss";
                } else {
                  $result_label = "Break Even";
                  $result_badge = "badge bg-secondary";
                }
                ?>
                <tr>
                  <td style="font-weight: 600;"><?php echo $row['tbl_project_name']; ?></td>
                  <td style="font-size: 11px; font-weight: 700; color: var(--accent-blue);">
                    <?php if($row['tbl_selection']){ echo "<span class='badge bg-dark'>".$row['tbl_selection']."</span>"; }else{ echo "—"; } ?>
                  </td>
                  <td style="font-weight: 700; color: var(--text-main);">&#8377;<?php echo number_format($profit_amount, 2); ?></td>
                  <td style="color: var(--text-dim);">&#8377;<?php echo number_format($bet_amount, 2); ?></td>
                  <td><span class="<?php echo $result_badge; ?>"><?php echo $result_label; ?></span></td>
                  <td style="color: var(--text-dim); font-size: 13px;"><?php echo $row['tbl_time_stamp']; ?></td>
                </tr>
              <?php } ?>
              <?php
              // Fetch totals for display (optional: still show totals for all records or just for this user overall)
              $total_sql = "SELECT SUM(tbl_match_cost) as total_bet, SUM(tbl_match_profit) as total_profit FROM tblmatchplayed WHERE tbl_user_id='{$user_id}'";
              $total_res = mysqli_query($conn, $total_sql);
              $total_row = mysqli_fetch_assoc($total_res);
              ?>
              <tr style="background: var(--table-header-bg);">
                <td colspan="6" class="text-end py-3">
                  <div class="d-inline-block text-start me-4">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-dim);">Overall Total Bet</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--text-main);">&#8377;<?php echo number_format($total_row['total_bet'], 2); ?></div>
                  </div>
                  <div class="d-inline-block text-start">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-dim);">Overall Total P&L</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--accent-teal);">&#8377;<?php echo number_format($total_row['total_profit'], 2); ?></div>
                  </div>
                </td>
              </tr>
            <?php }else{ ?>
              <tr>
                <td colspan="6" class="text-center py-5" style="color: var(--text-dim);">No match records found.</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_mat, $total_mat_pages, 'p_mat', 'match_section'); ?>
    </div>

    <!-- Sports Activities Section -->
    <div class="premium-card" id="sports_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #003366; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Sports Activities</h2>
        </div>
        <div class="d-flex gap-2">
           <span class="badge bg-dark d-flex align-items-center px-3" style="font-size: 10px; letter-spacing: 1px;">SABA, LUCKSPORT</span>
        </div>
      </div>

      <?php
      $sports_where = "WHERE tbl_user_id='{$user_id}' AND (LOWER(tbl_project_name) LIKE '%saba%' OR LOWER(tbl_project_name) LIKE '%lucksport%')";
      
      $count_sql = "SELECT COUNT(*) as total FROM tblmatchplayed {$sports_where}";
      $count_res = mysqli_query($conn, $count_sql);
      $total_spo = mysqli_fetch_assoc($count_res)['total'];
      $total_spo_pages = ceil($total_spo / $records_per_page);
      $offset_spo = ($p_spo - 1) * $records_per_page;

      $sports_sql = "SELECT * FROM tblmatchplayed {$sports_where} ORDER BY id DESC LIMIT {$offset_spo}, {$records_per_page}";
      $sports_result = mysqli_query($conn, $sports_sql) or die('Sports data fetch failed');
      ?>

      <div class="table-responsive">
        <table id="sports_activities_table" class="r-table">
          <thead>
            <tr>
              <th>Game Name</th>
              <th>P&L</th>
              <th>Bet Amount</th>
              <th>Result</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
          <?php
          if (mysqli_num_rows($sports_result) > 0) {
            while ($row = mysqli_fetch_assoc($sports_result)) {
              $bet = $row['tbl_match_cost'];
              $profit = $row['tbl_match_profit'];
              $result_val = $profit - $bet;

              if ($result_val > 0) {
                $result_label = "Profit " . number_format($result_val, 2);
                $result_badge = "badge-profit";
              } elseif ($result_val < 0) {
                $result_label = "Loss " . number_format(abs($result_val), 2);
                $result_badge = "badge-loss";
              } else {
                $result_label = "Break Even";
                $result_badge = "badge bg-secondary";
              }
          ?>
            <tr>
              <td style="font-weight: 600;"><?php echo $row['tbl_project_name']; ?></td>
              <td style="font-weight: 700; color: var(--text-main);">&#8377;<?php echo number_format($profit, 2); ?></td>
              <td style="color: var(--text-dim);">&#8377;<?php echo number_format($bet, 2); ?></td>
              <td><span class="<?php echo $result_badge; ?>"><?php echo $result_label; ?></span></td>
              <td style="color: var(--text-dim); font-size: 13px;"><?php echo $row['tbl_time_stamp']; ?></td>
            </tr>
          <?php } ?>
          <?php
          // Overall sports totals
          $total_sq = "SELECT SUM(tbl_match_cost) as s_bet, SUM(tbl_match_profit) as s_prof FROM tblmatchplayed {$sports_where}";
          $total_sr = mysqli_query($conn, $total_sq);
          $total_srow = mysqli_fetch_assoc($total_sr);
          $s_bet_all = $total_srow['s_bet'] ?? 0;
          $s_prof_all = $total_srow['s_prof'] ?? 0;
          ?>
            <tr style="background: var(--table-header-bg);">
              <td colspan="5" class="text-end py-3">
                 <div class="row g-3 justify-content-end">
                    <div class="col-auto text-start">
                      <div style="font-size: 10px; color: var(--text-dim);">OVERALL SPORTS BET</div>
                      <div style="font-size: 16px; font-weight: 800; color: var(--text-main);">&#8377;<?php echo number_format($s_bet_all, 2); ?></div>
                    </div>
                    <div class="col-auto text-start">
                      <div style="font-size: 10px; color: var(--text-dim);">OVERALL SPORTS P&L</div>
                      <div style="font-size: 16px; font-weight: 800; color: var(--text-main);">&#8377;<?php echo number_format($s_prof_all, 2); ?></div>
                    </div>
                 </div>
              </td>
            </tr>
          <?php } else { ?>
            <tr>
              <td colspan="5" class="text-center py-5" style="color: var(--text-dim);">No sports activity records found.</td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_spo, $total_spo_pages, 'p_spo', 'sports_section'); ?>
    </div>

    <!-- Casino Activities Section -->
    <div class="premium-card" id="casino_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #e11d48; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Casino Activities</h2>
        </div>
        <div class="d-flex gap-2">
           <span class="badge bg-dark d-flex align-items-center px-3" style="font-size: 10px; letter-spacing: 1px;">SLOTS, LIVE CASINO</span>
        </div>
      </div>

      <?php
      $casino_where = "WHERE tbl_user_id='{$user_id}' AND LOWER(tbl_project_name) NOT LIKE '%saba%' AND LOWER(tbl_project_name) NOT LIKE '%lucksport%'";
      
      $count_sql = "SELECT COUNT(*) as total FROM tblmatchplayed {$casino_where}";
      $count_res = mysqli_query($conn, $count_sql);
      $total_cas = mysqli_fetch_assoc($count_res)['total'];
      $total_cas_pages = ceil($total_cas / $records_per_page);
      $p_cas = isset($_GET['p_cas']) ? (int)$_GET['p_cas'] : 1;
      $offset_cas = ($p_cas - 1) * $records_per_page;

      $casino_sql = "SELECT * FROM tblmatchplayed {$casino_where} ORDER BY id DESC LIMIT {$offset_cas}, {$records_per_page}";
      $casino_result = mysqli_query($conn, $casino_sql) or die('Casino data fetch failed');
      ?>

      <div class="table-responsive">
        <table id="casino_activities_table" class="r-table">
          <thead>
            <tr>
              <th>Game Name</th>
              <th>Choice</th>
              <th>P&L</th>
              <th>Bet Amount</th>
              <th>Result</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
          <?php
          if (mysqli_num_rows($casino_result) > 0) {
            while ($row = mysqli_fetch_assoc($casino_result)) {
              $bet = $row['tbl_match_cost'];
              $profit = $row['tbl_match_profit'];
              $result_val = $profit - $bet;

              if ($result_val > 0) {
                $result_label = "Profit " . number_format($result_val, 2);
                $result_badge = "badge-profit";
              } elseif ($result_val < 0) {
                $result_label = "Loss " . number_format(abs($result_val), 2);
                $result_badge = "badge-loss";
              } else {
                $result_label = "Break Even";
                $result_badge = "badge bg-secondary";
              }
          ?>
            <tr>
              <td style="font-weight: 600;"><?php echo $row['tbl_project_name']; ?></td>
              <td style="font-size: 11px; font-weight: 700; color: var(--accent-blue);">
                <?php if($row['tbl_selection']){ echo "<span class='badge bg-dark'>".$row['tbl_selection']."</span>"; }else{ echo "—"; } ?>
              </td>
              <td style="font-weight: 700; color: var(--text-main);">&#8377;<?php echo number_format($profit, 2); ?></td>
              <td style="color: var(--text-dim);">&#8377;<?php echo number_format($bet, 2); ?></td>
              <td><span class="<?php echo $result_badge; ?>"><?php echo $result_label; ?></span></td>
              <td style="color: var(--text-dim); font-size: 13px;"><?php echo $row['tbl_time_stamp']; ?></td>
            </tr>
          <?php } ?>
          <?php
          // Overall casino totals
          $total_cq = "SELECT SUM(tbl_match_cost) as c_bet, SUM(tbl_match_profit) as c_prof FROM tblmatchplayed {$casino_where}";
          $total_cr = mysqli_query($conn, $total_cq);
          $total_crow = mysqli_fetch_assoc($total_cr);
          $c_bet_all = $total_crow['c_bet'] ?? 0;
          $c_prof_all = $total_crow['c_prof'] ?? 0;
          ?>
            <tr style="background: var(--table-header-bg);">
              <td colspan="6" class="text-end py-3">
                 <div class="row g-3 justify-content-end">
                    <div class="col-auto text-start">
                      <div style="font-size: 10px; color: var(--text-dim);">OVERALL CASINO BET</div>
                      <div style="font-size: 16px; font-weight: 800; color: var(--text-main);">&#8377;<?php echo number_format($c_bet_all, 2); ?></div>
                    </div>
                    <div class="col-auto text-start">
                      <div style="font-size: 10px; color: var(--text-dim);">OVERALL CASINO P&L</div>
                      <div style="font-size: 16px; font-weight: 800; color: var(--text-main);">&#8377;<?php echo number_format($c_prof_all, 2); ?></div>
                    </div>
                 </div>
              </td>
            </tr>
          <?php } else { ?>
            <tr>
              <td colspan="6" class="text-center py-5" style="color: var(--text-dim);">No casino activity records found.</td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_cas, $total_cas_pages, 'p_cas', 'casino_section'); ?>
    </div>

 <!--sports -->




    <!-- Match Activities Consolidated Report -->
    <div class="premium-card" id="match_summary_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: #8B4513; border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">Match Activities Consolidated Report</h2>
        </div>
        <div class="d-flex gap-2">
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #8B4513, #5D2E0A);" onclick="exportPDF('<?php echo $user_name; ?>-match-summary', 'match_summary_table')">
            <i class='bx bxs-file-pdf'></i>
          </button>
          <button class="action-btn-premium" style="background: linear-gradient(135deg, #A0522D, #8B4513);" onclick="exportExcel('match_summary_table', '<?php echo $user_name; ?>-match-summary.xlsx')">
            <i class='bx bxs-spreadsheet'></i>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="match_summary_table" class="r-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Total Bet</th>
              <th>Total P&L</th>
              <th>Result</th>
              <th>Matches</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql = "SELECT * FROM tblmatchplayed WHERE tbl_user_id='{$user_id}' ORDER BY tbl_time_stamp DESC";
            $result = mysqli_query($conn, $sql) or die('Search failed');

            $grouped_match_data = [];
            $total_profit_amnt_all = 0;

            while ($row = mysqli_fetch_assoc($result)) {
                if (!$row['tbl_time_stamp']) continue;
                $timestamp = strtotime($row['tbl_time_stamp']);
                $date_label = date("d M Y", $timestamp);
                $key = date("Y-m-d", $timestamp);

                if (!isset($grouped_match_data[$key])) {
                    $grouped_match_data[$key] = ['date' => $date_label, 'bet_total' => 0, 'profit_total' => 0, 'count' => 0];
                }

                $grouped_match_data[$key]['bet_total'] += $row['tbl_match_cost'];
                $grouped_match_data[$key]['profit_total'] += $row['tbl_match_profit'];
                $grouped_match_data[$key]['count'] += 1;
                $total_profit_amnt_all += $row['tbl_match_profit'];
            }

            // Paginate grouped data
            $total_msm = count($grouped_match_data);
            $total_msm_pages = ceil($total_msm / $records_per_page);
            $offset_msm = ($p_msm - 1) * $records_per_page;
            $paginated_match_data = array_slice($grouped_match_data, $offset_msm, $records_per_page);

            if (!empty($paginated_match_data)) {
                $idx = $offset_msm + 1;
                foreach ($paginated_match_data as $data) {
                    $res_amt = $data['profit_total'] - $data['bet_total'];
                    $res_type = $res_amt > 0 ? 'Profit' : ($res_amt < 0 ? 'Loss' : 'Break Even');
                    $res_badge = $res_amt > 0 ? 'badge-profit' : ($res_amt < 0 ? 'badge-loss' : 'badge bg-secondary');

                    echo "<tr>
                            <td><span class='badge bg-dark'>{$idx}</span></td>
                            <td style='font-weight: 600; color: var(--text-main);'>{$data['date']}</td>
                            <td style='color: var(--text-main);'>&#8377;" . number_format($data['bet_total'], 2) . "</td>
                            <td style='font-weight: 700; color: var(--text-main);'>&#8377;" . number_format($data['profit_total'], 2) . "</td>
                            <td><span class='{$res_badge}'>{$res_type}<br>&#8377;" . number_format(abs($res_amt), 2) . "</span></td>
                            <td><span class='badge bg-dark'>{$data['count']} matches</span></td>
                          </tr>";
                    $idx++;
                }
                echo "<tr style='background: var(--table-header-bg);'><td colspan='3' class='text-end py-3'><strong style='font-size: 12px; text-transform: uppercase; color: var(--text-dim);'>Overall Grand Total Profit:</strong></td><td colspan='3'><strong style='font-size: 20px; color: var(--accent-teal);'>&#8377;" . number_format($total_profit_amnt_all, 2) . "</strong></td></tr>";
            } else {
                echo "<tr><td colspan='6' class='text-center py-5' style='color: var(--text-dim);'>No consolidated match data found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_msm, $total_msm_pages, 'p_msm', 'match_summary_section'); ?>
    </div>

    <?php
    $data_by_date = [];
    $deposit_sql = "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
    $result = mysqli_query($conn, $deposit_sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
        $data_by_date[$date]['deposit'] = ($data_by_date[$date]['deposit'] ?? 0) + $row['tbl_recharge_amount'];
    }

    $withdraw_sql = "SELECT tbl_withdraw_amount, tbl_time_stamp FROM tbluserswithdraw WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
    $result = mysqli_query($conn, $withdraw_sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
        $data_by_date[$date]['withdraw'] = ($data_by_date[$date]['withdraw'] ?? 0) + $row['tbl_withdraw_amount'];
    }

    $match_sql = "SELECT tbl_match_cost, tbl_match_profit, tbl_time_stamp FROM tblmatchplayed WHERE tbl_user_id='{$user_id}' AND tbl_time_stamp IS NOT NULL";
    $result = mysqli_query($conn, $match_sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
        $data_by_date[$date]['bet'] = ($data_by_date[$date]['bet'] ?? 0) + $row['tbl_match_cost'];
        $data_by_date[$date]['pl'] = ($data_by_date[$date]['pl'] ?? 0) + $row['tbl_match_profit'];
    }
    krsort($data_by_date); // Show latest dates first

    // Paginate summary data
    $total_fsm = count($data_by_date);
    $total_fsm_pages = ceil($total_fsm / $records_per_page);
    $offset_fsm = ($p_fsm - 1) * $records_per_page;
    $paginated_summary_data = array_slice($data_by_date, $offset_fsm, $records_per_page, true);
    ?>

    <!-- Financial Summary Report -->
    <div class="premium-card" id="summary_section">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width: 3px; height: 18px; background: var(--accent-teal); border-radius: 4px;"></div>
          <h2 class="dash-title" style="font-size: 15px; margin: 0;">User Summary Report (Financial)</h2>
        </div>
        <button class="action-btn-premium" onclick="exportExcel('summaryTable', 'user_summary.xlsx')">
          <i class='bx bxs-download me-1'></i> Download Excel
        </button>
      </div>

      <div class="table-responsive">
        <table id="summaryTable" class="r-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Deposit</th>
              <th>Withdraw</th>
              <th>Total Bet</th>
              <th>Total P&L</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (!empty($paginated_summary_data)) {
                foreach ($paginated_summary_data as $date => $values) {
                    echo "<tr>
                            <td style='font-weight: 600; color: var(--text-main);'> " . date("d M Y", strtotime($date)) . "</td>
                            <td style='color: var(--accent-teal); font-weight: 700;'>&#8377;" . number_format($values['deposit'] ?? 0, 2) . "</td>
                            <td style='color: #f87171; font-weight: 700;'>&#8377;" . number_format($values['withdraw'] ?? 0, 2) . "</td>
                            <td style='color: var(--text-main);'>&#8377;" . number_format($values['bet'] ?? 0, 2) . "</td>
                            <td style='font-weight: 700; color: var(--text-main);'>&#8377;" . number_format($values['pl'] ?? 0, 2) . "</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='5' class='text-center py-5' style='color: var(--text-dim);'>No summary data available for this page.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
      <?php echo render_pagination($p_fsm, $total_fsm_pages, 'p_fsm', 'summary_section'); ?>
    </div>

  </div>
</div>

<script src="../script.js?v=8"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
  function exportExcel(tableId, filename = '') {
      var table = document.getElementById(tableId);
      if (!table) {
          alert("Table not found: " + tableId);
          return;
      }
      var wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
      XLSX.writeFile(wb, filename || 'Export.xlsx');
  }

  function exportPDF(title, tableId) {
      // PDF export logic should be handled by your existing exportPDF function in script.js
      // If not, this is where you'd call it.
      if (typeof window.exportPDF === 'function') {
          window.exportPDF(title, tableId);
      } else {
          console.error("exportPDF function not found in script.js");
          alert("PDF background script error. Contact support.");
      }
  }
</script>

</body>
</html>


