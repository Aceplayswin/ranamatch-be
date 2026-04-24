<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

// Merchant keys moved to constants.php

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_withdraw") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        exit;
    }
} else {
    header('location:../logout-account');
    exit;
}

if (!isset($_GET['order-id']) || !isset($_GET['order-type'])) {
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

if (mysqli_num_rows($select_result) > 0) {
    $select_res_data = mysqli_fetch_assoc($select_result);
    $user_id = $select_res_data['tbl_user_id'];
    $withdraw_requested_amount = $select_res_data['tbl_withdraw_request'];
    $withdraw_details = $select_res_data['tbl_withdraw_details'];
    $withdraw_details_arr = explode(',', $withdraw_details);
    $actual_name = $withdraw_details_arr[0];
    $bank_account = $withdraw_details_arr[1];

    if ($order_type == "rejected") {
        $update_sql = "UPDATE tbluserswithdraw SET tbl_request_status='rejected' WHERE tbl_uniq_id=?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "s", $order_id);
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Withdrawal request rejected!');window.close();</script>";
        } else {
            echo "<script>alert('Failed to reject withdrawal!');window.close();</script>";
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
            echo "<script>alert('Insufficient user balance! Approval cancelled.');window.close();</script>";
            exit;
        }

        // Deduct balance SECOND
        $deduct_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance - ? WHERE tbl_uniq_id = ?";
        $stmt_ded = mysqli_prepare($conn, $deduct_sql);
        mysqli_stmt_bind_param($stmt_ded, "ds", $withdraw_requested_amount, $user_id);
        if (!mysqli_stmt_execute($stmt_ded)) {
            echo "<script>alert('Failed to deduct balance! Approval cancelled.');window.close();</script>";
            exit;
        }

        // Call Payout API THIRD
        $withdraw_amount = $select_res_data['tbl_withdraw_amount'];
        $url = "https://full2sms.in/api/v2/payout?mid={$PAYOUT_MID}&mkey={$PAYOUT_MKEY}&guid={$PAYOUT_GUID}&type=upi&amount={$withdraw_amount}&upi={$bank_account}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data['status'] == "success") {
            // FINALLY update status to success
            $update_sql = "UPDATE tbluserswithdraw SET tbl_request_status='success' WHERE tbl_uniq_id=?";
            $stmt_final = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_final, "s", $order_id);
            mysqli_stmt_execute($stmt_final);
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "success", "message" => "Withdrawal successfully approved!"]);
            } else {
                echo "<script>alert('Withdrawal successfully approved and processed!');window.close();</script>";
            }
        } else {
            // Log the error for debugging
            $error_log = date('Y-m-d H:i:s') . " - Payout Failed - Order: $order_id, User: $user_id, Response: $response\n";
            file_put_contents("payout_errors.log", $error_log, FILE_APPEND);

            // REFUND if API fails
            $refund_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE tbl_uniq_id = ?";
            $stmt_ref = mysqli_prepare($conn, $refund_sql);
            mysqli_stmt_bind_param($stmt_ref, "ds", $withdraw_requested_amount, $user_id);
            mysqli_stmt_execute($stmt_ref);
            
            if (isset($_GET['ajax'])) {
                echo json_encode(["status" => "error", "message" => "Payout API failed! Balance refunded."]);
            } else {
                echo "<script>alert('Payout API failed! Balance refunded. Request remains pending.');window.close();</script>";
            }
        }
    } else {
        if (isset($_GET['ajax'])) {
            echo json_encode(["status" => "error", "message" => "Unknown order type!"]);
        } else {
            echo "<script>alert('Unknown order type!');window.close();</script>";
        }
    }
} else {
    if (isset($_GET['ajax'])) {
        echo json_encode(["status" => "error", "message" => "Invalid withdrawal request!"]);
    } else {
        echo "<script>alert('Invalid withdrawal request!');window.close();</script>";
    }
}

mysqli_close($conn);
?>