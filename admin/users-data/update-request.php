<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

if(!$IS_PRODUCTION_MODE){
  echo "Game is under Demo Mode. So, you can not add or modify.";
  return;
}

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

if(!isset($_GET['user-id'])){
  echo "invalid request";
  return;
}else{
  $user_id = mysqli_real_escape_string($conn,$_GET['user-id']);
}

if(!isset($_GET['request-type'])){
  echo "invalid request";
  return;
}else{
  $request_type = mysqli_real_escape_string($conn,$_GET['request-type']);
}

$select_sql = "SELECT tbl_account_status FROM tblusersdata WHERE tbl_uniq_id='$user_id' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $update_sql = "UPDATE tblusersdata SET tbl_account_status='{$request_type}' WHERE tbl_uniq_id='{$user_id}'";
  $update_result = mysqli_query($conn, $update_sql) or die('error');
  if ($update_result){ ?>

  <script>
    alert('Request updated!');
    window.close();
  </script>

<?php }else{ ?>
  
  <script>
    alert('Failed to update!');
    window.close();
  </script>

<?php } } ?> ?>