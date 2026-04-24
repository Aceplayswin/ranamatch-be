<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

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

$resArr = array();
$resArr['status_code'] = "failed"; 

  
if(!isset($_GET['set-bet'])){
   echo "request block";
   return;
}else{
   $set_bet = mysqli_real_escape_string($conn,$_GET['set-bet']);
}
  
if (!isset($_GET["project"])) {
   header('location:../index.php');
}else{
    $project_name = $_GET["project"];
}
  

if($project_name!="" && $set_bet!=""){
  
  // fetching game settings
  $games_sql = "SELECT * FROM tblgamecontrols WHERE tbl_service_name='{$project_name}' ";
  $games_result = mysqli_query($conn, $games_sql) or die('search failed');


  $updated_service_value = "";
  
  if (mysqli_num_rows($games_result) > 0){
    $res_data = mysqli_fetch_assoc($games_result);
    $service_value = $res_data['tbl_service_value'];
    $service_value_arr = explode(",", $service_value);
    
    for ($i = 0; $i < count($service_value_arr); $i++) {
      if($i==2){
        $updated_service_value .= $set_bet.',';
      }else{
        $updated_service_value .= $service_value_arr[$i].',';
      }
    }
    
    $updated_service_value = rtrim($updated_service_value, ",");

    $update_sql = "UPDATE tblgamecontrols SET tbl_service_value='{$updated_service_value}' WHERE tbl_service_name='{$project_name}'";
    $update_result = mysqli_query($conn, $update_sql) or die('error');
    if ($update_result){
      $resArr['status_code'] = "success"; 
    } 
  }

}

mysqli_close($conn);
echo json_encode($resArr);
?>