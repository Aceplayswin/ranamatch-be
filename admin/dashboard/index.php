<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';
include '../../security/headers-security.php';

session_start();
date_default_timezone_set("Asia/Kolkata");
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
  header('location:../logout-account');
}
$curr_date = date('d-m-Y');
$curr_time = date('h:i:s a');
$curr_date_time = $curr_date . ' ' . $curr_time;

$anlyt_total_users = 0;
$anlyt_last_active_users = 0;
$anlyt_number_recharge = 0;
$anlyt_number_withdraw = 0;
$anlyt_total_recharge = 0;
$anlyt_total_withdraw = 0;
$grand_sports_total_bet = 0;
$grand_sports_total_profit = 0;
$grand_sports_profit_amount = 0;
$grand_sports_loss_amount = 0;

function getTimeDiff($datetime_1, $datetime_2)
{
  $timestamp1 = strtotime($datetime_1);
  $timestamp2 = strtotime($datetime_2);
  return $timestamp1 - $timestamp2;
}

$search_user_sql = "SELECT tbl_last_active_date,tbl_last_active_time FROM tblusersdata WHERE tbl_account_status='true'";
$search_user_result = mysqli_query($conn, $search_user_sql) or die($conn->error);
while ($search_user_row = mysqli_fetch_assoc($search_user_result)) {
  $anlyt_total_users++;
  $activeDateTime = $search_user_row['tbl_last_active_date'] . ' ' . $search_user_row['tbl_last_active_time'];
  if (getTimeDiff($curr_date_time, $activeDateTime) <= 1800) {
    $anlyt_last_active_users++;
  }
}

$sports_sql = "SELECT tbl_match_cost, tbl_match_profit, tbl_match_status FROM tblmatchplayed WHERE LOWER(REPLACE(tbl_project_name, ' ', '')) IN ('sabasports', 'lucksport', 'lucksportgaming', 'lucksports', '9wickets', 'esports')";
$sports_result = mysqli_query($conn, $sports_sql) or die($conn->error);
while ($sports_row = mysqli_fetch_assoc($sports_result)) {
  $grand_sports_total_bet += $sports_row['tbl_match_cost'];
  $grand_sports_total_profit += $sports_row['tbl_match_profit'];
  $m_status = strtolower($sports_row['tbl_match_status']);
  if ($m_status === 'profit' || $m_status === 'win' || $m_status === 'won' || $m_status === 'cashout') {
    $grand_sports_profit_amount += $sports_row['tbl_match_profit'];
  } elseif ($m_status === 'loss' || $m_status === 'lost') {
    $grand_sports_loss_amount += $sports_row['tbl_match_cost'];
  }
}

function formatAmount($amount)
{
  return $amount > 1000000 ? "10 Lac+" : number_format($amount, 0, ".", "");
}
function calculatePercentage($amount, $total_cost)
{
  return ($total_cost > 0) ? round(($amount / $total_cost) * 100, 2) . "%" : "0%";
}

$grand_sports_total_bet = formatAmount($grand_sports_total_bet);
$grand_sports_total_profit = formatAmount($grand_sports_total_profit);
$grand_sports_profit_amount = formatAmount($grand_sports_profit_amount);
$grand_sports_loss_amount = formatAmount($grand_sports_loss_amount);

$current_date = date('Y-m-d');
$yesterday_date = date('Y-m-d', strtotime('-1 day'));

$search_recharge_sql = "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge WHERE tbl_request_status = 'success'";
$search_recharge_result = mysqli_query($conn, $search_recharge_sql) or die($conn->error);
$today_total_recharge = 0;
$yesterday_total_recharge = 0;
while ($search_recharge_row = mysqli_fetch_assoc($search_recharge_result)) {
  if (!empty($search_recharge_row['tbl_time_stamp'])) {
    $timestamp = strtotime($search_recharge_row['tbl_time_stamp']);
    if ($timestamp !== false) {
      $date_only = date('Y-m-d', $timestamp);
      if ($date_only === $current_date) {
        $today_total_recharge += $search_recharge_row['tbl_recharge_amount'];
      } elseif ($date_only === $yesterday_date) {
        $yesterday_total_recharge += $search_recharge_row['tbl_recharge_amount'];
      }

      // Monthly calculation
      if (date('Y-m', $timestamp) === date('Y-m')) {
        $anlyt_total_recharge += $search_recharge_row['tbl_recharge_amount'];
      }
    }
  }
}
$today_total_recharge = $today_total_recharge > 1000000 ? "10 Lac+" : number_format($today_total_recharge, 2, ".", "");
$yesterday_total_recharge = $yesterday_total_recharge > 1000000 ? "10 Lac+" : number_format($yesterday_total_recharge, 2, ".", "");
$anlyt_total_recharge = $anlyt_total_recharge > 1000000 ? "10 Lac+" : number_format($anlyt_total_recharge, 2, ".", "");

$search_withdraw_sql = "SELECT tbl_withdraw_amount,tbl_time_stamp FROM tbluserswithdraw WHERE tbl_request_status='success'";
$search_withdraw_result = mysqli_query($conn, $search_withdraw_sql) or die('search failed2');
while ($search_withdraw_row = mysqli_fetch_assoc($search_withdraw_result)) {
  if (!empty($search_withdraw_row['tbl_time_stamp'])) {
    $timestamp = strtotime($search_withdraw_row['tbl_time_stamp']);
    if ($timestamp !== false) {
      if (date('Y') == date("Y", $timestamp) && date('m') == date("m", $timestamp)) {
        $anlyt_number_withdraw++;
        $anlyt_total_withdraw += $search_withdraw_row['tbl_withdraw_amount'];
      }
    }
  }
}
$anlyt_total_withdraw = $anlyt_total_withdraw > 1000000 ? "1000000+" : number_format($anlyt_total_withdraw, 0, ".", "");

$search_services_sql = "SELECT * FROM tblservices";
$search_services_result = mysqli_query($conn, $search_services_sql) or die('search failed2');
$service_app_status = "";
$service_sms_token = "";
$service_payment_option = "";
$service_telegram_option = "";
$service_impmessage_option = "";
while ($search_services_row = mysqli_fetch_assoc($search_services_result)) {
  if ($search_services_row['tbl_service_name'] == "APP_STATUS") {
    $service_app_status = $search_services_row['tbl_service_value'];
  } else if ($search_services_row['tbl_service_name'] == "SMS_TOKEN") {
    $service_sms_token = $search_services_row['tbl_service_value'];
  } else if ($search_services_row['tbl_service_name'] == "PAYMENT_OPTION") {
    $service_payment_option = $search_services_row['tbl_service_value'];
  } else if ($search_services_row['tbl_service_name'] == "TELEGRAM_URL") {
    $service_telegram_option = $search_services_row['tbl_service_value'];
  } else if ($search_services_row['tbl_service_name'] == "IMP_MESSAGE") {
    $service_impmessage_option = $search_services_row['tbl_service_value'];
  }
}

$selected_date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$total_recharge = 0;
$total_withdraw = 0;
$sql = "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge WHERE tbl_request_status = 'success'";
$sql2 = "SELECT tbl_withdraw_amount, tbl_time_stamp FROM tbluserswithdraw WHERE tbl_request_status = 'success'";
$result = mysqli_query($conn, $sql) or die($conn->error);
$result2 = mysqli_query($conn, $sql2) or die($conn->error);
while ($row = mysqli_fetch_assoc($result)) {
  $ts = strtotime($row['tbl_time_stamp']);
  if ($ts !== false && date('Y-m-d', $ts) === $selected_date) {
    $total_recharge += $row['tbl_recharge_amount'];
  }
}
while ($row2 = mysqli_fetch_assoc($result2)) {
  $ts = strtotime($row2['tbl_time_stamp']);
  if ($ts !== false && date('Y-m-d', $ts) === $selected_date) {
    $total_withdraw += $row2['tbl_withdraw_amount'];
  }
}
$total_recharge = $total_recharge > 1000000 ? "10 Lac+" : number_format($total_recharge, 0, ".", "");
$total_withdraw = $total_withdraw > 1000000 ? "10 Lac+" : number_format($total_withdraw, 0, ".", "");

$search_withdraw_sql2 = "SELECT tbl_withdraw_amount, tbl_time_stamp FROM tbluserswithdraw WHERE tbl_request_status='success'";
$search_withdraw_result2 = mysqli_query($conn, $search_withdraw_sql2) or die($conn->error);
$today_total_withdraw = 0;
$yesterday_total_withdraw = 0;
while ($search_withdraw_row = mysqli_fetch_assoc($search_withdraw_result2)) {
  if (!empty($search_withdraw_row['tbl_time_stamp'])) {
    $timestamp = strtotime($search_withdraw_row['tbl_time_stamp']);
    if ($timestamp !== false) {
      $date_only = date('Y-m-d', $timestamp);
      if ($date_only === $current_date) {
        $today_total_withdraw += $search_withdraw_row['tbl_withdraw_amount'];
      } elseif ($date_only === $yesterday_date) {
        $yesterday_total_withdraw += $search_withdraw_row['tbl_withdraw_amount'];
      }
    }
  }
}
$today_total_withdraw = $today_total_withdraw > 1000000 ? "10 Lac+" : number_format($today_total_withdraw, 0, ".", "");
$yesterday_total_withdraw = $yesterday_total_withdraw > 1000000 ? "10 Lac+" : number_format($yesterday_total_withdraw, 0, ".", "");

$search_sql = "SELECT tbl_match_cost, tbl_match_profit, tbl_time_stamp, tbl_match_status FROM tblmatchplayed WHERE LOWER(tbl_match_status) IN ('profit', 'loss', 'cashout', 'win', 'won', 'lost')";
$search_result = mysqli_query($conn, $search_sql) or die($conn->error);
$today_total_profit = 0;
$yesterday_total_profit = 0;
$today_total_loss = 0;
$yesterday_total_loss = 0;
$today_total_cost = 0;
$yesterday_total_cost = 0;
while ($row = mysqli_fetch_assoc($search_result)) {
  if (!empty($row['tbl_time_stamp'])) {
    $timestamp = strtotime($row['tbl_time_stamp']);
    if ($timestamp !== false) {
      $date_only = date('Y-m-d', $timestamp);
      $m_status = strtolower($row['tbl_match_status']);
      if ($date_only === $current_date) {
        $today_total_cost += $row['tbl_match_cost'];
        if ($m_status === 'profit' || $m_status === 'win' || $m_status === 'won' || $m_status === 'cashout') {
          $today_total_profit += $row['tbl_match_profit'];
        } elseif ($m_status === 'loss' || $m_status === 'lost') {
          $today_total_loss += $row['tbl_match_cost'];
        }
      }
      if ($date_only === $yesterday_date) {
        $yesterday_total_cost += $row['tbl_match_cost'];
        if ($m_status === 'profit' || $m_status === 'win' || $m_status === 'won' || $m_status === 'cashout') {
          $yesterday_total_profit += $row['tbl_match_profit'];
        } elseif ($m_status === 'loss' || $m_status === 'lost') {
          $yesterday_total_loss += $row['tbl_match_cost'];
        }
      }
    }
  }
}

$today_percentage = ($today_total_profit > 0) ? number_format($today_total_profit, 2, ".", "") . " / ₹" . number_format($today_total_cost, 2, ".", "") . " (" . calculatePercentage($today_total_profit, $today_total_cost) . ")" : number_format($today_total_loss, 2, ".", "") . " / ₹" . number_format($today_total_cost, 2, ".", "") . " (" . calculatePercentage($today_total_loss, $today_total_cost) . ")";
$yesterday_percentage = ($yesterday_total_profit > 0) ? number_format($yesterday_total_profit, 2, ".", "") . " / ₹" . number_format($yesterday_total_cost, 2, ".", "") . " (" . calculatePercentage($yesterday_total_profit, $yesterday_total_cost) . ")" : number_format($yesterday_total_loss, 2, ".", "") . " / ₹" . number_format($yesterday_total_cost, 2, ".", "") . " (" . calculatePercentage($yesterday_total_loss, $yesterday_total_cost) . ")";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include "../header_contents.php" ?>
  <title><?php echo $APP_NAME; ?>: Dashboard</title>
  <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    /* Page-specific overrides */
    <?php include "../components/theme-variables.php"; ?>
    .stat-card-premium:nth-child(1) {
      animation-delay: 0.04s;
    }

    .stat-card-premium:nth-child(2) {
      animation-delay: 0.09s;
    }

    .stat-card-premium:nth-child(3) {
      animation-delay: 0.14s;
    }

    .stat-card-premium:nth-child(4) {
      animation-delay: 0.19s;
    }

    .stat-card-premium:nth-child(5) {
      animation-delay: 0.24s;
    }

    .stat-card-premium:nth-child(6) {
      animation-delay: 0.29s;
    }

    .stat-card-premium:nth-child(7) {
      animation-delay: 0.34s;
    }

    .stat-card-premium:nth-child(8) {
      animation-delay: 0.39s;
    }

    .stat-card-premium:nth-child(9) {
      animation-delay: 0.44s;
    }

    .stat-card-premium:nth-child(10) {
      animation-delay: 0.49s;
    }

    .stat-card-premium:nth-child(11) {
      animation-delay: 0.54s;
    }

    .stat-card-premium:nth-child(12) {
      animation-delay: 0.59s;
    }
  </style>
</head>

<body class="bg-light">
  <div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">

      <div class="dash-header">
        <div class="dash-header-left">
          <div class="dash-menu-btn menu-open-btn"><i class='bx bx-menu'></i></div>
          <div>
            <span class="dash-breadcrumb">Admin Panel</span>
            <span class="dash-title">Dashboard</span>
          </div>
        </div>
        <div class="dash-header-right">
          <div class="dash-badge"><span class="dash-live-dot"></span>&nbsp;Live</div>
          <div class="dash-badge"><i class='bx bx-calendar'></i>&nbsp;<?php echo date('D, M j Y'); ?></div>
          <div class="dash-badge"><i class='bx bx-time-five'></i>&nbsp;<?php echo date('h:i A'); ?></div>
        </div>
      </div>

      <div class="stat-cards-grid">

        <div class="stat-card-premium c1">
          <div class="card-label">Monthly Recharge</div>
          <div class="card-value">&#8377;<?php echo $anlyt_total_recharge; ?></div>
          <div class="card-sub">This Month Total</div>
          <i class='bx bx-trending-up card-icon'></i>
        </div>

        <div class="stat-card-premium c2">
          <div class="card-label">Monthly Withdraw</div>
          <div class="card-value">&#8377;<?php echo $anlyt_total_withdraw; ?></div>
          <div class="card-sub">This Month Total</div>
          <i class='bx bx-wallet card-icon'></i>
        </div>

        <div class="stat-card-premium c3">
          <div class="card-label">Today's Deposit</div>
          <div class="card-value">&#8377;<?php echo $today_total_recharge; ?></div>
          <div class="card-sub">Last 24 Hours</div>
          <i class='bx bx-money-withdraw card-icon'></i>
        </div>

        <div class="stat-card-premium c4">
          <div class="card-label">Yesterday's Deposit</div>
          <div class="card-value">&#8377;<?php echo $yesterday_total_recharge; ?></div>
          <div class="card-sub">Previous Day</div>
          <i class='bx bx-calendar-minus card-icon'></i>
        </div>

        <div class="stat-card-premium c5">
          <div class="card-label">Today's Withdrawal</div>
          <div class="card-value">&#8377;<?php echo $today_total_withdraw; ?></div>
          <div class="card-sub">Last 24 Hours</div>
          <i class='bx bx-transfer card-icon'></i>
        </div>

        <div class="stat-card-premium c6">
          <div class="card-label">Yesterday's Withdrawal</div>
          <div class="card-value">&#8377;<?php echo $yesterday_total_withdraw; ?></div>
          <div class="card-sub">Previous Day</div>
          <i class='bx bx-history card-icon'></i>
        </div>

        <div class="stat-card-premium c7">
          <div class="card-label">Today Profit / Loss</div>
          <div class="card-value" style="font-size:17px;"><?php echo $today_percentage; ?></div>
          <div class="card-sub">Last 24 Hours</div>
          <i class='bx bx-line-chart card-icon'></i>
        </div>

        <div class="stat-card-premium c8">
          <div class="card-label">Yesterday Profit / Loss</div>
          <div class="card-value" style="font-size:17px;"><?php echo $yesterday_percentage; ?></div>
          <div class="card-sub">Previous Day</div>
          <i class='bx bx-stats card-icon'></i>
        </div>

        <div class="stat-card-premium c10">
          <div class="card-label">Total Users</div>
          <div class="card-value"><?php echo $anlyt_total_users; ?></div>
          <div class="card-sub">All Registered</div>
          <i class='bx bx-group card-icon'></i>
        </div>

        <div class="stat-card-premium c11">
          <div class="card-label">Active Users</div>
          <div class="card-value"><?php echo $anlyt_last_active_users; ?></div>
          <div class="card-sub">Online in last 30 min</div>
          <i class='bx bx-user-check card-icon'></i>
        </div>

        <div class="stat-card-premium c12">
          <div class="card-label">Grand Sports</div>
          <div class="card-value">&#8377;<?php echo $grand_sports_total_bet; ?></div>
          <div class="card-sub">
            Profit:&nbsp;<strong>&#8377;<?php echo $grand_sports_profit_amount; ?></strong>
            &nbsp;|&nbsp;Loss:&nbsp;<strong>&#8377;<?php echo $grand_sports_loss_amount; ?></strong>
          </div>
          <i class='bx bx-trophy card-icon'></i>
        </div>

        <div class="stat-card-premium c9">
          <div class="card-label"><i
              class='bx bx-calendar'></i>&nbsp;<?php echo date('M j, Y', strtotime($selected_date)); ?></div>
          <div class="date-card-inner">
            <div class="card-sub">Deposit</div>
            <h3>&#8377;<?php echo $total_recharge; ?></h3>
            <div class="card-sub" style="margin-top:8px;">Withdrawal</div>
            <h3>&#8377;<?php echo $total_withdraw; ?></h3>
          </div>
          <form method="post" id="dateForm" style="margin-top:4px;">
            <input type="date" name="date" class="premium-date-input" value="<?php echo $selected_date; ?>" required
              onchange="submitForm()">
          </form>
          <i class='bx bx-filter-alt card-icon'></i>
        </div>

      </div>



      <div class="recent-section">
        <div class="section-title">
          <span class="title-bar"></span>
          Recently Played
        </div>
        <table class="r-table">
          <thead>
            <tr>
              <th style="width:8%">No</th>
              <th style="width:20%">User ID</th>
              <th style="width:20%">User Name</th>
              <th>Game</th>
              <th style="width:18%">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $play_records_sql = "SELECT m.*, u.tbl_full_name FROM tblmatchplayed m LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id ORDER BY m.id DESC LIMIT 20";
            $play_records_result = mysqli_query($conn, $play_records_sql) or die('search failed');
            $sl_num = 1;
            if (mysqli_num_rows($play_records_result) > 0) {
              while ($row = mysqli_fetch_assoc($play_records_result)) { ?>
                <tr>
                  <td><span class="rn"><?php echo $sl_num; ?></span></td>
                  <td><?php echo htmlspecialchars($row['tbl_user_id']); ?></td>
                  <td><?php echo htmlspecialchars($row['tbl_full_name'] ?? 'Unknown'); ?></td>
                  <td><?php echo htmlspecialchars($row['tbl_project_name']); ?></td>
                  <td>
                    <?php if (strtolower($row['tbl_match_status']) === 'loss'): ?>
                      <span class="badge-loss">&#9660; Loss</span>
                    <?php else: ?>
                      <span class="badge-profit">&#9650; Profit</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php $sl_num++;
              }
            } ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

  <script>function submitForm() { document.getElementById('dateForm').submit(); }</script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* <?php echo $APP_NAME; ?>
    Premium SweetAlert2 Theme */

    /* <?php echo $APP_NAME; ?>
    Premium Compact Horizontal Modal */ .winco-swal-popup {
      background: rgba(15, 23, 42, 0.94) !important;
      backdrop-filter: blur(15px) !important;
      -webkit-backdrop-filter: blur(15px) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      border-radius: 12px !important;
      padding: 15px !important;
      width: 300px !important;
    }

    .winco-swal-popup .swal2-icon {
      transform: scale(0.4) !important;
      margin: -20px auto -15px !important;
    }

    .winco-swal-title {
      font-family: 'Archivo Black', sans-serif !important;
      color: #fff !important;
      font-size: 9px !important;
      letter-spacing: 0.5px !important;
      text-transform: uppercase !important;
      text-align: center !important;
      margin-bottom: 12px !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
      padding-bottom: 6px !important;
    }

    .winco-swal-html {
      color: #94a3b8 !important;
      font-family: 'DM Sans', sans-serif !important;
      font-size: 11px !important;
      margin-bottom: 15px !important;
      text-align: center !important;
    }

    .winco-swal-confirm {
      background: linear-gradient(135deg, #10b981, #06b6d4) !important;
      border-radius: 8px !important;
      padding: 6px 20px !important;
      font-weight: 700 !important;
      font-size: 10px !important;
      box-shadow: 0 6px 12px -3px rgba(16, 185, 129, 0.3) !important;
      cursor: pointer !important;
      border: none !important;
      color: #fff !important;
    }

    .swal-recharge-horizontal {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 10px;
      padding: 10px 15px;
    }

    .swal-recharge-icon {
      width: 28px;
      height: 28px;
      background: rgba(16, 185, 129, 0.1);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #10b981;
      font-size: 16px;
    }

    .swal-recharge-content {
      display: flex;
      flex-direction: column;
      text-align: center;
    }

    .swal-recharge-content .title {
      font-weight: 800;
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      opacity: 0.4;
    }

    .swal-recharge-content .amt {
      font-family: 'Archivo Black', sans-serif;
      font-size: 16px;
      color: #fff;
      line-height: 1.1;
      margin-top: 1px;
    }

    /* Light Mode Pop-up Overrides */
    [data-theme="light"] .winco-swal-popup {
      background: rgba(255, 255, 255, 0.98) !important;
      border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }

    [data-theme="light"] .winco-swal-title {
      color: #0f172a !important;
    }

    [data-theme="light"] .swal-recharge-horizontal {
      background: rgba(0, 0, 0, 0.02);
      border-color: rgba(0, 0, 0, 0.05);
    }

    [data-theme="light"] .swal-recharge-content .amt {
      color: #0f172a;
    }
  </style>

  <script>
    let lastRechargeId = -1;

    function notifyRecharge(amount) {
      Swal.fire({
        title: 'New <?php echo $APP_NAME; ?> System Event',
        html: `
            <div class="swal-recharge-horizontal">
                <div class="swal-recharge-icon">
                    <i class='bx bx-check-shield'></i>
                </div>
                <div class="swal-recharge-content">
                    <span class="title">User Active Recharge</span>
                    <span class="amt">₹ ${parseFloat(amount).toFixed(2)}</span>
                </div>
            </div>
        `,
        icon: 'success',
        iconColor: '#10b981',
        background: 'transparent',
        customClass: {
          popup: 'winco-swal-popup',
          title: 'winco-swal-title',
          htmlContainer: 'winco-swal-html',
          confirmButton: 'winco-swal-confirm'
        },
        buttonsStyling: false,
        confirmButtonText: 'Acknowledge Portal'
      });
    }

    function checkNewRecharge() {
      fetch('check_new_recharge.php')
        .then(r => r.json())
        .then(data => {
          if (data.new_recharge) {
            if (lastRechargeId === -1) {
              // Initialize on first load without showing popup
              lastRechargeId = data.id;
            } else if (data.id > lastRechargeId) {
              // Only show for strictly newer recharges
              lastRechargeId = data.id;
              notifyRecharge(data.amount);
            }
          }
        })
        .catch(e => console.error("Poll Error:", e));
    }

    setInterval(checkNewRecharge, 5000);
    checkNewRecharge();
  </script>
</body>

</html>