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
  $others_balance = 0;
}

// $othersdata_sql = "SELECT * FROM tblusersdata where tbl_uniq_id='{$user_id}'";
// $othersdata_result = mysqli_query($conn, $othersdata_sql) or die('search failed');

// if (mysqli_num_rows($othersdata_result) > 0){
//   while ($row = mysqli_fetch_assoc($othersdata_result)){
//     $others_balance += $row['user_bonus_sended'];   
//   }
// }

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "../header_contents.php" ?>
  <title>Manage: All Activties</title>
  <link href='../style.css' rel='stylesheet'>
</head>

<body>
    
<div class="mh-100vh w-100 col-view dotted-back">
    
    <div class="w-100 col-view pd-15 bg-primary">
      <div class="dpl-flx a-center cl-white" onclick="window.history.back()">
          <i class='bx bx-left-arrow-alt ft-sz-30'></i>
          
          <div class="col-view ft-sz-20 mg-l-10">All Activities</div>
      </div>
    </div>
    
    <div class="w-100 col-view v-center">
       
    <div class="w-90 col-view pd-10-15 mg-t-15 br-r-5 direct-p-mg-t bg-white bx-shdw">
      <p>User Details:</p>
      <p><span class="back-green">User Id</span>&nbsp; <?php echo $user_id; ?></p>
      <p><span class="back-green">Balance</span>&nbsp; <?php echo "₹".checkSetData($user_balance); ?></p>
    
   
	<!--recent activities added -->
	  
	  <div class="mg-t-20 h-line-view"></div>

<br>
<p class="cl-red">Recharge Activities:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-recharge-records', 'table')">Export PDF</button>
<div class="w-100 ovflw-x-scroll">
<table id="table" class="cus-tbl mg-t-10 bg-white">
  <tr>
    <th>Id(s)</th>
    <th>Date</th>
    <th>Number of Transactions</th>
    <th>Deposit Amount</th>
  </tr>

  <?php
  $sql = "SELECT * FROM tblusersrecharge 
          WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL 
          ORDER BY tbl_time_stamp DESC";
  $result = mysqli_query($conn, $sql) or die('Search failed');

  $total_recharge_amnt = 0;
  $grouped_data = [];

  while ($row = mysqli_fetch_assoc($result)) {
      $date_key = date("d M Y", strtotime($row['tbl_time_stamp']));
      $grouped_data[$date_key][] = $row;
  }

  if (!empty($grouped_data)) {
      foreach ($grouped_data as $date => $records) {
          $daily_total = 0;
          $ids = [];
          foreach ($records as $row) {
              $daily_total += $row['tbl_recharge_amount'];
              $total_recharge_amnt += $row['tbl_recharge_amount'];
              $ids[] = $row['tbl_uniq_id'];
          }
          ?>
          <tr>
            <td><?php echo implode(', ', $ids); ?></td>
            <td><?php echo $date; ?></td>
            <td><?php echo count($records); ?> transaction<?php echo count($records) > 1 ? 's' : ''; ?></td>
            <td>₹<?php echo number_format($daily_total, 2); ?></td>
          </tr>
          <?php
      }
      echo "<tr><td colspan='3' class='text-end'><strong>Grand Total</strong></td><td><strong>₹" . number_format($total_recharge_amnt, 2) . "</strong></td></tr>";
  } else {
      echo "<tr><td colspan='4'>No Data Found!</td></tr>";
  }
  ?>
</table>
</div>

<!--recent activities added -->
	  
	  
	  
 
  	  
  	  <!--withdraw activities added -->
  	  <div class="mg-t-20 h-line-view"></div>

<br>
<p class="cl-red">Withdrawl Activities:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-withdrawl-records', 'table2')">Export PDF</button>
<div class="w-100 ovflw-x-scroll">
<table id="table2" class="cus-tbl mg-t-10 bg-white">
  <tr>
    <th>Date</th>
    <th>Number of Transactions</th>
    <th>Withdraw Amount</th>
  </tr>

  <?php
  $sql = "SELECT * FROM tbluserswithdraw 
          WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL 
          ORDER BY tbl_time_stamp DESC";
  $result = mysqli_query($conn, $sql) or die('Search failed');

  $total_withdraw_amnt = 0;
  $grouped_data = [];

  while ($row = mysqli_fetch_assoc($result)) {
      $date_key = date("d M Y", strtotime($row['tbl_time_stamp']));
      $grouped_data[$date_key][] = $row;
  }

  if (!empty($grouped_data)) {
      foreach ($grouped_data as $date => $records) {
          $daily_total = 0;
          foreach ($records as $row) {
              $daily_total += $row['tbl_withdraw_amount'];
              $total_withdraw_amnt += $row['tbl_withdraw_amount'];
          }
          ?>
          <tr>
            <td><?php echo $date; ?></td>
            <td><?php echo count($records); ?> transaction<?php echo count($records) > 1 ? 's' : ''; ?></td>
            <td>₹<?php echo number_format($daily_total, 2); ?></td>
          </tr>
          <?php
      }
      echo "<tr><td colspan='2' class='text-end'><strong>Grand Total</strong></td><td><strong>₹" . number_format($total_withdraw_amnt, 2) . "</strong></td></tr>";
  } else {
      echo "<tr><td colspan='3'>No Data Found!</td></tr>";
  }
  ?>
</table>
</div>

  	  <!--withdraw activities added -->
  	  
  
	    	  <!--Match activities added -->

	
<div class="mg-t-20 h-line-view"></div>

<br>
<p class="cl-red">Match Activities:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-match-data', 'table4')">Export PDF</button>
<div class="w-100 ovflw-x-scroll">
<table id="table4" class="cus-tbl mg-t-10 bg-white">
  <tr>
    <th>#</th>
    <th>Date</th>
    <th>Total Bet Amount</th>
    <th>Total P&L</th>
    <th>Number of Matches</th>
  </tr>

  <?php
  $sql = "SELECT * FROM tblmatchplayed WHERE tbl_user_id='{$user_id}' ORDER BY tbl_time_stamp DESC";
  $result = mysqli_query($conn, $sql) or die('Search failed');

  $grouped_data = [];
  $total_profit_amnt = 0;

  while ($row = mysqli_fetch_assoc($result)) {
      if (!$row['tbl_time_stamp']) continue;
      $date = date("d M Y", strtotime($row['tbl_time_stamp']));

      if (!isset($grouped_data[$date])) {
          $grouped_data[$date] = [
              'bet_total' => 0,
              'profit_total' => 0,
              'count' => 0
          ];
      }

      $grouped_data[$date]['bet_total'] += $row['tbl_match_cost'];
      $grouped_data[$date]['profit_total'] += $row['tbl_match_profit'];
      $grouped_data[$date]['count'] += 1;

      $total_profit_amnt += $row['tbl_match_profit'];
  }

  if (!empty($grouped_data)) {
      $index = 1;
      foreach ($grouped_data as $date => $data) {
          ?>
          <tr>
            <td><?php echo $index++; ?></td>
            <td><?php echo $date; ?></td>
            <td>₹<?php echo number_format($data['bet_total'], 2); ?></td>
            <td>₹<?php echo number_format($data['profit_total'], 2); ?></td>
            <td><?php echo $data['count']; ?> match<?php echo $data['count'] > 1 ? 'es' : ''; ?></td>
          </tr>
          <?php
      }
      echo "<tr><td colspan='3' class='text-end'><strong>Grand Total Profit</strong></td><td colspan='2'><strong>₹" . number_format($total_profit_amnt, 2) . "</strong></td></tr>";
  } else {
      echo "<tr><td colspan='5'>No Data Found!</td></tr>";
  }
  ?>
</table>
</div>

<!--Matchactivities-->

<?php
session_start();
include '../../security/config.php';

if (!isset($_GET['user-id'])) {
    echo "Request blocked";
    return;
}
$user_id = mysqli_real_escape_string($conn, $_GET['user-id']);

// Initialize arrays to store grouped data
$data = [];

function formatAmount($amt) {
    return $amt ? number_format($amt, 2) : "0.00";
}

// Fetch deposit data
$deposit_sql = "SELECT tbl_time_stamp, tbl_recharge_amount FROM tblusersrecharge 
                WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
$deposit_result = mysqli_query($conn, $deposit_sql);
while ($row = mysqli_fetch_assoc($deposit_result)) {
    $date = date("d M Y", strtotime($row['tbl_time_stamp']));
    $data[$date]['deposit'] = ($data[$date]['deposit'] ?? 0) + $row['tbl_recharge_amount'];
}

// Fetch withdraw data
$withdraw_sql = "SELECT tbl_time_stamp, tbl_withdraw_amount FROM tbluserswithdraw 
                 WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
$withdraw_result = mysqli_query($conn, $withdraw_sql);
while ($row = mysqli_fetch_assoc($withdraw_result)) {
    $date = date("d M Y", strtotime($row['tbl_time_stamp']));
    $data[$date]['withdraw'] = ($data[$date]['withdraw'] ?? 0) + $row['tbl_withdraw_amount'];
}

// Fetch match data (bets and profit/loss)
$match_sql = "SELECT tbl_time_stamp, tbl_match_cost, tbl_match_profit FROM tblmatchplayed 
              WHERE tbl_user_id='{$user_id}' AND tbl_time_stamp IS NOT NULL";
$match_result = mysqli_query($conn, $match_sql);
while ($row = mysqli_fetch_assoc($match_result)) {
    $date = date("d M Y", strtotime($row['tbl_time_stamp']));
    $data[$date]['bet'] = ($data[$date]['bet'] ?? 0) + $row['tbl_match_cost'];
    $data[$date]['pl'] = ($data[$date]['pl'] ?? 0) + $row['tbl_match_profit'];
}

ksort($data); // Sort by date

?>

<!DOCTYPE html>
<html>
<head>
    <title>User Summary</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>

<h2>User Summary Report</h2>

<table>
    <tr>
        <th>Date</th>
        <th>Deposit Amount</th>
        <th>Withdraw Amount</th>
        <th>Total Bet Amount</th>
        <th>Total P&amp;L</th>
    </tr>
    <?php
    
    if (!empty($data)) {
        foreach ($data as $date => $values) {
            echo "<tr>
                 $uniq_id = $row['tbl_uniq_id'];

                    <td>{$date}</td>
                    <td>₹" . formatAmount($values['deposit'] ?? 0) . "</td>
                    <td>₹" . formatAmount($values['withdraw'] ?? 0) . "</td>
                    <td>₹" . formatAmount($values['bet'] ?? 0) . "</td>
                    <td>₹" . formatAmount($values['pl'] ?? 0) . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='5'>No Data Found!</td></tr>";
    }
    ?>
</table>

</body>
</html>


	
    </div>
    
    </div>

</div>
<script src="../script.js?v=8"></script>
</body>
</html>


