<?php
define("ACCESS_SECURITY", "true");
include "../../security/config.php";
include "../../security/constants.php";
include '../access_validate.php';
include "../../mainhandler/get-period-id.php";

date_default_timezone_set('Asia/Kolkata');
$curr_date = date('d-m-Y');
$curr_time = date('h:i:s a');
$curr_date_time = $curr_date.' '.$curr_time;

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_match_control")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

if (!isset($_GET["game"])) {
    echo "request block";
    return;
} else {
    $game_code = mysqli_real_escape_string($conn, $_GET["game"]);
}


$controllerType = 1;
if($game_code=="AVIATOR" || $game_code=="DICE"){
 $controllerType = 2;   
}else if($game_code=="ANDARBAHAR"){
 $controllerType = 3; 
}else if($game_code=="WHEELOCITY"){
 $controllerType = 4; 
}



$data_total_bet_count = 0;
$data_total_red_invested = 0;
$data_total_green_invested = 0;
$data_total_violet_invested = 0;
$data_total_yellow_invested = 0;
$data_total_invested = 0;

$data_total_big_invested = 0;
$data_total_small_invested = 0;
$data_total_odd_invested = 0;
$data_total_even_invested = 0;

$data_total_andar_invested = 0;
$data_total_bahar_invested = 0;
$data_total_tie_invested = 0;

$data_tiger_invested = 0;
$data_cow_invested = 0;
$data_elephant_invested = 0;
$data_crown_invested = 0;

$data_invested_in_1 = 0;
$data_invested_in_2 = 0;
$data_invested_in_3 = 0;
$data_invested_in_4 = 0;
$data_invested_in_5 = 0;
$data_invested_in_6 = 0;
$data_invested_in_7 = 0;
$data_invested_in_8 = 0;
$data_invested_in_9 = 0;
$data_invested_in_10 = 0;
$data_invested_in_11 = 0;
$data_invested_in_12 = 0;
$data_invested_in_13 = 0;
$data_invested_in_14 = 0;
$data_invested_in_15 = 0;
$data_invested_in_16 = 0;
$data_invested_in_17 = 0;
$data_invested_in_18 = 0;
$data_invested_in_19 = 0;
$data_invested_in_20 = 0;
$data_invested_in_21 = 0;
$data_invested_in_22 = 0;
$data_invested_in_23 = 0;
$data_invested_in_24 = 0;
$data_invested_in_25 = 0;
$data_invested_in_26 = 0;
$data_invested_in_27 = 0;
$data_invested_in_28 = 0;
$data_invested_in_29 = 0;
$data_invested_in_30 = 0;
$data_invested_in_31 = 0;
$data_invested_in_32 = 0;
$data_invested_in_33 = 0;
$data_invested_in_34 = 0;
$data_invested_in_35 = 0;
$data_invested_in_36 = 0;


$data_bet_set_as = "";
$data_play_time = "";
$data_active_users = "";

// $resArr['active_users'] = 0;
// $resArr['bet_set_as'] = "";


$games_sql = "SELECT * FROM tblgamecontrols WHERE tbl_service_name='{$game_code}' ";
$games_result = mysqli_query($conn, $games_sql) or die('search failed');

if (mysqli_num_rows($games_result) > 0){
    $res_data = mysqli_fetch_assoc($games_result);
    $service_value = $res_data['tbl_service_value'];
    $service_times = $res_data['tbl_service_times'];
    
    $service_value_arr = explode(",", $service_value);
    $service_times_arr = explode(",", $service_times);
    
    $data_play_time = $service_times_arr[0];
    $data_bet_set_as = $service_value_arr[2];
}else{
    echo "Invalid Project Name!";
    return;
}
    
    
$generatePeriod = new GeneratePeriod($data_play_time);
$generatePeriod->setupTimes();
$new_match_period_id =
$generatePeriod->getDateTime() . $generatePeriod->getPeriodId();

function getTimeDiff($datetime_1,$datetime_2){
 $timestamp1 = strtotime($datetime_2);
 $timestamp2 = strtotime($datetime_1);

 $diff = $timestamp2 - $timestamp1;
 return $diff;   
}

$select_sql = "SELECT * FROM tblmatchplayed WHERE tbl_period_id='{$new_match_period_id}' AND tbl_project_name='{$game_code}' ";
$select_query = mysqli_query($conn,$select_sql);
$numRows = mysqli_num_rows($select_query);

if($numRows > 0){

 while($row = mysqli_fetch_assoc($select_query)){
  $investedOn = $row['tbl_invested_on'];
  $investedAmnt = $row['tbl_match_cost'];
  $data_total_invested += $investedAmnt;
  
  if($investedOn == "A"){
      $data_total_andar_invested += $investedAmnt;
  }else if($investedOn == "B"){
      $data_total_bahar_invested += $investedAmnt;
  }else if($investedOn == "T"){
      $data_total_tie_invested += $investedAmnt;
  }
  
  
  if($investedOn == "tiger"){
      $data_tiger_invested += $investedAmnt;
  }else if($investedOn == "cow"){
      $data_cow_invested += $investedAmnt;
  }if($investedOn == "elephant"){
      $data_elephant_invested += $investedAmnt;
  }else if($investedOn == "crown"){
      $data_crown_invested += $investedAmnt;
  }
  
  if($investedOn == "1"){
      $data_invested_in_1 += $investedAmnt;
  }else if($investedOn == "2"){
      $data_invested_in_2 += $investedAmnt;
  }else if($investedOn == "3"){
      $data_invested_in_3 += $investedAmnt;
  }else if($investedOn == "4"){
      $data_invested_in_4 += $investedAmnt;
  }else if($investedOn == "5"){
      $data_invested_in_5 += $investedAmnt;
  }else if($investedOn == "6"){
      $data_invested_in_6 += $investedAmnt;
  }else if($investedOn == "7"){
      $data_invested_in_7 += $investedAmnt;
  }else if($investedOn == "8"){
      $data_invested_in_8 += $investedAmnt;
  }else if($investedOn == "9"){
      $data_invested_in_9 += $investedAmnt;
  }else if($investedOn == "10"){
      $data_invested_in_10 += $investedAmnt;
  }else if($investedOn == "11"){
      $data_invested_in_11 += $investedAmnt;
  }else if($investedOn == "12"){
      $data_invested_in_12 += $investedAmnt;
  }else if($investedOn == "13"){
      $data_invested_in_13 += $investedAmnt;
  }else if($investedOn == "14"){
      $data_invested_in_14 += $investedAmnt;
  }else if($investedOn == "14"){
      $data_invested_in_14 += $investedAmnt;
  }else if($investedOn == "15"){
      $data_invested_in_15 += $investedAmnt;
  }else if($investedOn == "16"){
      $data_invested_in_16 += $investedAmnt;
  }else if($investedOn == "17"){
      $data_invested_in_17 += $investedAmnt;
  }else if($investedOn == "18"){
      $data_invested_in_18 += $investedAmnt;
  }else if($investedOn == "19"){
      $data_invested_in_19 += $investedAmnt;
  }else if($investedOn == "20"){
      $data_invested_in_20 += $investedAmnt;
  }else if($investedOn == "21"){
      $data_invested_in_21 += $investedAmnt;
  }else if($investedOn == "22"){
      $data_invested_in_22 += $investedAmnt;
  }else if($investedOn == "23"){
      $data_invested_in_23 += $investedAmnt;
  }else if($investedOn == "24"){
      $data_invested_in_24 += $investedAmnt;
  }else if($investedOn == "25"){
      $data_invested_in_25 += $investedAmnt;
  }else if($investedOn == "26"){
      $data_invested_in_26 += $investedAmnt;
  }else if($investedOn == "27"){
      $data_invested_in_27 += $investedAmnt;
  }else if($investedOn == "28"){
      $data_invested_in_28 += $investedAmnt;
  }else if($investedOn == "29"){
      $data_invested_in_29 += $investedAmnt;
  }else if($investedOn == "30"){
      $data_invested_in_30 += $investedAmnt;
  }else if($investedOn == "31"){
      $data_invested_in_31 += $investedAmnt;
  }else if($investedOn == "32"){
      $data_invested_in_32 += $investedAmnt;
  }else if($investedOn == "33"){
      $data_invested_in_33 += $investedAmnt;
  }else if($investedOn == "34"){
      $data_invested_in_34 += $investedAmnt;
  }else if($investedOn == "35"){
      $data_invested_in_35 += $investedAmnt;
  }else if($investedOn == "36"){
      $data_invested_in_36 += $investedAmnt;
  }
  
  if($investedOn == "b"){
      $data_total_big_invested += $investedAmnt;
  }else if($investedOn == "s"){
      $data_total_small_invested += $investedAmnt;
  }else if($investedOn == "o"){
      $data_total_odd_invested += $investedAmnt;
  }else if($investedOn == "e"){
      $data_total_even_invested += $investedAmnt;
  }
  
  if ($investedOn == "green" || $investedOn == "1" || $investedOn == "3" || $investedOn == "7" || $investedOn == "9"){
    $data_total_green_invested += $investedAmnt;
  }else if ($investedOn == "red" || $investedOn == "2" || $investedOn == "4" || $investedOn == "6" || $investedOn == "8"){
    $data_total_red_invested += $investedAmnt;
  }else if ($investedOn == "yellow"){
    $data_total_yellow_invested += $investedAmnt;
  }else if ($investedOn == "violet" || $investedOn == "0" || $investedOn == "5"){
    $data_total_violet_invested += $investedAmnt;
  }
  
 }
 
 $data_total_bet_count = $numRows;
}

    
// calculate active users
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='true' AND tbl_last_active_date='{$curr_date}' ";
$select_query = mysqli_query($conn,$select_sql);
$numRows = mysqli_num_rows($select_query);

if($numRows > 0){
  $tempActiveUsers = 0;
  while($row = mysqli_fetch_assoc($select_query)){
     $activeDateTime = $row['tbl_last_active_date'].' '.$row['tbl_last_active_time'];
    
    if(getTimeDiff($curr_date_time,$activeDateTime) <= 120){
      $tempActiveUsers++;
    }     
  }

  $data_active_users = (string) $tempActiveUsers;
}

// set game data
$generatePeriod = new GeneratePeriod($data_play_time);
$generatePeriod->setupTimes();
$new_match_period_id =
        $generatePeriod->getDateTime() . $generatePeriod->getPeriodId();
$match_remaining_seconds = $generatePeriod->getRemainingSec();





// $content = 15;
// if (isset($_GET["page_no"])) {
//     $page_no = $_GET["page_no"];
//     $offset = ($page_no - 1) * $content;
// } else {
//     $page_no = 1;
//     $offset = ($page_no - 1) * $content;
// }
?>
<!DOCTYPE html>
<html>
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Control Game</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
<?php include "../components/theme-variables.php"; ?>
body { 
    font-family: var(--font-body) !important;
    background-color: var(--page-bg) !important;
    min-height: 100vh;
    color: var(--text-main);
}

.dash-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px; padding: 20px 14px 18px;
  border-bottom: 1px solid var(--border-dim);
  margin-bottom: 24px;
}
.dash-header-left  { display: flex; align-items: center; gap: 14px; }
.dash-header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.dash-menu-btn {
  display:flex; align-items:center; justify-content:center;
  width:40px; height:40px; border-radius:10px;
  background:rgba(255,255,255,0.07);
  border:1px solid rgba(255,255,255,0.12);
  font-size:20px; color:#e2e8f0; cursor:pointer; flex-shrink:0;
}

.dash-breadcrumb {
  font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase;
  color: var(--status-info);
}
.dash-title {
  font-size:24px; font-weight:700; letter-spacing:-0.5px;
  color: var(--text-main); line-height:1.2; display:block;
}

.metric-card {
  background: var(--panel-bg);
  border: 1px solid var(--border-dim);
  border-radius: 16px; padding: 20px;
  display: flex; align-items: center; justify-content: space-between;
  transition: all 0.2s;
}
.metric-card:hover { transform: translateY(-4px); border-color: rgba(59, 130, 246, 0.3); }
.metric-val { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 4px; }
.metric-label { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.metric-icon { 
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; background: rgba(255,255,255,0.05); color: var(--accent-blue);
}

.game-panel {
  background: var(--panel-bg);
  border: 1px solid var(--border-dim);
  border-radius: 20px; padding: 24px; margin-top: 24px;
}

.r-table { width: 100%; border-collapse: separate; border-spacing: 0 4px; }
.r-table th { 
  font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; 
  letter-spacing: 1px; padding: 12px 16px;
}
.r-table td { 
  background: rgba(255,255,255,0.02); padding: 14px 16px; 
  font-size: 14px; color: #cbd5e1; font-weight: 500;
}
.r-table tr td:first-child { border-radius: 10px 0 0 10px; }
.r-table tr td:last-child  { border-radius: 0 10px 10px 0; }

.manual-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.manual-modal {
    background: #1c2128; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 24px; width: 400px; padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
}

.set-bet-btn-header {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    color: #fff; padding: 10px 18px; border-radius: 12px;
    font-weight: 700; cursor: pointer; transition: all 0.2s;
    font-size: 13px; display: flex; align-items: center; gap: 8px;
}
.set-bet-btn-header:hover { background: var(--accent-blue); transform: scale(1.05); }

.bet-label {
    display: inline-block; padding: 10px 20px; border-radius: 12px;
    font-weight: 700; color: #fff; cursor: pointer; transition: all 0.2s;
    border: 2px solid transparent; margin-bottom: 8px; font-size: 13px;
}
.bet-label:hover { transform: scale(1.05); }
input[type="radio"]:checked + .bet-label { border-color: #fff; box-shadow: 0 0 15px rgba(255,255,255,0.2); }

.restart-footer {
    background: rgba(239, 68, 68, 0.05);
    border: 1px solid rgba(239, 68, 68, 0.1);
    border-radius: 16px; padding: 20px; margin-top: 30px;
    display: flex; align-items: center; justify-content: space-between;
}
</style>
   </head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
       
      <input type="text" id="in_game_code" value="<?php echo $game_code; ?>" hidden>
          
      <div class="dash-header">
        <div class="dash-header-left">
            <div class="dash-menu-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
            <div>
                <span class="dash-breadcrumb">Game Control > <?php echo $game_code; ?></span>
                <span class="dash-title" id="match_details_view"><?php echo $new_match_period_id; ?></span>
            </div>
        </div>
        
        <div class="set-bet-btn-header set_bet_btn">
            <i class='bx bx-lock-open-alt'></i>&nbsp;<?php if($data_bet_set_as!="none"){ echo $data_bet_set_as; }else{ echo "Set Bet Option"; } ?>
        </div>
      </div>
      
      <div class="pd-15">
          
        <div class="row g-4">
          <div class="col-md-3">
              <div class="metric-card">
                  <div>
                      <div class="metric-val" id="match_timer_tv"><?php echo $match_remaining_seconds; ?>s</div>
                      <div class="metric-label">Remaining Time</div>
                  </div>
                  <div class="metric-icon"><i class='bx bx-time-five'></i></div>
              </div>
          </div>
          <div class="col-md-3">
              <div class="metric-card">
                  <div>
                      <div class="metric-val" id="match_active_users_tv"><?php echo $data_active_users; ?></div>
                      <div class="metric-label">Active Users</div>
                  </div>
                  <div class="metric-icon" style="color: var(--accent-green);"><i class='bx bx-user'></i></div>
              </div>
          </div>
          <div class="col-md-3">
              <div class="metric-card">
                  <div>
                      <div class="metric-val" id="match_total_bet_tv"><?php echo $data_total_bet_count; ?></div>
                      <div class="metric-label">Bets Placed</div>
                  </div>
                  <div class="metric-icon" style="color: #f59e0b;"><i class='bx bx-receipt'></i></div>
              </div>
          </div>
          <div class="col-md-3">
              <div class="metric-card">
                  <div>
                      <div class="metric-val" id="match_total_invested_tv">₹<?php echo $data_total_invested; ?></div>
                      <div class="metric-label">Total Volume</div>
                  </div>
                  <div class="metric-icon" style="color: #ec4899;"><i class='bx bx-wallet'></i></div>
              </div>
          </div>
        </div>
        
          
        <div class="game-panel">
            <p class="mg-b-20 font-weight-bold" style="letter-spacing: 1px; color: #94a3b8; text-transform: uppercase; font-size: 11px;">
                <i class='bx bx-receipt' style="color: var(--accent-blue);"></i>&nbsp;Detailed Investment Analysis
            </p>
            <table class="r-table">
                <thead>
                  <tr>
                    <th style="width: 60%">Wager Category</th>
                    <th>Total Investment</th>
                  </tr>
                </thead>
                <tbody>
          
          <?php if($controllerType ==1){ ?>
          
          <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-red" style="font-weight: 700;">RED</span></td>
            <td id="match_red_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_red_invested; ?></td>
          </tr>
          
          <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-green" style="font-weight: 700;">GREEN</span></td>
            <td id="match_green_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_green_invested; ?></td>
          </tr>
          
          <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-violet" style="font-weight: 700;">VIOLET</span></td>
            <td id="match_violet_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_violet_invested; ?></td>
          </tr>
          
          <?php }else if($controllerType==3){ ?>
          
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-blue" style="font-weight: 700;">ANDAR</span></td>
            <td id="match_andar_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_andar_invested; ?></td>
           </tr>
           
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-red" style="font-weight: 700;">BAHAR</span></td>
            <td id="match_bahar_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_bahar_invested; ?></td>
           </tr>
           
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-yellow" style="font-weight: 700; color: #000;">TIE</span></td>
            <td id="match_tie_invested_tv" style="font-weight: 700;">₹<?php echo $data_total_tie_invested; ?></td>
           </tr>
          
          <?php }else if($controllerType==4){ ?>
          
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-yellow" style="font-weight: 700; color:#000;">YELLOW</span></td>
            <td style="font-weight: 700;">₹<?php echo $data_total_yellow_invested; ?></td>
           </tr>
           
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-red" style="font-weight: 700;">RED</span></td>
            <td style="font-weight: 700;">₹<?php echo $data_total_red_invested; ?></td>
           </tr>
           
           <tr>
            <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-green" style="font-weight: 700;">GREEN</span></td>
            <td style="font-weight: 700;">₹<?php echo $data_total_green_invested; ?></td>
           </tr>
           
           <?php if($data_tiger_invested > 0){ ?>
             <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-blue" style="font-weight: 700;">TIGER</span></td>
              <td style="font-weight: 700;">₹<?php echo $data_tiger_invested; ?></td>
             </tr>
           <?php } ?>
           
           <?php if($data_cow_invested > 0){ ?>
             <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-blue" style="font-weight: 700;">COW</span></td>
              <td style="font-weight: 700;">₹<?php echo $data_cow_invested; ?></td>
             </tr>
           <?php } ?>
           
           <?php if($data_elephant_invested > 0){ ?>
             <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-blue" style="font-weight: 700;">ELEPHANT</span></td>
              <td style="font-weight: 700;">₹<?php echo $data_elephant_invested; ?></td>
             </tr>
           <?php } ?>
           
           <?php if($data_crown_invested > 0){ ?>
             <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-12 br-r-5 cl-white bg-blue" style="font-weight: 700;">CROWN</span></td>
              <td style="font-weight: 700;">₹<?php echo $data_crown_invested; ?></td>
             </tr>
           <?php } ?>
          
          <?php } ?>
          
          
          <?php if($data_total_big_invested > 0){ ?>
            <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-13 br-r-5 cl-white bg-orange">Big</span></td>
              <td>₹<?php echo $data_total_big_invested; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_total_small_invested > 0){ ?>
            <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-13 br-r-5 cl-white bg-blue">Small</span></td>
              <td>₹<?php echo $data_total_small_invested; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_total_odd_invested > 0){ ?>
            <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-13 br-r-5 cl-white bg-red">Odd</span></td>
              <td>₹<?php echo $data_total_odd_invested; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_total_even_invested > 0){ ?>
            <tr>
              <td>Invested In <span class="pd-5-10 ft-sz-13 br-r-5 cl-white bg-green">Even</span></td>
              <td>₹<?php echo $data_total_even_invested; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_1 > 0){ ?>
            <tr>
              <td>Invested In 1</td>
              <td>₹<?php echo $data_invested_in_1; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_2 > 0){ ?>
            <tr>
              <td>Invested In 2</td>
              <td>₹<?php echo $data_invested_in_2; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_3 > 0){ ?>
            <tr>
              <td>Invested In 3</td>
              <td>₹<?php echo $data_invested_in_3; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_4 > 0){ ?>
            <tr>
              <td>Invested In 4</td>
              <td>₹<?php echo $data_invested_in_4; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_5 > 0){ ?>
            <tr>
              <td>Invested In 5</td>
              <td>₹<?php echo $data_invested_in_5; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_6 > 0){ ?>
            <tr>
              <td>Invested In 6</td>
              <td>₹<?php echo $data_invested_in_6; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_7 > 0){ ?>
            <tr>
              <td>Invested In 7</td>
              <td>₹<?php echo $data_invested_in_7; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_8 > 0){ ?>
            <tr>
              <td>Invested In 8</td>
              <td>₹<?php echo $data_invested_in_8; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_9 > 0){ ?>
            <tr>
              <td>Invested In 9</td>
              <td>₹<?php echo $data_invested_in_9; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_10 > 0){ ?>
            <tr>
              <td>Invested In 10</td>
              <td>₹<?php echo $data_invested_in_10; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_11 > 0){ ?>
            <tr>
              <td>Invested In 11</td>
              <td>₹<?php echo $data_invested_in_11; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_12 > 0){ ?>
            <tr>
              <td>Invested In 12</td>
              <td>₹<?php echo $data_invested_in_12; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_13 > 0){ ?>
            <tr>
              <td>Invested In 13</td>
              <td>₹<?php echo $data_invested_in_13; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_14 > 0){ ?>
            <tr>
              <td>Invested In 14</td>
              <td>₹<?php echo $data_invested_in_14; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_15 > 0){ ?>
            <tr>
              <td>Invested In 15</td>
              <td>₹<?php echo $data_invested_in_15; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_16 > 0){ ?>
            <tr>
              <td>Invested In 16</td>
              <td>₹<?php echo $data_invested_in_16; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_17 > 0){ ?>
            <tr>
              <td>Invested In 17</td>
              <td>₹<?php echo $data_invested_in_17; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_18 > 0){ ?>
            <tr>
              <td>Invested In 18</td>
              <td>₹<?php echo $data_invested_in_18; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_19 > 0){ ?>
            <tr>
              <td>Invested In 19</td>
              <td>₹<?php echo $data_invested_in_19; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_20 > 0){ ?>
            <tr>
              <td>Invested In 20</td>
              <td>₹<?php echo $data_invested_in_20; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_21 > 0){ ?>
            <tr>
              <td>Invested In 21</td>
              <td>₹<?php echo $data_invested_in_21; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_22 > 0){ ?>
            <tr>
              <td>Invested In 22</td>
              <td>₹<?php echo $data_invested_in_22; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_23 > 0){ ?>
            <tr>
              <td>Invested In 23</td>
              <td>₹<?php echo $data_invested_in_23; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_24 > 0){ ?>
            <tr>
              <td>Invested In 24</td>
              <td>₹<?php echo $data_invested_in_24; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_25 > 0){ ?>
            <tr>
              <td>Invested In 25</td>
              <td>₹<?php echo $data_invested_in_25; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_26 > 0){ ?>
            <tr>
              <td>Invested In 26</td>
              <td>₹<?php echo $data_invested_in_26; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_27 > 0){ ?>
            <tr>
              <td>Invested In 27</td>
              <td>₹<?php echo $data_invested_in_27; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_28 > 0){ ?>
            <tr>
              <td>Invested In 28</td>
              <td>₹<?php echo $data_invested_in_28; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_29 > 0){ ?>
            <tr>
              <td>Invested In 29</td>
              <td>₹<?php echo $data_invested_in_29; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_30 > 0){ ?>
            <tr>
              <td>Invested In 30</td>
              <td>₹<?php echo $data_invested_in_30; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_31 > 0){ ?>
            <tr>
              <td>Invested In 31</td>
              <td>₹<?php echo $data_invested_in_31; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_32 > 0){ ?>
            <tr>
              <td>Invested In 32</td>
              <td>₹<?php echo $data_invested_in_32; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_33 > 0){ ?>
            <tr>
              <td>Invested In 33</td>
              <td>₹<?php echo $data_invested_in_33; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_34 > 0){ ?>
            <tr>
              <td>Invested In 34</td>
              <td>₹<?php echo $data_invested_in_34; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_35 > 0){ ?>
            <tr>
              <td>Invested In 35</td>
              <td>₹<?php echo $data_invested_in_35; ?></td>
            </tr>
          <?php } ?>
          
          <?php if($data_invested_in_36 > 0){ ?>
            <tr>
              <td>Invested In 36</td>
              <td>₹<?php echo $data_invested_in_36; ?></td>
            </tr>
          <?php } ?>
          
        </table>
        
                </tbody>
            </table>
        </div>
        
        <div class="game-panel">
            <p class="mg-b-20 font-weight-bold" style="letter-spacing: 1px; color: #94a3b8; text-transform: uppercase; font-size: 11px;">
                <i class='bx bx-history' style="color: var(--accent-blue);"></i>&nbsp;Recent Match Records
            </p>
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width:10%">No</th>
                        <th>Period ID</th>
                        <th style="width:15%">Result</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                  $indexVal = 1;
                  $match_records_sql = "SELECT * FROM tblmatchrecords WHERE tbl_project_name='{$game_code}' ORDER BY id DESC LIMIT 5";   
                  $match_records_result = mysqli_query($conn, $match_records_sql) or die('search failed');
          
                  if (mysqli_num_rows($match_records_result) > 0){
                    while ($row = mysqli_fetch_assoc($match_records_result)){
                    ?>
                     <tr>
                        <td><span class="rn"><?php echo $indexVal; ?></span></td>
                        <td style="font-weight: 700; color: #fff;"><?php echo $row['tbl_period_id']; ?></td>
                        <td><span class="pd-5-10 br-r-5 bg-white cl-black ft-sz-12 font-weight-bold"><?php echo $row['tbl_match_result']; ?></span></td>
                     </tr>
                <?php $indexVal++; }}else{ ?>
                  <tr>
                       <td colspan="3" class="text-center">No data found!</td>
                  </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        
      </div>
         
      <br>
      <div class="manual-modal-overlay manual_bet_control_view hide_view">
        <div class="manual-modal manual_bet_dialog hide_view">
            <h4 style="font-weight: 700; color:#fff; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">Setup Manual Bet</h4>
            <br>
            
            <?php if($controllerType==1){ ?>
              <div class=<?php if($controllerType!=1){ echo "hide_view"; } ?>>
               <input type="radio" id="rlabel" name="select_bet_list" value="red" hidden>
               <label for="rlabel" class="bet-label bg-red">Red</label>&nbsp;
               <input type="radio" id="glabel" name="select_bet_list" value="green" hidden>
               <label for="glabel" class="bet-label bg-green">Green</label>
               <br>
               <input type="radio" id="rvlabel" name="select_bet_list" value="redviolet" hidden>
               <label for="rvlabel" class="bet-label bg-red-violet-back">Red - Violet</label>&nbsp;
               <input type="radio" id="gvlabel" name="select_bet_list" value="greenviolet" hidden>
               <label for="gvlabel" class="bet-label bg-green-violet-back">Green - Violet</label>
               <br>
               <?php for($i=1; $i<=9; $i++): 
                 $bColor = ($i % 2 == 0) ? "bg-red" : "bg-green";
               ?>
                 <input type="radio" id="l<?php echo $i; ?>" name="select_bet_list" value="<?php echo $i; ?>" hidden>
                 <label for="l<?php echo $i; ?>" class="bet-label <?php echo $bColor; ?>" style="width:40px; text-align:center;"><?php echo $i; ?></label>&nbsp;
               <?php endfor; ?>
              </div>
            <?php } if($controllerType==2){ ?>
              <div>
                 <p class="ft-sz-12 mg-b-10" style="color:#94a3b8;">Enter Manual Result Multiplier:</p>
                 <input type="text" name="in_bet_value" placeholder="e.g. 1.5x" class="w-100 pd-10 br-r-10 bg-black cl-white br-all" style="border-color: rgba(255,255,255,0.1);">
              </div>
            <?php } if($controllerType==3){ ?>
              <div>
                <input type="radio" id="andarlabel" name="select_bet_list" value="A" hidden>
                <label for="andarlabel" class="bet-label bg-blue">Andar</label>&nbsp;
                <input type="radio" id="baharlabel" name="select_bet_list" value="B" hidden>
                <label for="baharlabel" class="bet-label bg-red">Bahar</label>&nbsp;
                <input type="radio" id="tielabel" name="select_bet_list" value="T" hidden>
                <label for="tielabel" class="bet-label bg-yellow" style="color:#000;">Tie</label>
              </div>
            <?php } if($controllerType==4 || $controllerType==5){ ?>
              <div class="row g-2">
                <div class="col-6">
                    <input type="radio" id="tigerlabel" name="select_bet_list" value="tiger" hidden>
                    <label for="tigerlabel" class="bet-label bg-blue w-100 text-center">Tiger</label>
                </div>
                <div class="col-6">
                    <input type="radio" id="elephantlabel" name="select_bet_list" value="elephant" hidden>
                    <label for="elephantlabel" class="bet-label bg-blue w-100 text-center">Elephant</label>
                </div>
                <div class="col-6">
                    <input type="radio" id="cowlabel" name="select_bet_list" value="cow" hidden>
                    <label for="cowlabel" class="bet-label bg-blue w-100 text-center">Cow</label>
                </div>
                <div class="col-6">
                    <input type="radio" id="crownlabel" name="select_bet_list" value="crown" hidden>
                    <label for="crownlabel" class="bet-label bg-blue w-100 text-center">Crown</label>
                </div>
                <div class="col-4">
                    <input type="radio" id="redlabel" name="select_bet_list" value="red" hidden>
                    <label for="redlabel" class="bet-label bg-red w-100 text-center">Red</label>
                </div>
                 <div class="col-4">
                    <input type="radio" id="greenlabel" name="select_bet_list" value="green" hidden>
                    <label for="greenlabel" class="bet-label bg-green w-100 text-center">Green</label>
                </div>
                 <div class="col-4">
                    <input type="radio" id="yellowlabel" name="select_bet_list" value="yellow" hidden>
                    <label for="yellowlabel" class="bet-label bg-yellow w-100 text-center" style="color:#000;">Yellow</label>
                </div>
              </div>
              <div>
                <p class="ft-sz-12 mg-t-15 mg-b-5" style="color:#94a3b8;">Custom Result Value:</p>
                <input type="text" name="in_bet_value" placeholder="Enter here.." class="w-100 pd-10 br-r-10 bg-black cl-white br-all" style="border-color: rgba(255,255,255,0.1);">
              </div>
            <?php } ?>
            
            <div class="mg-t-30 d-flex gap-2">
                <button class="w-100 pd-12 br-n br-r-12 ft-sz-14 font-weight-bold cl-white bg-primary set_bet_option control_btn">Apply Selection</button>
                <button class="w-50 pd-12 br-n br-r-12 ft-sz-14 font-weight-bold cl-white bg-red bet_dismiss_btn dismiss_btn">Cancel</button>
            </div>
        </div>
      </div>
      
      
      </div>
      
      <div class="restart-footer">
          <div>
              <p style="font-size: 14px; font-weight: 700; color:#fff; margin-bottom: 4px;">Restart Match Execution</p>
              <p style="font-size: 12px; color: #94a3b8; margin: 0;">Would you like to force restart the game engine for this module?</p>
          </div>
          <button class="action-btn btn-restart" onclick="restartGame('<?php echo $game_code; ?>')">
              <i class='bx bx-refresh'></i> Restart Game
          </button>
      </div>
      
    </div>
</div>
      <script>
         var gameTimer = null,RUNNING_TIME=0,PERIOD_ID="";
         let match_details_view = document.querySelector("#match_details_view");
         let in_game_code = document.querySelector("#in_game_code");
         let manual_bet_tv = document.querySelector("#manual_bet_tv");
         let match_timer_tv = document.querySelector("#match_timer_tv");
         
         let match_active_users_tv = document.querySelector("#match_active_users_tv");
         let manual_bet_btn = document.querySelector(".manual_bet_btn");
         let manual_bet_control_view = document.querySelector(".manual_bet_control_view");
         let manual_bet_dialog = document.querySelector(".manual_bet_dialog");
         let input_dialog = document.querySelector(".input_dialog");
         let set_bet_btn = document.querySelector(".set_bet_btn");
         let set_bet_option = document.querySelector(".set_bet_option");
         let bet_dismiss_btn = document.querySelector(".bet_dismiss_btn");
         let in_dismiss_btn = document.querySelector(".in_dismiss_btn");
         let match_contants_view = document.querySelector(".match_contants_view");
         let update_controlls_btn = document.querySelector(".update_controlls_btn");
         
         let in_high_profit = document.querySelector("#in_high_profit");
         let in_trigger_amnt = document.querySelector("#in_trigger_amnt");
         
         let period_id_tv = document.querySelector("#period_id_tv");
         let open_price_tv = document.querySelector("#open_price_tv");
         let tax_amount_tv = document.querySelector("#tax_amount_tv");
         let high_profit_tv = document.querySelector("#high_profit_tv");
         
         let match_red_invested_tv = document.querySelector("#match_red_invested_tv");
         let match_green_invested_tv = document.querySelector("#match_green_invested_tv");
         let match_violet_invested_tv = document.querySelector("#match_violet_invested_tv");
         let match_total_invested_tv = document.querySelector("#match_total_invested_tv");
         let match_color_count_tv = document.querySelector("#match_color_count_tv");
         let update_rule_btn = document.querySelector(".update_rule_btn");
         let in_match_rules = document.querySelector("#in_match_rules");
         
         function setBetOptionView(data){
           if(data!="false" && data!=""){
              if(data=="red"){
                 set_bet_btn.innerHTML = "<i class='bx bxs-lock-alt' ></i>&nbsp;Red";
              }else if(data=="green"){
                 set_bet_btn.innerHTML = "<i class='bx bxs-lock-alt' ></i>&nbsp;Green";
              }else if(data=="greenviolet"){
                 set_bet_btn.innerHTML = "<i class='bx bxs-lock-alt' ></i>&nbsp;Green Violet";
              }else if(data=="redviolet"){
                 set_bet_btn.innerHTML = "<i class='bx bxs-lock-alt' ></i>&nbsp;Red Violet";
              }else if(data!="none"){
                 set_bet_btn.innerHTML = "<i class='bx bxs-lock-alt' ></i>&nbsp;"+data;  
              }else{
                 set_bet_btn.innerHTML = "<i class='bx bx-lock-open-alt' ></i>&nbsp;Set Bet";         
              }
           }else{
            //   bet_set_as_tv.innerHTML = "Not set";
           }
         }
         
        function setUpTimer(totalSeconds){
          clearTimerInterval();
           
          function padTo2Digits(num) {
           return num.toString().padStart(2, '0');
          }
    
          function splitIntoArray(num) {
  	       return Array.from(String(num), Number);
          }

    	  gameTimer = setInterval(function() {
	    
           match_timer_tv.innerHTML = totalSeconds;
        
           if(totalSeconds <= 0){
            clearTimerInterval();
            window.location.reload();
           }else if(totalSeconds%5===0){
            window.location.reload();  
           }
	   
	       totalSeconds--;
        
  	      }, 1000);
           
        }
         
        set_bet_option.addEventListener("click",()=>{
           var color_list_option = document.getElementsByName('select_bet_list');
           var in_bet_value = document.getElementsByName('in_bet_value');
           
           let selected_bet = "";
           for(i = 0; i < color_list_option.length; i++) {
               if(color_list_option[i].checked)
               selected_bet = color_list_option[i].value;
           }
           
           if(in_bet_value.length > 0 && in_bet_value[0].value!=""){
              selected_bet = in_bet_value[0].value;
           }
           
           console.log(selected_bet);
           
           if(in_game_code.value=="AVIATOR" && Number(selected_bet) > 8){
               return;
           }else if(in_game_code.value=="DICE" && (Number(selected_bet) > 95 || Number(selected_bet) < 3)){
               return;
           }else if(in_game_code.value=="WHEELOCITY" && (Number(selected_bet) > 36 || Number(selected_bet) < 0)){
               return;
           }
         
           setManualBet(selected_bet);
        });
         
        set_bet_btn.addEventListener("click",()=>{
           manual_bet_control_view.classList.remove("hide_view");
           manual_bet_dialog.classList.remove("hide_view");
        });
         
        bet_dismiss_btn.addEventListener("click",()=>{
           manual_bet_control_view.classList.add("hide_view");
           manual_bet_dialog.classList.add("hide_view");
        });
         
        document.addEventListener("visibilitychange", () => {
           if (document.hidden) {
              clearTimerInterval();
           } else {
              window.location.reload();
           }
        });
         
        function setManualBet(setBet){
           async function requestFile() {
               try {
                   set_bet_option.classList.remove('hide_view');
                   const response =
                       await fetch("set-manual-bet.php?set-bet=" + setBet+"&project=<?php echo $game_code; ?>", {
                           method: "GET"
                       });
         
                   const resp = await response.json();
         
                   if (resp.status_code == "success") {
                     setBetOptionView(setBet);
                     manual_bet_control_view.classList.add("hide_view");
                     manual_bet_dialog.classList.add("hide_view");
                   } else {
                     alert('Oops! Failed to set bet!');
                   }
         
               } catch (error) {
                  set_bet_option.classList.remove('hide_view');
                  alert('Oops! Something went wrong! Failed to update!');  
               }
           }
         
           if (setBet!="" && setBet!=undefined) {
               set_bet_option.classList.add('hide_view');
               requestFile();
           }
        }
         
        function clearTimerInterval(){
          if(gameTimer!=null){
            clearInterval(gameTimer);
          }  
        }

        
        function restartGame(project_name){
          if(confirm("Are you sure you want to restart the game?")){
            window.open("restart-game.php?project="+project_name);
          }
        }
        
        window.addEventListener("load", (event) => {
            setUpTimer(match_timer_tv.innerHTML);
        });
         
      </script>
   </body>
</html>

