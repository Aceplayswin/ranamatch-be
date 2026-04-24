<?php
header("Access-Control-Allow-Origin: *");
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';

session_start();

if (!isset($_SESSION["pb_admin_user_id"])) {
 header('location:../index.php');
}

if (!isset($_SESSION["pb_admin_access"])) {
  header('location:../index.php');
}else{
  $account_access = $_SESSION["pb_admin_access"];
  $account_access_arr = explode (",", $account_access);
}
 
if (in_array("access_users_data", $account_access_arr)){
}else{
  echo "You're not allowed! Please grant the access.";
  return;
}

if(!isset($_GET['access-code'])){
  echo "request block";
  return;
}else{
  $admin_acccess = mysqli_real_escape_string($conn,$_GET['access-code']);
}
 
if($admin_acccess!=$AdminIDAccessKey){
 echo "request block";
 return;
}

if(!isset($_GET['user-id'])){
  echo "invalid request";
  return;
}else{
  $user_id = mysqli_real_escape_string($conn,$_GET['user-id']);
}

if(isset($_GET['user-id'])){
  $user_id = mysqli_real_escape_string($conn,$_GET["user-id"]);
}

$select_sql = $conn->prepare("SELECT bank_account FROM userscard WHERE user_id = ? ");
$select_sql->bind_param("s",$user_id);
$select_sql->execute();

if ($select_sql->error != "") { ?>
  <script>
      alert('SQL ERROR!');
  </script>
 <?php }else{
 $select_result = $select_sql->get_result()->fetch_all(MYSQLI_ASSOC);

 if (count($select_result) > 0) {
  $delete_sql = "DELETE FROM userscard WHERE user_id='$user_id' ";

  if (mysqli_query($conn, $delete_sql)) { ?>
    <script>
      alert('Card deleted successfully!');
      window.close();
    </script>
  <?php } else { ?>
   <script>
      alert('Failed to delete card!');
      window.close();
   </script>
 <?php } }else{ ?>
  <script>
      alert('No Card Found!');
      window.close();
  </script>
 <?php }

 mysqli_close($conn);
}
?>