<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_admins")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

if(isset($_GET['uniq-id'])){
  $uniq_id = mysqli_real_escape_string($conn,$_GET["uniq-id"]);
}else{
  return;
}

if($uniq_id=="RzuujDpJOiu6RmiyENi0PUrPR115OR"){
    echo "This ID can't be deleted!";
    return;
}

$select_sql = "SELECT * FROM tbladmins";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 1){
  $delete_sql = "DELETE FROM tbladmins WHERE tbl_uniq_id='$uniq_id' ";
  if (mysqli_query($conn, $delete_sql)) {
    echo "<script>alert('Account Deleted!');window.close();</script>";
  }else{
    echo "<script>alert('Failed to deleted!');window.close();</script>";
  } 
}else{
    echo "<script>alert('Last Account Can not be deleted!');window.close();</script>";
}

?>