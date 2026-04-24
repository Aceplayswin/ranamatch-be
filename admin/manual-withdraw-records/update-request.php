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
$remark = isset($_GET['remark']) ? mysqli_real_escape_string($conn, $_GET['remark']) : "";
$select_sql = "SELECT * FROM tbluserswithdraw WHERE tbl_uniq_id=? AND (tbl_request_status='pending' OR tbl_request_status='approve')";
$stmt = mysqli_prepare($conn, $select_sql);
mysqli_stmt_bind_param($stmt, "s", $order_id);
mysqli_stmt_execute($stmt);
$select_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($select_result) > 0){
    $select_res_data = mysqli_fetch_assoc($select_result);
    $user_id = $select_res_data['tbl_user_id'];
    $withdraw_requested_amount = $select_res_data['tbl_withdraw_request'];

    if ($order_type == "rejected") {
        $update_sql = "UPDATE tbluserswithdraw SET tbl_request_status='rejected', tbl_remark=? WHERE tbl_uniq_id=?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ss", $remark, $order_id);
        if (mysqli_stmt_execute($stmt)) {
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "success", "message" => "Manual withdrawal rejected!", "type" => "rejected"]);
            } else {
                echo "<script>alert('Manual withdrawal rejected!');window.location.href='index.php';</script>";
            }
        } else {
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "error", "message" => "Failed to reject withdrawal!"]);
            } else {
                echo "<script>alert('Failed to reject withdrawal!');window.location.href='index.php';</script>";
            }
        }
    } elseif ($order_type == "success") {
        // Check balance FIRST
        $check_bal_sql = "SELECT tbl_balance FROM tblusersdata WHERE tbl_uniq_id = ?";
        $stmt_bal = mysqli_prepare($conn, $check_bal_sql);
        mysqli_stmt_bind_param($stmt_bal, "s", $user_id);
        mysqli_stmt_execute($stmt_bal);
        $bal_res = mysqli_stmt_get_result($stmt_bal);
        $bal_data = mysqli_fetch_assoc($bal_res);
        $current_balance = $bal_data['tbl_balance'] ?? 0;

        if ($current_balance < $withdraw_requested_amount) {
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "error", "message" => "Insufficient user balance! Approval cancelled."]);
            } else {
                echo "<script>alert('Insufficient user balance! Approval cancelled.');window.location.href='index.php';</script>";
            }
            exit;
        }

        // Deduct balance SECOND
        $deduct_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance - ? WHERE tbl_uniq_id = ?";
        $stmt_ded = mysqli_prepare($conn, $deduct_sql);
        mysqli_stmt_bind_param($stmt_ded, "ds", $withdraw_requested_amount, $user_id);
        if (mysqli_stmt_execute($stmt_ded)) {
            // FINALLY update status to success
            $update_sql = "UPDATE tbluserswithdraw SET tbl_request_status='success', tbl_remark=? WHERE tbl_uniq_id=?";
            $stmt_final = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_final, "ss", $remark, $order_id);
            mysqli_stmt_execute($stmt_final);
            
            if (isset($_GET['ajax'])) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Manual withdrawal approved!", 
                    "type" => "success",
                    "data" => [
                        "user_id" => $user_id,
                        "amount" => $withdraw_requested_amount,
                        "order_id" => $order_id
                    ]
                ]);
            } else {
                echo "<script>alert('Manual withdrawal approved and balance deducted!');window.location.href='index.php';</script>";
            }
        } else {
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "error", "message" => "Failed to deduct balance! Approval cancelled."]);
            } else {
                echo "<script>alert('Failed to deduct balance! Approval cancelled.');window.location.href='index.php';</script>";
            }
        }
    } else {
        if (isset($_GET['ajax'])) {
            echo json_encode(["status" => "error", "message" => "Unknown order type!"]);
        } else {
            echo "<script>alert('Unknown order type!');window.location.href='index.php';</script>";
        }
    }
} else {
    if (isset($_GET['ajax'])) {
        echo json_encode(["status" => "error", "message" => "Invalid withdrawal request!"]);
    } else {
        echo "<script>alert('Invalid withdrawal request!');window.location.href='index.php';</script>";
    }
}

mysqli_close($conn);
?>