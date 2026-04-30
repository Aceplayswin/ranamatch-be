<?php
error_reporting(E_ALL & ~E_NOTICE); 
header('Content-Type: application/json');

define("ACCESS_SECURITY", "true");

include "../../security/config.php";
include "../../security/constants.php";
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

    // Dynamic Agency Overrides
    $incoming_agency = $json_data["agency_uid"] ?? "";
    if ($incoming_agency == "f1c978d202831562722aab59824e3cc5") {
        $AES_KEY = "ee4c17cb3d1eedd3c751ae3a232aa92a";
        $PREFIX = "hc4d11";
    }

    $payload = $json_data["payload"];
    $data = null;
    if ($payload) {
        $decrypted = decrypt($payload, $AES_KEY);
        $data = json_decode($decrypted, true);
    }

    // Diagnostic logging
    $log_path = __DIR__ . "/bet_logs.txt";
    $log_entry = date('Y-m-d H:i:s') . " - Req: " . $json . " - Decoded: " . json_encode($data) . "\n";
    file_put_contents($log_path, $log_entry, FILE_APPEND);

    if (!$data) {
        file_put_contents($log_path, "ERROR: Failed to decode data\n", FILE_APPEND);
        exit;
    }

    $const_game_uid = $data["game_uid"] ?? "N/A";
    $win_amount = floatval($data["win_amount"] ?? 0);
    $bet_amount = floatval($data["bet_amount"] ?? 0);
    $m_order_id = $data["game_round"] ?? $data["serial_number"] ?? ("API" . bin2hex(random_bytes(6)));

    // Standardized Parsing
    $match_details = "";
    $bet_type = "";
    $odds = "";
    $const_game_name = $data["game_name"] ?? $data["gameName"] ?? $data["mGameName"] ?? $data["title"] ?? "";
    $is_sports = false;
    $is_cashout_req = false;
    $sports_data = [];

    // 1. Dynamic Auto-Sync
    if ($const_game_uid != "N/A" && $const_game_uid != "") {
        if ($const_game_name != "") {
            $sync_stmt = $conn->prepare("INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE tbl_game_name = VALUES(tbl_game_name)");
            $sync_stmt->bind_param("ss", $const_game_uid, $const_game_name);
            $sync_stmt->execute();
            $sync_stmt->close();
        }
    }

    // 2. Database Lookup
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

    // 3. Extraction Logic
    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            $s_home = $sports_data["homeName_en"] ?? $sports_data["homeName"] ?? "";
            $s_away = $sports_data["awayName_en"] ?? $sports_data["awayName"] ?? "";
            $s_league = $sports_data["leagueName_en"] ?? $sports_data["leagueName"] ?? "";
            $s_sport = $sports_data["sportTypeName_en"] ?? $sports_data["sportTypeName"] ?? "";

            if (!empty($s_home) && !empty($s_away)) {
                $match_details = (!empty($s_league)) ? "$s_league - $s_home vs $s_away" : "$s_home vs $s_away";
            } else {
                $match_details = $s_league ?: $s_sport ?: "";
            }

            $s_market = $sports_data["betTypeName_en"] ?? $sports_data["marketName_en"] ?? "";
            $s_choice = $sports_data["betChoice_en"] ?? $sports_data["betChoice"] ?? "";
            $bet_type = (!empty($s_market) && !empty($s_choice)) ? "$s_market - $s_choice" : ($s_choice ?: $s_market ?: "");
            $odds = $sports_data["odds"] ?? "";

            // SABA Check
            if (isset($sports_data["marketName_en"]) || isset($sports_data["leagueName_en"]) || (isset($data["provider"]) && stripos($data["provider"], "SABA") !== false)) {
                $const_game_name = "SABA Sports";
                $is_sports = true;
                $action = strtolower($sports_data["action"] ?? "");
                if (($action == "bet" || $action == "confirmbet") && $bet_amount == 0 && isset($sports_data["txns"][0])) {
                    $bet_amount = floatval($sports_data["txns"][0]["actualAmount"] ?? $sports_data["txns"][0]["debitAmount"] ?? 0);
                }
            }
        }
    }

    // Robust User ID Extraction (Optimized for SABA/Luck Sports)
    $raw_member = $data["member_account"] ?? "";
    $const_user_id = "";
    
    // Primary: Database lookup by account name/mobile
    $e_raw = mysqli_real_escape_string($conn, $raw_member);
    $u_lookup = mysqli_query($conn, "SELECT tbl_uniq_id FROM tblusersdata WHERE tbl_user_name='$e_raw' OR tbl_mobile_num='$e_raw' OR tbl_uniq_id='$e_raw' LIMIT 1");
    if ($u_row_lk = mysqli_fetch_assoc($u_lookup)) {
        $const_user_id = $u_row_lk['tbl_uniq_id'];
    } else {
        // Fallback: Prefix removal (Legacy)
        $account_clean = explode('_', $raw_member)[0];
        // Strip common prefixes like hd86cd or winco
        $const_user_id = str_replace($PREFIX, "", $account_clean);
        // If it's still not found, try to find a user where the uniq_id is a substring
        if (strlen($const_user_id) > 10) { // If it's a long account name
             $u_lookup2 = mysqli_query($conn, "SELECT tbl_uniq_id FROM tblusersdata WHERE '$e_raw' LIKE CONCAT('%', tbl_uniq_id, '%') LIMIT 1");
             if ($row2 = mysqli_fetch_assoc($u_lookup2)) {
                 $const_user_id = $row2['tbl_uniq_id'];
             }
        }
    }
    
    if (empty($const_user_id)) {
        $const_user_id = "N/A";
    }
    
    if ($const_user_id == "N/A") {
        file_put_contents(__DIR__ . "/bet_logs.txt", "DEBUG: User NOT Found for account: '$raw_member'\n", FILE_APPEND);
    }

    $u_res = mysqli_query($conn, "SELECT * FROM tblusersdata WHERE tbl_uniq_id='$const_user_id' FOR UPDATE");
    if ($u_row = mysqli_fetch_assoc($u_res)) {
        
        $real_bal = floatval($u_row["tbl_balance"]);
        
        // Robust Sports Data Extraction
        $sports_data = $data["data"] ?? [];
        if (is_string($sports_data)) {
            $sports_data = json_decode($sports_data, true) ?? [];
        }

        // --- SPORTS: MAX ODDS LIMIT (SABA & LUCK SPORTS) ---
        $is_sports_engine = ($const_game_uid == "92b24e4c25107367a80e0fe1a97c24e4") || // Luck Sports
                            ($const_game_uid == "08ced9dd788aed11ff3c7f387ae0f063") || // SABA Sports
                            (stripos($const_game_name, "Luck") !== false) || 
                            (stripos($const_game_name, "SABA") !== false);

        if ($is_sports_engine) {
            // For Luck Sports, we treat ANY request with a bet_amount as a placement attempt
            $saba_action = strtolower($data["action"] ?? $sports_data["action"] ?? $sports_data["transaction"]["operation"] ?? "");
            $is_placement = in_array($saba_action, ["bet", "confirmbet", "placebet", "place_exchange_order", "place_order", "place-bet", "place"]) || ($bet_amount > 0);
            
            // Multi-Level Odds Extraction (Prioritize 'k' for Luck)
            $check_odds = 0;
            if (isset($sports_data["betslip"]["k"])) {
                $check_odds = floatval($sports_data["betslip"]["k"]);
            } elseif (isset($sports_data["odds"])) {
                $check_odds = floatval($sports_data["odds"]);
            } elseif (isset($sports_data["txns"][0]["odds"])) {
                $check_odds = floatval($sports_data["txns"][0]["odds"]);
            } else {
                // Regex fallback
                $raw_json = json_encode($data);
                if (preg_match('/"(odds|rate|k)"\s*:\s*"?([\d\.-]+)"?/', $raw_json, $matches)) {
                    $check_odds = floatval($matches[2]);
                }
            }
            
            // Fail-safe limit loading
            $limit_val = floatval($SABA_MAX_ODDS > 0 ? $SABA_MAX_ODDS : 4.0);

            // Robust Odds Extraction (SABA Specific)
            $check_odds = 0;
            if (isset($sports_data["odds"])) {
                $check_odds = floatval($sports_data["odds"]);
            } else if (isset($sports_data["txns"][0]["odds"])) {
                $check_odds = floatval($sports_data["txns"][0]["odds"]);
            } else {
                // Regex fallback only for SABA requests to be extra safe
                $raw_json = json_encode($data);
                if (preg_match('/"(odds|rate)"\s*:\s*"?([\d\.-]+)"?/', $raw_json, $matches)) {
                    $check_odds = floatval($matches[2]);
                }
            }

            if ($is_placement && $check_odds > $limit_val) {
                // Trigger Frontend Notification via tblmatchplayed (Fastest & Most Reliable)
                $m_time = date("d-m-Y h:i a");
                $rejected_status = "rejected";
                $rejected_result = "lost";
                $rejection_reason = "Maximum odds limit for Sports is {$limit_val}x. Your bet with odds {$check_odds}x was rejected.";
                
                // Fetch latest balance for the record
                $b_res = mysqli_query($conn, "SELECT tbl_balance, tbl_bonus_balance, tbl_sports_bonus FROM tblusersdata WHERE tbl_uniq_id='$const_user_id' LIMIT 1");
                $b_row = mysqli_fetch_assoc($b_res);
                $cur_bal = floatval($b_row["tbl_balance"] ?? 0) + floatval($b_row["tbl_bonus_balance"] ?? 0) + floatval($b_row["tbl_sports_bonus"] ?? 0);

                $istmt = $conn->prepare("INSERT INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_match_invested, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp, tbl_result_time, tbl_notified, tbl_notify_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                $istmt->bind_param("ssssdddsssssssss", $const_user_id, $m_order_id, $const_game_uid, $const_game_name, $bet_amount, $bet_amount, $win_amount, $rejected_result, $cur_bal, $rejected_status, $const_game_name, $rejection_reason, $bet_type, $check_odds, $m_time, $m_time);
                $istmt->execute();
                $istmt->close();

                // Still insert into tblallnotices for backup/global visibility
                $n_title = "Bet Rejected";
                $n_msg = "Maximum odds limit for Sports is {$limit_val}x. Your bet with odds {$check_odds}x was rejected. (Ref: {$m_order_id})";
                $n_time = date("d-m-Y h:i:s a");
                $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                $e_title = mysqli_real_escape_string($conn, $n_title);
                $e_msg = mysqli_real_escape_string($conn, $n_msg);
                $e_time = mysqli_real_escape_string($conn, $n_time);
                mysqli_query($conn, "INSERT INTO tblallnotices (tbl_user_id, tbl_notice_title, tbl_notice_note, tbl_notice_status, tbl_time_stamp) VALUES ('$e_uid', '$e_title', '$e_msg', 'true', '$e_time')");

                $payload = encrypt(json_encode([
                    "credit_amount" => $cur_bal, 
                    "timestamp" => round(microtime(true) * 1000)
                ]), $AES_KEY);
                
                echo json_encode([
                    "code" => 1, // Generic failure to prevent "Insufficient Funds" popup
                    "msg" => "Max odds limit is {$limit_val}", 
                    "payload" => $payload
                ]);
                exit;
            }
        }

        $bonus_bal = floatval($u_row["tbl_bonus_balance"]);
        $sports_bonus = floatval($u_row["tbl_sports_bonus"]);
        $wagering = floatval($u_row["tbl_requiredplay_balance"]);
        $total_avail_bal = $real_bal + $bonus_bal + $sports_bonus;

        $incoming_action = strtolower($data["action"] ?? $sports_data["action"] ?? "");
        $is_bet_action = in_array($incoming_action, ["bet", "confirmbet"]);
        $is_settlement = (!$is_bet_action && ($win_amount > 0 || $bet_amount == 0));

        $merged_record = null;
        $search_ids = array_unique([$m_order_id, $data["game_round"] ?? "", $data["serial_number"] ?? ""]);
        foreach ($search_ids as $sid) {
            if (empty($sid)) continue;
            $chk_res = mysqli_query($conn, "SELECT * FROM tblmatchplayed WHERE tbl_user_id='$const_user_id' AND tbl_uniq_id='".mysqli_real_escape_string($conn, $sid)."' ORDER BY id DESC LIMIT 1");
            if ($chk_record = mysqli_fetch_assoc($chk_res)) {
                $merged_record = $chk_record;
                break;
            }
        }

        $net_bet_change = ($merged_record && $is_settlement) ? 0 : max(0, $bet_amount - ($merged_record ? floatval($merged_record["tbl_match_cost"]) : 0));
        $net_win_change = $win_amount - ($merged_record ? floatval($merged_record["tbl_match_profit"]) : 0);
        $net_win_change = max(0, $net_win_change);

        if ($net_bet_change > 0 && $total_avail_bal < $net_bet_change) {
            echo json_encode(["code" => 1, "msg" => "Insufficient balance"]);
            exit;
        }

        $rem_bet = $net_bet_change;
        if ($real_bal > 0 && $rem_bet > 0) { $ded = min($real_bal, $rem_bet); $real_bal -= $ded; $rem_bet -= $ded; }
        if ($sports_bonus > 0 && $rem_bet > 0) { $ded = min($sports_bonus, $rem_bet); $sports_bonus -= $ded; $rem_bet -= $ded; }
        if ($bonus_bal > 0 && $rem_bet > 0) { $ded = min($bonus_bal, $rem_bet); $bonus_bal -= $ded; $rem_bet -= $ded; }
        $real_bal += $net_win_change;

        $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=?, tbl_sports_bonus=?, tbl_requiredplay_balance=? WHERE tbl_uniq_id=?");
        $stmt->bind_param("dddds", $real_bal, $bonus_bal, $sports_bonus, $wagering, $const_user_id);
        $stmt->execute();
        $stmt->close();

        $m_time = date("d-m-Y h:i a");
        if ($merged_record) {
            $rid = $merged_record['id'];
            mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_match_cost=tbl_match_cost+$net_bet_change, tbl_match_profit=$win_amount, tbl_match_status='".($win_amount>0?'profit':'loss')."', tbl_result_time='$m_time' WHERE id=$rid");
        } else {
            $m_status = $is_sports ? "wait" : ($win_amount > 0 ? "profit" : "loss");
            $istmt = $conn->prepare("INSERT INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_match_profit, tbl_match_status, tbl_time_stamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $istmt->bind_param("ssssddss", $const_user_id, $m_order_id, $const_game_uid, $const_game_name, $bet_amount, $win_amount, $m_status, $m_time);
            $istmt->execute();
            $istmt->close();
        }

        $payload = encrypt(json_encode(["credit_amount" => $real_bal, "timestamp" => round(microtime(true) * 1000)]), $AES_KEY);
        echo json_encode(["code" => 0, "msg" => "", "payload" => $payload]);
        exit;

    } else {
        file_put_contents($log_path, "DEBUG: User NOT Found: '$const_user_id'\n", FILE_APPEND);
        echo json_encode(["code" => 4, "msg" => "User not found"]);
        exit;
    }
}
mysqli_close($conn);
?>