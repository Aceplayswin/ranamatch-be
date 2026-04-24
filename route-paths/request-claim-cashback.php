<?php
// Player-Side Cashback Claim API
// included by the router, so $conn is already available.

$resArr['status'] = "error";
$resArr['message'] = "Invalid Request";

// 1. Capture User, Request ID, and Auth Secret
$json_data = json_decode(file_get_contents('php://input'), true);
$user_id = isset($_POST['USER_ID']) ? $_POST['USER_ID'] : ($json_data['USER_ID'] ?? '');
$user_id = mysqli_real_escape_string($conn, $user_id);
$log_id = isset($_POST['LOG_ID']) ? (int)$_POST['LOG_ID'] : (int)($json_data['LOG_ID'] ?? 0);
$secret_key = isset($headerObj) ? $headerObj->getAuthorization() : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : "");

if (empty($user_id) || $log_id === 0 || empty($secret_key)) {
    $resArr['status_code'] = "invalid_parameters";
    $resArr['message'] = "Missing User ID, Log ID, or Authorization";
    echo json_encode($resArr);
    exit;
}

// 1.5 Verify User Authorization
$user_sql = "SELECT id FROM tblusersdata WHERE tbl_uniq_id='$user_id' AND tbl_auth_secret='$secret_key'";
$user_res = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_res) == 0) {
    $resArr['status_code'] = "authorization_failed";
    $resArr['message'] = "authorization_failed";
    echo json_encode($resArr);
    exit;
}

// 2. Validate the Pending Reward
$check_sql = "SELECT cashback_amount, status FROM tbl_cashback_logs 
              WHERE id = $log_id AND user_id = '$user_id' AND status = 'pending_claim' LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);

if ($row = mysqli_fetch_assoc($check_res)) {
    $amount = (float)$row['cashback_amount'];
    
    mysqli_begin_transaction($conn);
    try {
        // 3. Update User Balance
        $update_user = "UPDATE tblusersdata SET tbl_balance = tbl_balance + $amount WHERE tbl_uniq_id = '$user_id'";
        if (!mysqli_query($conn, $update_user)) throw new Exception("Balance Error");
        
        // 4. Mark as Credited
        $update_log = "UPDATE tbl_cashback_logs SET status = 'credited', credited_at = NOW() WHERE id = $log_id";
        if (!mysqli_query($conn, $update_log)) throw new Exception("Log Error");
        
        mysqli_commit($conn);
        $resArr['status'] = "success";
        $resArr['message'] = "Bonus of ₹" . number_format($amount, 2) . " credited successfully!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $resArr['message'] = "Payout failed: " . $e->getMessage();
    }
} else {
    $resArr['message'] = "No pending reward found for this ID.";
}

echo json_encode($resArr);
return;
?>
