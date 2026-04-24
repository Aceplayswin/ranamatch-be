<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
// Check if user has settings access
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_settings")=="false"){
        die("You're not allowed to perform this action.");
    }
}else{
    header('location:../logout-account');
    exit;
}

if(!$IS_PRODUCTION_MODE){
    die("Game is under Demo Mode. So, you can not modify.");
}

if(isset($_GET['id']) && isset($_GET['status'])){
    $id = intval($_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    mysqli_query($conn, "UPDATE tbl_offer_promotions SET status = '$status' WHERE id = $id");
}

header("Location: index.php?msg=StatusUpdated");
exit;
?>
