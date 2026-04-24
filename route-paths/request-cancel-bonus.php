<?php
// API to cancel an active bonus and wipe wagering
if (!isset($conn)) {
    define('ACCESS_SECURITY', 'true');
    require_once __DIR__ . '/../security/config.php';
}

$resArr['status'] = "failed";
$resArr['status_code'] = "unknown_error";
$resArr['message'] = "unknown_error";

$user_id = isset($_GET['USER_ID']) ? mysqli_real_escape_string($conn, $_GET['USER_ID']) : "";
$secret_key = isset($headerObj) ? $headerObj->getAuthorization() : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : "");

if ($user_id == "" || $secret_key == "") {
    $resArr['status_code'] = "invalid_parameters";
    $resArr['message'] = "invalid_parameters";
    echo json_encode($resArr);
    return;
}

// 1. Verify User, Authorization and Active Bonus
$user_sql = "SELECT tbl_active_bonus_id, tbl_requiredplay_balance FROM tblusersdata WHERE tbl_uniq_id='$user_id' AND tbl_auth_secret='$secret_key'";
$user_res = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_res) > 0) {
    $user_data = mysqli_fetch_assoc($user_res);
    $active_bonus_id = (int)$user_data['tbl_active_bonus_id'];
    $required_play = (float)$user_data['tbl_requiredplay_balance'];

    if ($active_bonus_id > 0 || $required_play > 0) {
        $update_sql = "UPDATE tblusersdata SET 
                       tbl_bonus_balance = 0, 
                       tbl_sports_bonus = 0, 
                       tbl_requiredplay_balance = 0, 
                       tbl_active_bonus_id = 0, 
                       tbl_is_bonus_locked = 0 
                       WHERE tbl_uniq_id = '$user_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            // Update redemptions status
            mysqli_query($conn, "UPDATE tbl_bonus_redemptions SET status='cancelled', updated_at=NOW() WHERE user_id='$user_id' AND bonus_id=$active_bonus_id AND status='active'");
            
            // Log Transaction
            mysqli_query($conn, "INSERT INTO tblotherstransactions (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
                                 VALUES ('$user_id', 'app', 'bonus_cancelled', '0', 'Bonus manually cancelled by user', NOW())");
            
            $resArr['status'] = "success";
            $resArr['message'] = "Bonus successfully cancelled and wagering reset.";
        } else {
            $resArr['message'] = "Failed to update user data.";
        }
    } else {
        $resArr['message'] = "You do not have an active bonus to cancel.";
    }
} else {
    $resArr['status_code'] = "authorization_failed";
    $resArr['message'] = "Authorization failed. Please re-login.";
}

echo json_encode($resArr);
?>
