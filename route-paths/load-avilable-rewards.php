<?php
header("Access-Control-Allow-Origin: *");
define("ACCESS_SECURITY","true");
include 'security/config.php';

$resArr = array();
$resArr['data'] = array();

if($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'GET') {

$user_id = "";
$content = 5;

if(isset($_POST['USER_ID'])){
 $user_id = mysqli_real_escape_string($conn,$_POST["USER_ID"]);   
}

if(isset($_GET['USER_ID'])){
 $user_id = mysqli_real_escape_string($conn,$_GET["USER_ID"]);   
}


$select_sql = "SELECT * FROM availablerewards WHERE reward_status='true' ORDER BY id ASC LIMIT $content ";
$select_query = mysqli_query($conn,$select_sql);
    
while($row = mysqli_fetch_assoc($select_query)){
  $index['r_id'] = $row['reward_id'];
  $index['r_title'] = $row['reward_title'];
  $index['r_bonus'] = $row['reward_bonus'];
  $index['r_applied'] = "false";
  
  if($row['reward_id']!=""){
    $select_reward_sql = "SELECT * FROM othertransactions WHERE user_id='{$user_id}' AND type='{$row['reward_id']}' ";
    $select_reward_query = mysqli_query($conn,$select_reward_sql);
    
    if(mysqli_num_rows($select_reward_query) > 0){
        $index['r_applied'] = "true";
    }
  }

  array_push($resArr['data'], $index);
}

if(mysqli_num_rows($select_query) > 0){ 
  $resArr['status_code'] = "success";
}else{
  $resArr['status_code'] = "404";
}

$conn->close();
echo json_encode($resArr);
}
?>