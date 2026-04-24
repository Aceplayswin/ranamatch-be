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
    
 
 
 
    
      <div class="mg-t-20 h-line-view"></div>

<br>
<p class="cl-red">Recharge Activities:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-recharge-records', 'recharge_table')">Export PDF</button>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:100px;" onclick="exportExcel('recharge_table', '<?php echo $user_name; ?>-recharge-records.xlsx')">Export Excel</button>

<div class="w-100 ovflw-x-scroll">
<table id="recharge_table" class="cus-tbl mg-t-10 bg-white">
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

	  <div class="mg-t-20 h-line-view"></div>

<br>
<p class="cl-red">Withdrawl Activities:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-withdrawl-records', 'withdrawal_table')">Export PDF</button>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:100px;" onclick="exportExcel('withdrawal_table', '<?php echo $user_name; ?>-withdrawl-records.xlsx')">Export Excel</button>

<div class="w-100 ovflw-x-scroll">
<table id="withdrawal_table" class="cus-tbl mg-t-10 bg-white">
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

  <div class="mg-t-20 h-line-view"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<br>
<p class="cl-red">Match Activities Download Report:</p>
<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:80px;" onclick="exportPDF('<?php echo $user_name; ?>-match-summary', 'match_summary_table')">Export PDF</button>

<button class="action-btn br-r-5 ft-sz-12 mg-t-5" style="width:100px;" onclick="exportExcel('match_summary_table', '<?php echo $user_name; ?>-match-summary.xlsx')">Export Excel</button>






<div class="w-100 ovflw-x-scroll">
<table id="match_summary_table" class="cus-tbl mg-t-10 bg-white">
    
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

<?php
include '../../security/config.php';

if (!isset($_GET['user-id'])) {
    echo "User ID missing.";
    return;
}
$user_id = mysqli_real_escape_string($conn, $_GET['user-id']);

$data_by_date = [];

// Fetch Deposits
$deposit_sql = "SELECT tbl_recharge_amount, tbl_time_stamp FROM tblusersrecharge 
                WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
$result = mysqli_query($conn, $deposit_sql);
while ($row = mysqli_fetch_assoc($result)) {
    $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
    $data_by_date[$date]['deposit'] = ($data_by_date[$date]['deposit'] ?? 0) + $row['tbl_recharge_amount'];
}

// Fetch Withdrawals
$withdraw_sql = "SELECT tbl_withdraw_amount, tbl_time_stamp FROM tbluserswithdraw 
                 WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND tbl_time_stamp IS NOT NULL";
$result = mysqli_query($conn, $withdraw_sql);
while ($row = mysqli_fetch_assoc($result)) {
    $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
    $data_by_date[$date]['withdraw'] = ($data_by_date[$date]['withdraw'] ?? 0) + $row['tbl_withdraw_amount'];
}

// Fetch Match Activity
$match_sql = "SELECT tbl_match_cost, tbl_match_profit, tbl_time_stamp FROM tblmatchplayed 
              WHERE tbl_user_id='{$user_id}' AND tbl_time_stamp IS NOT NULL";
$result = mysqli_query($conn, $match_sql);
while ($row = mysqli_fetch_assoc($result)) {
    $date = date("Y-m-d", strtotime($row['tbl_time_stamp']));
    $data_by_date[$date]['bet'] = ($data_by_date[$date]['bet'] ?? 0) + $row['tbl_match_cost'];
    $data_by_date[$date]['pl'] = ($data_by_date[$date]['pl'] ?? 0) + $row['tbl_match_profit'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Financial Summary</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        table, th, td {
            border: 1px solid #999;
        }
        th, td {
            padding: 8px 12px;
            text-align: center;
        }
        .download-btn {
            margin: 15px 0;
            padding: 8px 14px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        .download-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

<h2>User Summary Report</h2>
<button class="download-btn" onclick="exportExcel('summaryTable', 'user_summary.xlsx')">📥 Download Excel</button>

<table id="summaryTable">
    <thead>
        <tr>
            <th>Date</th>
            <th>Deposit Amount</th>
            <th>Withdraw Amount</th>
            <th>Total Bet Amount</th>
            <th>Total P&L</th>
        </tr>
    </thead>
    <tbody>
        <?php
        ksort($data_by_date);
        foreach ($data_by_date as $date => $values) {
            echo "<tr>";
            echo "<td>" . date("d M Y", strtotime($date)) . "</td>";
            echo "<td>₹" . number_format($values['deposit'] ?? 0, 2) . "</td>";
            echo "<td>₹" . number_format($values['withdraw'] ?? 0, 2) . "</td>";
            echo "<td>₹" . number_format($values['bet'] ?? 0, 2) . "</td>";
            echo "<td>₹" . number_format($values['pl'] ?? 0, 2) . "</td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<script>
    function exportExcel(tableId, filename = '') {
        const table = document.getElementById(tableId);
        if (!table) {
            alert("Table not found: " + tableId);
            return;
        }
        const wb = XLSX.utils.table_to_book(table, { sheet: "Summary" });
        XLSX.writeFile(wb, filename || 'summary.xlsx');
    }
</script>

</body>
</html>



	
    </div>
    
    </div>

</div>


<script src="../script.js?v=8"></script>
<script>
  function exportExcel(tableId, filename = '') {
      var table = document.getElementById(tableId);
      var wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
      XLSX.writeFile(wb, filename || 'Export.xlsx');
  }
</script>
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
</script>
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
</script>

</body>

</html>
