<?php
header("Content-Type: application/json");
define("ACCESS_SECURITY", "true");
include '../security/config.php';

include '../security/constants.php';
include '../security/headers-security.php';

$headerObj = new RequestHeaders();
$headerObj->checkAllHeaders();
$secret_key = $headerObj->getAuthorization();

$user_id = mysqli_real_escape_string($conn, trim($_POST['user_id'] ?? ''));
$broadcast_id = (int)($_POST['broadcast_id'] ?? 0);

$resArr = ["status_code" => "error"];

// Debug Log
$log_msg = date('Y-m-d H:i:s') . " - Mark Seen Request: User=$user_id, Broadcast=$broadcast_id, Token=" . substr($secret_key, 0, 10) . "...\n";

if (!empty($user_id) && !empty($secret_key) && $broadcast_id > 0) {
    // Validate user first
    $check_user = mysqli_query($conn, "SELECT id FROM tblusersdata WHERE tbl_uniq_id = '$user_id' AND tbl_auth_secret = '$secret_key'");
    if ($check_user && mysqli_num_rows($check_user) > 0) {
        $sql = "INSERT INTO tbl_broadcast_views (broadcast_id, user_id) VALUES ($broadcast_id, '$user_id')";
        if (mysqli_query($conn, $sql)) {
            $resArr["status_code"] = "success";
            $log_msg .= "   ✅ SUCCESS: Record saved.\n";
        } else {
            $err = mysqli_error($conn);
            $resArr["message"] = $err;
            $log_msg .= "   ❌ SQL ERROR: $err\n";
        }
    } else {
        $resArr["status_code"] = "unauthorized";
        $log_msg .= "   ❌ UNAUTHORIZED: User or Token invalid.\n";
    }
} else {
    $resArr["status_code"] = "invalid_params";
    $log_msg .= "   ❌ INVALID PARAMS: Missing required fields.\n";
}

file_put_contents(__DIR__ . "/broadcast_debug.log", $log_msg, FILE_APPEND);
mysqli_close($conn);
echo json_encode($resArr);
?>
