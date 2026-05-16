<?php
/**
 * India Lotto (Running10) Wallet Callback Handler
 * This handles balance queries and transaction updates from the provider.
 */

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';

// 1. Get the POST data from India Lotto
$raw_data = $_POST['Data'] ?? '';
$received_sign = $_POST['Sign'] ?? '';
$received_platform = $_POST['PlatformCode'] ?? '';

// 2. Security Check: Validate the MD5 Signature
$expected_sign = md5($raw_data . $INDIALOTTO_SECRET_KEY);

if ($received_sign !== $expected_sign || $received_platform !== $INDIALOTTO_PLATFORM_CODE) {
    echo json_encode([
        "code" => 1,
        "msg" => "Invalid Signature or Platform Code",
        "data" => ""
    ]);
    exit;
}

// 3. Parse the Request Data
$data = json_decode($raw_data, true);
$method = $data['method'] ?? '';
$acc = $data['acc'] ?? ''; // This is our const_user_id (tbl_uniq_id)

// 4. Handle Different Methods (getBalance, wager, settle, etc.)
$response_data = [];
$status_code = 0;
$msg = "Success";

switch ($method) {
    case 'getBalance':
        // Query the current balance from our database
        $user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$acc' LIMIT 1");
        if ($user_row = mysqli_fetch_assoc($user_res)) {
            $balance = (float)$user_row['tbl_balance'] + (float)$user_row['tbl_bonus_balance'];
            $response_data = ["balance" => number_format($balance, 2, '.', '')];
        } else {
            $status_code = 1001; // User not found
            $msg = "User not found";
        }
        break;

    case 'transaction':
        // Handle bets (wagers) and wins (settlements)
        // Note: Running10 often combines these into a transaction method or separate ones.
        // We'll implement a generic balance update here.
        $amount = (float)($data['amount'] ?? 0);
        $trans_id = $data['transaction_id'] ?? '';
        
        // Update user balance in database
        // (Simplified logic: update primary balance)
        if ($amount != 0) {
            $update = mysqli_query($conn, "UPDATE tblusersdata SET tbl_balance = tbl_balance + ($amount) WHERE tbl_uniq_id = '$acc'");
            if (mysqli_affected_rows($conn) > 0) {
                // Get new balance
                $user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$acc' LIMIT 1");
                $user_row = mysqli_fetch_assoc($user_res);
                $balance = (float)$user_row['tbl_balance'] + (float)$user_row['tbl_bonus_balance'];
                $response_data = ["balance" => number_format($balance, 2, '.', ''), "transaction_id" => $trans_id];
            } else {
                $status_code = 1;
                $msg = "Balance update failed";
            }
        }
        break;

    default:
        $status_code = 1;
        $msg = "Unknown method: $method";
        break;
}

// 5. Send Response back to India Lotto
echo json_encode([
    "code" => $status_code,
    "msg" => $msg,
    "data" => json_encode($response_data)
]);

// 6. Log the callback for auditing
$log_entry = date('Y-m-d H:i:s') . " | IndiaLotto Callback | Method: $method | User: $acc | Data: $raw_data | Resp: $msg\n";
file_put_contents(__DIR__ . "/../callback_logs.txt", $log_entry, FILE_APPEND);
