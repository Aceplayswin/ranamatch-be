<?php
/**
 * India Lotto (Running10) - CHANGE POINT (Update Player Balance)
 * Endpoint: /india-lotto-callback/change_point
 * 
 * Handles: Betting (type=3), Payout (type=4), Refund (type=5), Jackpot (type=9)
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
$log = date('Y-m-d H:i:s') . " | CHANGE_POINT | Data: $raw_data | Sign: $received_sign | Platform: $received_platform\n";
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
$type = (int)($data['type'] ?? 0);
$game_id = $data['game_id'] ?? '';
$order_id = $data['order_id'] ?? '';
$buy_order_id = $data['buy_order_id'] ?? '';
$balance_points = (int)($data['balance'] ?? 0); // In provider format: 1000 = 1 rupee
$gift = (int)($data['gift'] ?? 0);

// 4. Extract user ID from account (remove platform prefix)
$user_id = str_replace($INDIALOTTO_PLATFORM_CODE . "_", "", $acc);
$user_id_safe = mysqli_real_escape_string($conn, $user_id);

// 5. Convert balance from provider format to rupees
// Provider: 1000 = 1 rupee
$amount_rupees = $balance_points / 1000;

// 6. Check if this order_id was already processed (prevent duplicate transactions)
$order_id_safe = mysqli_real_escape_string($conn, $order_id);
$dup_check = mysqli_query($conn, "SELECT id FROM tbl_india_lotto_transactions WHERE order_id = '$order_id_safe' LIMIT 1");
if ($dup_check && mysqli_num_rows($dup_check) > 0) {
    // Already processed - return current balance
    $user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id_safe' LIMIT 1");
    $user_row = mysqli_fetch_assoc($user_res);
    $current_balance = ((float)$user_row['tbl_balance'] + (float)$user_row['tbl_bonus_balance']) * 1000;
    
    echo json_encode([
        "code" => 0,
        "msg" => "success",
        "data" => json_encode(["balance" => (int)round($current_balance)])
    ]);
    exit;
}

// 7. Get current user balance
$user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id_safe' LIMIT 1");

if (!$user_row = mysqli_fetch_assoc($user_res)) {
    echo json_encode([
        "code" => 101,
        "msg" => "NO_User",
        "data" => ""
    ]);
    exit;
}

$current_balance = (float)$user_row['tbl_balance'];

// 8. Fetch Game Name for history
$game_name = "India Lotto";
$game_id_safe = mysqli_real_escape_string($conn, $game_id);
$game_query = mysqli_query($conn, "SELECT game_name FROM tbl_games WHERE game_uid = '$game_id_safe' LIMIT 1");
if ($game_query && $game_row = mysqli_fetch_assoc($game_query)) {
    $game_name = $game_row['game_name'];
}
$game_name_safe = mysqli_real_escape_string($conn, $game_name);

// 8.1 Process based on type
$success = false;
$description = "";
$trans_type = "game";
$curr_date_time = date('d-m-Y h:i A');

switch ($type) {
    case 3: // Betting - DEDUCT money
        if ($current_balance < $amount_rupees) {
            echo json_encode([
                "code" => 110,
                "msg" => "Insufficient balance",
                "data" => ""
            ]);
            exit;
        }
        $success = mysqli_query($conn, "UPDATE tblusersdata SET tbl_balance = tbl_balance - $amount_rupees WHERE tbl_uniq_id = '$user_id_safe' AND tbl_balance >= $amount_rupees");
        $description = "$game_name Bet";
        $trans_type = "game_bet";
        break;

    case 4: // Payout - ADD money (winning)
        $success = mysqli_query($conn, "UPDATE tblusersdata SET tbl_balance = tbl_balance + $amount_rupees WHERE tbl_uniq_id = '$user_id_safe'");
        $description = "$game_name Win";
        $trans_type = "game_win";
        break;

    case 5: // Refund - ADD money back
        $success = mysqli_query($conn, "UPDATE tblusersdata SET tbl_balance = tbl_balance + $amount_rupees WHERE tbl_uniq_id = '$user_id_safe'");
        $description = "$game_name Refund";
        $trans_type = "game_refund";
        break;

    case 9: // Jackpot Payout - ADD money
        $success = mysqli_query($conn, "UPDATE tblusersdata SET tbl_balance = tbl_balance + $amount_rupees WHERE tbl_uniq_id = '$user_id_safe'");
        $description = "India Lotto Jackpot Payout";
        $trans_type = "game_win";
        break;

    default:
        echo json_encode([
            "code" => 1,
            "msg" => "Unknown type: $type",
            "data" => ""
        ]);
        exit;
}

if ($success) {
    // 9. Record the transaction (Wrap in try/catch or safety check)
    $desc_safe = mysqli_real_escape_string($conn, $description);
    $game_id_safe = mysqli_real_escape_string($conn, $game_id);
    $buy_order_safe = mysqli_real_escape_string($conn, $buy_order_id);
    
    // Log before insert
    file_put_contents(__DIR__ . "/../callback_logs.txt", date('Y-m-d H:i:s') . " | DEBUG | Inserting transaction...\n", FILE_APPEND);
    
    mysqli_query($conn, "INSERT INTO tbl_india_lotto_transactions 
        (user_id, order_id, buy_order_id, game_id, type, amount, gift, description, created_at) 
        VALUES ('$user_id_safe', '$order_id_safe', '$buy_order_safe', '$game_id_safe', $type, $amount_rupees, $gift, '$desc_safe', NOW())");

    // Try History Updates Safely
    try {
        if ($type == 3) {
            // New Bet
            mysqli_query($conn, "INSERT INTO tblmatchplayed 
                (tbl_user_id, tbl_uniq_id, tbl_project_name, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_lot_size, tbl_match_invested, tbl_match_fee, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_provider, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp) 
                VALUES ('$user_id_safe', '$order_id_safe', '$game_name_safe', '$order_id_safe', 'India Lotto', $amount_rupees, '1', $amount_rupees, 0, 0, 'wait', $current_balance, 'wait', 'India Lotto', 'India Lotto Bet', 'Standard', '1', '$curr_date_time')");
        } else if ($type == 4 || $type == 9) {
            // Payout / Jackpot
            if (!empty($buy_order_id)) {
                $match_res = ($amount_rupees > 0) ? 'win' : 'loss';
                mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_match_status = 'settled', tbl_match_result = '$match_res', tbl_match_profit = $amount_rupees, tbl_updated_at = NOW() WHERE tbl_user_id = '$user_id_safe' AND tbl_period_id = '$buy_order_safe'");
            }
        } else if ($type == 5) {
            // Refund
            if (!empty($buy_order_id)) {
                mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_match_status = 'refunded', tbl_match_profit = $amount_rupees, tbl_updated_at = NOW() WHERE tbl_user_id = '$user_id_safe' AND tbl_period_id = '$buy_order_safe'");
            }
        }
        
        // General Transaction History
        mysqli_query($conn, "INSERT INTO tblotherstransactions 
            (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
            VALUES ('$user_id_safe', 'India Lotto', '$trans_type', $amount_rupees, '$desc_safe', '$curr_date_time')");
            
    } catch (Exception $e) {
        file_put_contents(__DIR__ . "/../callback_logs.txt", date('Y-m-d H:i:s') . " | ERROR | History failed: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // 10. Get updated balance
    $user_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id_safe' LIMIT 1");
    $user_row = mysqli_fetch_assoc($user_res);
    $new_balance = ((float)$user_row['tbl_balance'] + (float)$user_row['tbl_bonus_balance']) * 1000;

    $response = [
        "code" => 0,
        "msg" => "success",
        "data" => json_encode(["balance" => (int)round($new_balance)])
    ];
} else {
    $response = [
        "code" => 1,
        "msg" => "Balance update failed",
        "data" => ""
    ];
}

// 11. Log and respond
$log = date('Y-m-d H:i:s') . " | CHANGE_POINT RESPONSE | User: $acc | Type: $type | Amount: $amount_rupees | Response: " . json_encode($response) . "\n";
file_put_contents(__DIR__ . "/../callback_logs.txt", $log, FILE_APPEND);

echo json_encode($response);
