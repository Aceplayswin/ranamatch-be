<?php
error_reporting(0);
header('Content-Type: application/json');

include "../security/constants.php";
date_default_timezone_set("Asia/Kolkata");

$server_db = "localhost";
$hostname_db = "winco";
$username_db = "winco";
$password_db = "winco";

try {
    if ($conn = mysqli_connect($server_db, $username_db, $password_db, $hostname_db)) {
        $is_db_connected = "true";
    } else {
        throw new Exception("Unable to connect");
    }
} catch (Throwable $e) {
    echo $e->getMessage();
    echo "Please setup extension properly.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function decrypt($data, $key)
    {
        return openssl_decrypt(base64_decode($data), "AES-256-ECB", $key, OPENSSL_RAW_DATA);
    }

    function encrypt($data, $key)
    {
        return base64_encode(openssl_encrypt($data, "AES-256-ECB", $key, OPENSSL_RAW_DATA));
    }

    $json = file_get_contents("php://input");
    $json_data = json_decode($json, true);

    // Dynamic Agency Overrides: Supports multiple accounts
    $incoming_agency = $json_data["agency_uid"] ?? "";
    if ($incoming_agency == "f1c978d202831562722aab59824e3cc5") {
        $AES_SECRET_KEY = "ee4c17cb3d1eedd3c751ae3a232aa92a";
        $PLAYER_PREFIX = "hc4d11";
    }

    $payload = $json_data["payload"];
    if ($payload) {
        $data = json_decode(decrypt($payload, $AES_SECRET_KEY), true);
    }
    $log_data = date('Y-m-d H:i:s') . " - Server Request: " . json_encode($json) . "\n - Bet Request: " . json_encode($data) . "\n";
    file_put_contents("bet_logs.txt", $log_data, FILE_APPEND);

    if (!$data) {
        exit;
    }

    $const_game_uid = $data["game_uid"];
    $win_amount = floatval($data["win_amount"]);
    $bet_amount = floatval($data["bet_amount"]);

    // Robust parsing for sports vs other games
    $match_details = "";
    $bet_type = "";
    $odds = "";
    $const_game_name = "";

    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            $match_details = $sports_data["leagueName_en"] ?? $sports_data["sportTypeName_en"] ?? "";
            $bet_type = $sports_data["betChoice_en"] ?? $sports_data["marketName_en"] ?? "";
            $odds = $sports_data["odds"] ?? "";
            $const_game_name = "saba sports";
        }
    } else if (isset($data["bet_data"])) {
        $match_details = $data["bet_data"]["leagueName_en"] ?? "";
        $bet_type = $data["bet_data"]["betChoice_en"] ?? "";
        $odds = $data["bet_data"]["odds"] ?? "";
    }

    // Robust User ID Extraction (Handled multiple prefixes)
    $account_clean = explode('_', $data["member_account"])[0];
    $const_user_id = str_replace($PLAYER_PREFIX, "", $account_clean);

    $select_sql = "SELECT tbl_balance, tbl_bonus_balance, tbl_sports_bonus, tbl_requiredplay_balance, tbl_withdrawl_balance, tbl_joined_under, tbl_account_status, tbl_active_bonus_id, tbl_is_bonus_locked FROM tblusersdata WHERE tbl_uniq_id='$const_user_id'";
    $select_query = mysqli_query($conn, $select_sql);

    if (mysqli_num_rows($select_query) > 0) {
        $res_data = mysqli_fetch_assoc($select_query);

        if ($res_data["tbl_account_status"] == "true") {
            $real_balance = floatval($res_data["tbl_balance"]);
            $bonus_balance = floatval($res_data["tbl_bonus_balance"]) + floatval($res_data["tbl_sports_bonus"]);
            $combined_balance = $real_balance + $bonus_balance;
            $credit_amount = $combined_balance - $bet_amount + $win_amount;

            if ($bet_amount == 0 && $win_amount == 0) {
                $payloadData = json_encode(["credit_amount" => $credit_amount, "timestamp" => round(microtime(true) * 1000)]);
                $payload = encrypt($payloadData, $AES_SECRET_KEY);
                echo json_encode(["code" => 0, "msg" => "", "payload" => $payload]);
                exit;
            }

            // Deduct / Calculate Balances
            $remaining_bet = floatval($bet_amount);
            $new_bonus_balance = floatval($res_data["tbl_bonus_balance"]);
            $new_sports_bonus = floatval($res_data["tbl_sports_bonus"]);

            if ($new_bonus_balance > 0 && $remaining_bet > 0) {
                $deduct = min($new_bonus_balance, $remaining_bet);
                $new_bonus_balance -= $deduct;
                $remaining_bet -= $deduct;
            }
            if ($new_sports_bonus > 0 && $remaining_bet > 0) {
                $deduct = min($new_sports_bonus, $remaining_bet);
                $new_sports_bonus -= $deduct;
                $remaining_bet -= $deduct;
            }

            $updated_balance = $real_balance - $remaining_bet + floatval($win_amount);
            if ($updated_balance < 0)
                $updated_balance = 0;

            $tbl_play_updated_balance = floatval($res_data["tbl_requiredplay_balance"]) + floatval($win_amount) - floatval($bet_amount);
            if ($tbl_play_updated_balance < 0)
                $tbl_play_updated_balance = 0;

            $bonus_completed = false;
            if (floatval($res_data["tbl_requiredplay_balance"]) > 0 && $tbl_play_updated_balance <= 0) {
                $bonus_completed = true;
                $updated_balance += $new_bonus_balance + $new_sports_bonus;
                $new_bonus_balance = 0;
                $new_sports_bonus = 0;
            }

            $match_status = ($win_amount > 0) ? "profit" : "loss";

            // AGGREGATION LOGIC (Reverted per user request)
            $select_sql = $conn->prepare("SELECT * FROM tblmatchplayed WHERE tbl_user_id = ? AND tbl_period_id = ? ORDER BY tbl_time_stamp DESC LIMIT 1");
            $select_sql->bind_param("ss", $const_user_id, $const_game_uid);
            $select_sql->execute();
            $result = $select_sql->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $update_sql = $conn->prepare("UPDATE tblmatchplayed SET tbl_invested_on = tbl_invested_on + ?, tbl_match_invested = tbl_match_invested + ?, tbl_match_profit = tbl_match_profit + ?, tbl_match_cost = tbl_match_cost + ?, tbl_last_acbalance = ?, tbl_match_status = ?, tbl_match_details = ?, tbl_bet_type = ?, tbl_odds = ? WHERE tbl_user_id = ? AND tbl_period_id = ?");
                $update_sql->bind_param("ddddsssssss", $bet_amount, $bet_amount, $win_amount, $bet_amount, $updated_balance, $match_status, $match_details, $bet_type, $odds, $const_user_id, $const_game_uid);
                $update_sql->execute();
            } else {
                $match_order_id = "API" . strtoupper(bin2hex(random_bytes(6)));
                $curr_date_time = date("d-m-Y h:i a");
                if ($const_game_name == "") {
                    $const_game_name = "Game " . $const_game_uid;
                }

                $insert_sql = $conn->prepare("INSERT INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_lot_size, tbl_match_invested, tbl_match_fee, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $const_zero = "0";
                $insert_sql->bind_param("ssssddddsdsssssss", $const_user_id, $match_order_id, $const_game_uid, $bet_amount, $bet_amount, $const_zero, $bet_amount, $const_zero, $win_amount, $match_status, $updated_balance, $match_status, $const_game_name, $match_details, $bet_type, $odds, $curr_date_time);
                $insert_sql->execute();
            }

            // Final User Balance Update
            if ($bonus_completed) {
                $update_user = $conn->prepare("UPDATE tblusersdata SET tbl_balance = ?, tbl_bonus_balance = 0, tbl_sports_bonus = 0, tbl_requiredplay_balance = 0, tbl_active_bonus_id = 0, tbl_is_bonus_locked = 0 WHERE tbl_uniq_id = ?");
                $update_user->bind_param("ds", $updated_balance, $const_user_id);
            } else {
                $update_user = $conn->prepare("UPDATE tblusersdata SET tbl_balance = ?, tbl_bonus_balance = ?, tbl_sports_balance = ?, tbl_requiredplay_balance = ? WHERE tbl_uniq_id = ?");
                $update_user->bind_param("dddds", $updated_balance, $new_bonus_balance, $new_sports_bonus, $tbl_play_updated_balance, $const_user_id);
            }
            $update_user->execute();

            $payloadData = json_encode(["credit_amount" => $credit_amount, "timestamp" => round(microtime(true) * 1000)]);
            $payload = encrypt($payloadData, $AES_SECRET_KEY);
            echo json_encode(["code" => 0, "msg" => "", "payload" => $payload]);
            exit;
        }
    }
}
mysqli_close($conn);
?>