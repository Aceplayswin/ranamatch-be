<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_withdraw")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        exit;
    }
}else{
    header('location:../logout-account');
    exit;
}

if(!isset($_GET['order-id']) || !isset($_GET['order-type'])){
    echo "Invalid request";
    exit;
}

$order_id = mysqli_real_escape_string($conn, $_GET['order-id']);
$order_type = mysqli_real_escape_string($conn, $_GET['order-type']);

$select_sql = "SELECT * FROM tbluserswithdraw WHERE tbl_uniq_id=? AND (tbl_request_status='pending' OR tbl_request_status='approve')";
$stmt = mysqli_prepare($conn, $select_sql);
mysqli_stmt_bind_param($stmt, "s", $order_id);
mysqli_stmt_execute($stmt);
$select_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($select_result) > 0){
    $select_res_data = mysqli_fetch_assoc($select_result);
    $user_id = $select_res_data['tbl_user_id'];
    $withdraw_requested_amount = $select_res_data['tbl_withdraw_request'];

    $update_sql = "UPDATE tbluserswithdraw SET tbl_request_status=? WHERE tbl_uniq_id=?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "ss", $order_type, $order_id);
    $update_result = mysqli_stmt_execute($stmt);

    if ($update_result){
        if($order_type == "rejected"){
            $update_balance_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE tbl_uniq_id=? AND tbl_account_status = 'true'";
            $stmt = mysqli_prepare($conn, $update_balance_sql);
            mysqli_stmt_bind_param($stmt, "ds", $withdraw_requested_amount, $user_id);
            $update_balance_result = mysqli_stmt_execute($stmt);

            if ($update_balance_result){
                echo "<script>alert('Withdrawal rejected and balance updated!');window.close();</script>";
            } else {
                echo "<script>alert('Withdrawal rejected but failed to update balance!');window.close();</script>";
            }
        } else {
            echo "<script>alert('Withdrawal status updated!');window.close();</script>";
        }
    } else {
        echo "<script>alert('Failed to update withdrawal status!');window.close();</script>";
    }
} else {
    echo "<script>alert('Invalid withdrawal request!');window.close();</script>";
}

mysqli_close($conn);
?>