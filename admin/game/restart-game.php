<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
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
    header('location:../index.php');
}
  
if (!isset($_GET["project"])) {
   header('location:../index.php');
}else{
    $project_name = $_GET["project"];
}
  

if($project_name!=""){
    
  // fetching game settings
  $games_sql = "SELECT * FROM tblgamecontrols WHERE tbl_service_name='{$project_name}' ";
  $games_result = mysqli_query($conn, $games_sql) or die('search failed');

  $updated_service_value = "";
  
  if (mysqli_num_rows($games_result) > 0){
    $res_data = mysqli_fetch_assoc($games_result);
    $service_value = $res_data['tbl_service_value'];
    $service_times = $res_data['tbl_service_times'];
    
    $service_value_arr = explode(",", $service_value);
    $service_times_arr = explode(",", $service_times);
    
    $game_play_time = $service_times_arr[0];
    
    $generatePeriod = new GeneratePeriod($game_play_time);
    $generatePeriod->setupTimes();
    $new_match_period_id =
    $generatePeriod->getDateTime() . $generatePeriod->getPeriodId();
    
    for ($i = 0; $i <= count($service_value_arr); $i++) {
      if($i==0){
        $updated_service_value .= $new_match_period_id.',';
      }else if($i==2){
        $updated_service_value .= 'none,';
      }else{
        $updated_service_value .= $service_value_arr[$i].',';
      }
    }
    
    $updated_service_value = rtrim($updated_service_value, ",");
    
    $update_sql = "UPDATE tblgamecontrols SET tbl_service_value='{$updated_service_value}' WHERE tbl_service_name='{$project_name}'";
    $update_result = mysqli_query($conn, $update_sql) or die('error');
    if ($update_result){ ?>
  
    <script>alert('Game Restarted Successfully!!');window.close();</script>
  
<?php } } } ?>