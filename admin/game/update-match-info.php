<?php
header("Access-Control-Allow-Origin: *");
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include "../../security/constants.php";
include "../../mainhandler/get-period-id.php";
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_match_control")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    return;
}


date_default_timezone_set('Asia/Kolkata');
$curr_date = date('d-m-Y');
$curr_time = date('h:i:s a');
$curr_date_time = $curr_date.' '.$curr_time;

$resArr = array();
$resArr['data'] = array();

$resArr['total_bet_count'] = "0";
$resArr['total_red_invested'] = "0";
$resArr['total_green_invested'] = "0";
$resArr['total_violet_invested'] = "0";
$resArr['total_invested'] = "0";
$resArr['active_users'] = 0;
$resArr['bet_set_as'] = "";


if (!isset($_GET["project"])) {
 header('location:../index.php');
}else{
  $project_name = $_GET["project"];
}

if($project_name!=""){

$games_sql = "SELECT * FROM tblgamecontrols WHERE tbl_service_name='{$project_name}' ";
$games_result = mysqli_query($conn, $games_sql) or die('search failed');

if (mysqli_num_rows($games_result) > 0){
    $res_data = mysqli_fetch_assoc($games_result);
    $service_value = $res_data['tbl_service_value'];
    $service_times = $res_data['tbl_service_times'];
    
    $service_value_arr = explode(",", $service_value);
    $service_times_arr = explode(",", $service_times);
    
    $game_play_time = $service_times_arr[0];
    $resArr['bet_set_as'] = $service_value_arr[2];
}else{
    echo "Invalid Project Name!";
    return;
}
    
    
$generatePeriod = new GeneratePeriod($game_play_time);
$generatePeriod->setupTimes();
$new_match_period_id =
$generatePeriod->getDateTime() . $generatePeriod->getPeriodId();

function getTimeDiff($datetime_1,$datetime_2){
 $timestamp1 = strtotime($datetime_2);
 $timestamp2 = strtotime($datetime_1);

 $diff = $timestamp2 - $timestamp1;
 return $diff;   
}

$select_sql = "SELECT * FROM tblmatchplayed WHERE tbl_period_id='{$new_match_period_id}' AND tbl_project_name='{$project_name}' ";
$select_query = mysqli_query($conn,$select_sql);
$numRows = mysqli_num_rows($select_query);

if($numRows > 0){
 $temp_red_invested = 0;
 $temp_green_invested = 0;
 $temp_violet_invested = 0;

 while($row = mysqli_fetch_assoc($select_query)){
  $investedOn = $row['tbl_invested_on'];
  $investedAmnt = $row['tbl_match_cost'];
  
  if ($investedOn == "green" || $investedOn == "1" || $investedOn == "3" || $investedOn == "7" || $investedOn == "9"){
    $temp_green_invested += $investedAmnt;
  }else if ($investedOn == "red" || $investedOn == "2" || $investedOn == "4" || $investedOn == "6" || $investedOn == "8"){
    $temp_red_invested += $investedAmnt;
  }else{
    $temp_violet_invested += $investedAmnt;
  }
 }
 
 $resArr['total_bet_count'] = $numRows;
 $resArr['total_red_invested'] = $temp_red_invested;
 $resArr['total_green_invested'] = $temp_green_invested;
 $resArr['total_violet_invested'] = $temp_violet_invested;
 $resArr['total_invested'] = $temp_red_invested+$temp_green_invested+$temp_violet_invested;
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

  $resArr['diff'] = $curr_date_time.'/'.$activeDateTime.'/ Time: '.getTimeDiff($curr_date_time,$activeDateTime);
  $resArr['active_users'] = (string) $tempActiveUsers;
}

// set game data
$generatePeriod = new GeneratePeriod($game_play_time);
$generatePeriod->setupTimes();
$new_match_period_id =
        $generatePeriod->getDateTime() . $generatePeriod->getPeriodId();
$match_remaining_seconds = $generatePeriod->getRemainingSec();
$match_next_date_time = $generatePeriod->getFutureDateTime();

$resArr['game_period_id'] = $new_match_period_id;
$resArr['game_seconds_remaining'] = $match_remaining_seconds;
$resArr['game_next_datetime'] = $match_next_date_time;

$conn->close();

}

echo json_encode($resArr);
?>