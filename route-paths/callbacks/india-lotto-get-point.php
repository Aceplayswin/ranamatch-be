<?php
/**
 * India Lotto (Running10) - GET POINT (Check Player Balance)
 * Endpoint: /india-lotto-callback/get_point
 * 
 * Returns the player's current balance.
 * Balance format: 1000 = 1 rupee (last 3 digits are decimals)
 */

define("ACCESS_SECURITY", "true");
include __DIR__ . '/../../security/config.php';
include __DIR__ . '/../../security/constants.php';

// 1. Get POST data
$raw_data = $_POST['Data'] ?? '';
$received_sign = $_POST['Sign'] ?? '';
$received_platform = $_POST['PlatformCode'] ?? '';

// Log incoming request
$log = date('Y-m-d H:i:s') . " | GET_POINT | Data: $raw_data | Sign: $received_sign | Platform: $received_platform\n";
file_put_contents(__DIR__ . "/../callback_logs.txt", $log, FILE_APPEND);

// 2. Validate MD5 Signature
$expected_sign = md5($raw_data . $INDIALOTTO_SECRET_KEY);

if ($received_sign !== $expected_sign || $received_platform !== $INDIALOTTO_PLATFORM_CODE) {
    echo json_encode([
        "code" => 9999,
        "msg" => "sign_fail",
        "data" => ""
    ]);
    exit;
}

// 3. Parse data
$data = json_decode($raw_data, true);
$acc = $data['acc'] ?? '';

// 4. Extract user ID from account (remove platform prefix)
// Account format: velplaytest_8091921 -> we need 8091921
$user_id = str_replace($INDIALOTTO_PLATFORM_CODE . "_", "", $acc);

// 5. Query balance from database
$user_id_safe = mysqli_real_escape_string($conn, $user_id);
$user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id_safe' LIMIT 1");

if ($user_row = mysqli_fetch_assoc($user_res)) {
    $balance_rupees = (float)$user_row['tbl_balance'] + (float)$user_row['tbl_bonus_balance'];
    // Convert to provider format: 1 rupee = 1000
    $balance_points = (int)round($balance_rupees * 1000);
    
    $response = [
        "code" => 0,
        "msg" => "success",
        "data" => json_encode(["balance" => $balance_points])
    ];
} else {
    $response = [
        "code" => 101,
        "msg" => "NO_User",
        "data" => ""
    ];
}

// 6. Log and respond
$log = date('Y-m-d H:i:s') . " | GET_POINT RESPONSE | User: $acc | Response: " . json_encode($response) . "\n";
file_put_contents(__DIR__ . "/../callback_logs.txt", $log, FILE_APPEND);

echo json_encode($response);
