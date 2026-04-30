<?php
error_reporting(E_ALL & ~E_NOTICE); // Better than error_reporting(0) for debugging but clean for production
header('Content-Type: application/json');

define("ACCESS_SECURITY", "true");

include "../security/config.php";
include "../security/constants.php";
date_default_timezone_set("Asia/Kolkata");


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

    $AES_KEY = $AES_SECRET_KEY;
    $PREFIX = $PLAYER_PREFIX;

    // Dynamic Agency Overrides: Supports multiple accounts
    $incoming_agency = $json_data["agency_uid"] ?? "";
    if ($incoming_agency == "f1c978d202831562722aab59824e3cc5") {
        $AES_KEY = "ee4c17cb3d1eedd3c751ae3a232aa92a";
        $PREFIX = "hc4d11";
    }

    $payload = $json_data["payload"];
    if ($payload) {
        $data = json_decode(decrypt($payload, $AES_KEY), true);
    }

    // Diagnostic logging
    $log_data = date('Y-m-d H:i:s') . " - Req: " . $json . " - Decoded: " . json_encode($data) . "\n";
    file_put_contents("bet_logs.txt", $log_data, FILE_APPEND);

    if (!$data) {
        exit;
    }

    $const_game_uid = $data["game_uid"];
    $win_amount = floatval($data["win_amount"]);
    $bet_amount = floatval($data["bet_amount"]);
    $m_order_id = $data["game_round"] ?? $data["serial_number"] ?? ("API" . bin2hex(random_bytes(6)));

    // Standardized Parsing
    $match_details = "";
    $bet_type = "";
    $odds = "";
    $const_game_name = $data["game_name"] ?? $data["gameName"] ?? $data["mGameName"] ?? $data["title"] ?? "";
    $const_game_uid = $data["game_uid"] ?? "N/A";
    $is_sports = false;
    $sports_data = [];



    // 1. Dynamic Auto-Sync (Learn names from incoming requests)
    if ($const_game_name != "" && $const_game_uid != "N/A") {
        $sync_stmt = $conn->prepare("INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE tbl_game_name = VALUES(tbl_game_name)");
        $sync_stmt->bind_param("ss", $const_game_uid, $const_game_name);
        $sync_stmt->execute();
        $sync_stmt->close();
    }

    // 2. Dynamic Database Lookup (Recover names for requests that are missing them)
    if ($const_game_name == "" && $const_game_uid != "N/A") {
        $look_stmt = $conn->prepare("SELECT tbl_game_name FROM tbl_game_names WHERE tbl_game_id = ?");
        $look_stmt->bind_param("s", $const_game_uid);
        $look_stmt->execute();
        $look_res = $look_stmt->get_result();
        if ($look_row = $look_res->fetch_assoc()) {
            $const_game_name = $look_row['tbl_game_name'];
        }
        $look_stmt->close();
    }

    // 3. Static Mappings (Final hardcoded fallbacks for all providers)
    $game_mappings = [
        "8a87aae7a3624d284306e9c6fe1b3e9c" => "Dice",
        "fb2a2ac51303c0a0801dbe6a72d936f7" => "Leprechaun Riches",
        "1c47c7cc3fd4ffc3d31dc095b" => "Dragon Hatch",
        "052442de87321dd97bb91b1a3" => "Baccarat Deluxe",
        "e794bf5717aca371152df192341fe68b" => "Royal Fishing",
        "a04d1f3eb8ccec8a4823bdf18e3f0e84" => "Aviator",
        "4a858d6b74c05260d3ea2762838798c7" => "Lightning Roulette",
        "917c0c51d248c33eb058e3210a2e7371" => "Crazy Time",
        "d496ac5fd91702331133e44b6bd12b26" => "MONOPOLY Live",
        "8ef39602e589bf9f32fc351b1cbb338b" => "Evolution Lobby",
        "d0e052b031dfcdb08d1803f4bcc618ef" => "Ezugi Lobby",
        "56a42a03c0908cf807ba251fc52b0338" => "Hotline",
        "911a32ad38d77f86baf29a2cdb95da05" => "Crazy Pachinko",
        "f7b98e899461bdd49f92afc36b4c0db5" => "Super Andar Bahar",
        "a4f4823bdf18e3f0e848a87aae7a3624" => "Fortune Tiger",
        "61e84c53e079092b5a55e6001c60850c" => "Callbreak",
        "31ee96e1cc0c8b6a3504fb4b732551bf" => "Mega Ball",
        "ed1893c8d1979b00632fc351bcc618ef" => "Dream Catcher",
        "394fe6a2cde24bc487767236cc6eccd6" => "XXXtreme Lightning Roulette",
        "9c81b3e9c8a87aae7a3624d284306e9c" => "Mines",
        "f7b2a2ac51303c0a0801dbe6a72d936f" => "Plinko",
        "b3e9c8a87aae7a3624d284306e9c6fe1" => "Mahjong Ways",
        "a0801dbe6a72d936f7fb2a2ac51303c0" => "Mahjong Ways 2",
        "c0a0801dbe6a72d936f7fb2a2ac51303" => "Lucky Neko",
        "7a3624d284306e9c6fe1b3e9c8a87aae" => "Golden Temple"
    ];

    if ($const_game_name == "" && isset($game_mappings[$const_game_uid])) {
        $const_game_name = $game_mappings[$const_game_uid];
    }

    // SABA/Sports Advanced Extraction
    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            $match_details = $sports_data["leagueName_en"] ?? $sports_data["sportTypeName_en"] ?? "";
            $bet_type = $sports_data["betChoice_en"] ?? $sports_data["marketName_en"] ?? "";
            $odds = $sports_data["odds"] ?? "";

            // Check if it's actually SABA 
            if (isset($sports_data["marketName_en"]) || isset($sports_data["leagueName_en"]) || (isset($data["provider"]) && stripos($data["provider"], "SABA") !== false)) {
                $const_game_name = "SABA Sports";
                $is_sports = true;
                $const_provider = "SABA";

                // SABA sends actual bet amount inside txns for 'ConfirmBet' or 'Bet' actions
                $action = strtolower($sports_data["action"] ?? "");
                if (($action == "bet" || $action == "confirmbet") && $bet_amount == 0 && isset($sports_data["txns"][0])) {
                    $bet_amount = floatval($sports_data["txns"][0]["actualAmount"] ?? $sports_data["txns"][0]["debitAmount"] ?? 0);
                }
            }
        }
    } else if (isset($data["bet_data"])) {
        $match_details = $data["bet_data"]["leagueName_en"] ?? "";
        $bet_type = $data["bet_data"]["betChoice_en"] ?? "";
        $odds = $data["bet_data"]["odds"] ?? "";
        if ($const_game_name == "" && isset($data["bet_data"]["gameName"])) {
            $const_game_name = $data["bet_data"]["gameName"];
        }
    }

    $account_clean = explode('_', $data["member_account"])[0];
    $const_user_id = str_replace($PREFIX, "", $account_clean);

    // Row Lock for transaction safety
    $u_res = mysqli_query($conn, "SELECT * FROM tblusersdata WHERE tbl_uniq_id='$const_user_id' FOR UPDATE");
    if ($u_row = mysqli_fetch_assoc($u_res)) {
        if ($u_row["tbl_account_status"] == "true") {
            $real_bal = floatval($u_row["tbl_balance"]);
            $bonus_bal = floatval($u_row["tbl_bonus_balance"]);
            $sports_bonus = floatval($u_row["tbl_sports_bonus"]);
            $wagering = floatval($u_row["tbl_requiredplay_balance"]);

            $total_avail_bal = $real_bal + $bonus_bal + $sports_bonus;

            // 1. IMPROVED: Identify if this is a known transaction/round BEFORE updating balance
            $merged_record = null;
            $search_ids = array_unique([$m_order_id, $data["game_round"] ?? "", $data["serial_number"] ?? ""]);
            foreach ($search_ids as $sid) {
                if (empty($sid))
                    continue;
                $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                $e_sid = mysqli_real_escape_string($conn, $sid);
                $chk_res = mysqli_query($conn, "SELECT id, tbl_match_cost, tbl_match_profit, tbl_match_status, tbl_match_result FROM tblmatchplayed WHERE tbl_user_id='$e_uid' AND tbl_uniq_id='$e_sid' ORDER BY id DESC LIMIT 1");
                if ($chk_record = mysqli_fetch_assoc($chk_res)) {
                    $merged_record = $chk_record;
                    break;
                }
            }

            // 2. Identify Settlement Action (more robust detection)
            $incoming_action = strtolower($data["action"] ?? $sports_data["action"] ?? "");
            // Check deeper in Ezugi/Evolution txns if possible
            if ($incoming_action == "" && isset($sports_data["txns"]) && is_array($sports_data["txns"])) {
                foreach ($sports_data["txns"] as $txn) {
                    if (isset($txn["status"]) && in_array(strtolower($txn["status"]), ["won", "lost", "draw", "tie", "settled"])) {
                        $incoming_action = "settle";
                        break;
                    }
                }
            }

            // If the action is specifically 'Bet' or 'ConfirmBet', it is NOT a settlement
            $is_bet_action = in_array($incoming_action, ["bet", "confirmbet"]);
            $is_settle_action = in_array($incoming_action, ["settle", "settlement", "result", "credit"]);

            $is_settlement = ($is_settle_action || ($win_amount > 0 && !$is_bet_action) || ($bet_amount == 0 && $win_amount == 0 && !$is_bet_action));

            // 3. IDEMPOTENT BALANCE CALCULATION
            // We only deduct if it's a NEW bet amount. We only credit if it's NEW win amount.
            $previous_cost = $merged_record ? floatval($merged_record["tbl_match_cost"]) : 0;
            $previous_profit = $merged_record ? floatval($merged_record["tbl_match_profit"]) : 0;

            $net_bet_change = $bet_amount; // Default for new records
            if ($merged_record) {
                // If it's a settlement, we usually don't want to deduct more unless specifically asked
                if ($is_settlement) {
                    $net_bet_change = 0; // Assume bet already handled
                } else {
                    $net_bet_change = max(0, $bet_amount - $previous_cost);
                }
            }

            $net_win_change = $win_amount; // Most providers send TOTAL win in settle call
            if ($merged_record && $previous_profit > 0) {
                $net_win_change = max(0, $win_amount - $previous_profit);
            }

            // --- SPORTS: MAX ODDS LIMIT ---
            $is_saba_sport = (stripos($const_game_name, "SABA") !== false);
            $is_luck_sport = (stripos($const_game_name, "Luck") !== false || $const_game_uid == "92b24e4c25107367a80e0fe1a97c24e4");

            if ($is_sports || $is_saba_sport || $is_luck_sport) {
                $saba_action = strtolower($data["action"] ?? $sports_data["action"] ?? "");
                $is_placement = in_array($saba_action, ["bet", "confirmbet", "placebet", "place_exchange_order", "place_order"]);

                // Odds Extraction Strategy
                $check_odds = $odds;
                if (empty($check_odds)) {
                    $check_odds = $sports_data["betslip"]["k"] ?? $sports_data["k"] ?? $sports_data["txns"][0]["odds"] ?? "";
                }

                if ($is_placement && !empty($check_odds) && floatval($check_odds) > $SABA_MAX_ODDS) {
                    // Trigger Frontend Notification
                    $n_title = "Bet Rejected";
                    $n_msg = "Maximum odds limit for Sports is {$SABA_MAX_ODDS}x. Your bet with odds {$check_odds}x was rejected. (Ref: " . date("His") . ")";
                    $n_time = date("d-m-Y h:i:s a");
                    $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                    $e_title = mysqli_real_escape_string($conn, $n_title);
                    $e_msg = mysqli_real_escape_string($conn, $n_msg);
                    $e_time = mysqli_real_escape_string($conn, $n_time);
                    
                    mysqli_query($conn, "INSERT INTO tblallnotices (tbl_user_id, tbl_notice_title, tbl_notice_note, tbl_notice_status, tbl_time_stamp) VALUES ('$e_uid', '$e_title', '$e_msg', 'true', '$e_time')");

                    // IMPORTANT: We MUST return the current balance in the payload, otherwise SABA shows 0 balance
                    $payloadData = json_encode(["credit_amount" => $total_avail_bal, "timestamp" => round(microtime(true) * 1000)]);
                    $payload = encrypt($payloadData, $AES_KEY);

                    // Return code 6 (Generic Error) instead of code 1 to avoid "Insufficient balance" message
                    echo json_encode([
                        "code" => 6,
                        "msg" => "Max odds limit is {$SABA_MAX_ODDS}",
                        "payload" => $payload
                    ]);
                    exit;
                }
            }
            // --- END SPORTS LIMIT ---

            // Check balance against net change
            if ($net_bet_change > 0 && $total_avail_bal < $net_bet_change) {
                echo json_encode(["code" => 1, "msg" => "Insufficient balance"]);
                exit;
            }

            // Balance Adjustment
            $rem_bet = $net_bet_change;
            if ($bonus_bal > 0 && $rem_bet > 0) {
                $ded = min($bonus_bal, $rem_bet);
                $bonus_bal -= $ded;
                $rem_bet -= $ded;
            }
            if ($sports_bonus > 0 && $rem_bet > 0) {
                $ded = min($sports_bonus, $rem_bet);
                $sports_bonus -= $ded;
                $rem_bet -= $ded;
            }

            $real_bal = $real_bal - $rem_bet + $net_win_change;
            if ($real_bal < 0)
                $real_bal = 0;

            $wagering = max(0, $wagering + $net_win_change - $net_bet_change);
            $bonus_comp = false;
            if (floatval($u_row["tbl_requiredplay_balance"]) > 0 && $wagering <= 0) {
                $bonus_comp = true;
                $real_bal += ($bonus_bal + $sports_bonus);
                $bonus_bal = 0;
                $sports_bonus = 0;
            }

            $credit_amount = $real_bal + $bonus_bal + $sports_bonus; // For response

            if ($bonus_comp) {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=0, tbl_sports_bonus=0, tbl_requiredplay_balance=0, tbl_active_bonus_id=0, tbl_is_bonus_locked=0 WHERE tbl_uniq_id=?");
                $stmt->bind_param("ds", $real_bal, $const_user_id);
            } else {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=?, tbl_sports_bonus=?, tbl_requiredplay_balance=? WHERE tbl_uniq_id=?");
                $stmt->bind_param("dddds", $real_bal, $bonus_bal, $sports_bonus, $wagering, $const_user_id);
            }
            $stmt->execute();
            $stmt->close();

            // MATCH RECORDING - FIX: Get readable game names from the lookup table
            $m_time = date("d-m-Y h:i a");
            $real_name_res = mysqli_query($conn, "SELECT tbl_game_name FROM tbl_game_names WHERE tbl_game_id = '$const_game_uid' LIMIT 1");
            if ($name_row = mysqli_fetch_assoc($real_name_res)) {
                $const_game_name = $name_row['tbl_game_name'];
            }

            if ($const_game_name == "") {
                $const_game_name = "Game " . strtoupper($const_game_uid);
            }

            $is_live_provider = (isset($data["provider"]) && (stripos($data["provider"], "Ezugi") !== false || stripos($data["provider"], "Evolution") !== false));

            $is_delayed = $is_live_provider ||
                (strpos(strtolower($const_game_name), "lobby") !== false) ||
                (strpos(strtolower($const_game_name), "roulette") !== false) ||
                (strpos(strtolower($const_game_name), "monopoly") !== false) ||
                (strpos(strtolower($const_game_name), "crazy time") !== false) ||
                (strpos(strtolower($const_game_name), "mega ball") !== false) ||
                (strpos(strtolower($const_game_name), "dream catcher") !== false) ||
                (strpos(strtolower($const_game_name), "game ") === 0) || // Catch "Game [UID]"
                !empty($match_details);

            $merged = false;
            if ($merged_record) {
                $rid = intval($merged_record["id"]);
                $existing_profit = floatval($merged_record["tbl_match_profit"]);
                $new_profit = max($existing_profit, $win_amount);
                $new_cost = $previous_cost + $net_bet_change;

                if ($new_profit > $new_cost) {
                    $new_status = "profit";
                    $new_result = "won";
                } else if ($new_profit == $new_cost && $new_cost > 0) {
                    $new_status = "tie";
                    $new_result = "tie";
                } else if (($is_sports || $is_delayed) && $new_profit > 0 && $new_profit < $new_cost) {
                    $new_status = "cashout";
                    $new_result = "won";
                } else if ($is_settlement) {
                    $new_status = "loss";
                    $new_result = "lost";
                } else {
                    $new_status = $merged_record["tbl_match_status"];
                    $new_result = $merged_record["tbl_match_result"];
                }

                $e_bal = floatval($real_bal);
                $upd_sql = "UPDATE tblmatchplayed SET tbl_match_cost = $new_cost, tbl_match_invested = $new_cost, tbl_match_profit = $new_profit, tbl_last_acbalance = $e_bal, tbl_match_status = '$new_status', tbl_match_result = '$new_result' WHERE id = $rid";
                mysqli_query($conn, $upd_sql);
                $merged = true;

                // DIAGNOSTIC LOGGING
                $log_upd = date("Y-m-d H:i:s") . " | UPD | Match Found ID: $rid | New Status: $new_status | Profit: $new_profit | Cost: $new_cost\n";
                file_put_contents("debug_log.txt", $log_upd, FILE_APPEND);
            }

            if (!$merged) {
                // INSERT NEW ROW if not found
                if ($win_amount > 0) {
                    if (($is_sports || $is_delayed) && $win_amount < $bet_amount) {
                        $m_status = "cashout";
                    } else {
                        $m_status = "profit";
                    }
                } else {
                    $m_status = (($is_sports || $is_delayed) && $bet_amount > 0) ? "wait" : "loss";
                }

                $m_result = ($m_status == "profit" || $m_status == "cashout") ? "won" : (($m_status == "wait") ? "pending" : "lost");

                $istmt = $conn->prepare("INSERT IGNORE INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_match_invested, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $istmt->bind_param("ssssdddssssssss", $const_user_id, $m_order_id, $const_game_uid, $bet_amount, $bet_amount, $bet_amount, $win_amount, $m_result, $real_bal, $m_status, $const_game_name, $match_details, $bet_type, $odds, $m_time);
                if ($istmt->execute()) {
                    $log_ins = date("Y-m-d H:i:s") . " | INS | New Record Created | Status: $m_status | ID: $m_order_id\n";
                } else {
                    $log_ins = date("Y-m-d H:i:s") . " | ERR | Insert Failed: " . $istmt->error . "\n";
                }
                file_put_contents("debug_log.txt", $log_ins, FILE_APPEND);
                $istmt->close();
            }

            $payloadData = json_encode(["credit_amount" => $credit_amount, "timestamp" => round(microtime(true) * 1000)]);
            $payload = encrypt($payloadData, $AES_KEY);
            echo json_encode(["code" => 0, "msg" => "", "payload" => $payload]);
            exit;
        }
    }
}
mysqli_close($conn);
?>