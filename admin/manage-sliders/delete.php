<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_gift")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

if(!$IS_PRODUCTION_MODE){
  echo "Game is under Demo Mode. So, you can not add or modify.";
  return;
}

if(isset($_GET['id'])){
  $uniq_id = mysqli_real_escape_string($conn,$_GET["id"]);
}else{
  return;
}

$select_sql = "SELECT * FROM tblsliders WHERE id ='{$uniq_id}' ";
$select_result = mysqli_query($conn, $select_sql) or die('error');

$delete_sql = "DELETE FROM tblsliders WHERE id ='{$uniq_id}' ";
if (mysqli_query($conn, $delete_sql)) { ?>
  <script>
      alert('Slider Deleted!');
      window.close();
  </script>
<?php }else{ ?>
  <script>
      alert('Failed to delete!');
  </script>
<?php } ?>