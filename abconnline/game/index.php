<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

define("ACCESS_SECURITY", "true");

include "../security/config.php";
include "../security/constants.php";
date_default_timezone_set("Asia/Kolkata");

// Ensure extra columns exist on the live environment
mysqli_query($conn, "ALTER TABLE tblmatchplayed ADD COLUMN IF NOT EXISTS tbl_provider VARCHAR(255) DEFAULT 'Standard' AFTER tbl_project_name");
mysqli_query($conn, "ALTER TABLE tblmatchplayed ADD COLUMN IF NOT EXISTS tbl_selection VARCHAR(255) DEFAULT '' AFTER tbl_bet_type");

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
    $is_cashout_req = false;
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

    // Static Provider mappings
    $const_provider = $data["provider"] ?? $data["provider_name"] ?? ($incoming_agency == "f1c978d202831562722aab59824e3cc5" ? "HC4D" : "Winco");

    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            $match_details = $sports_data["leagueName_en"] ?? $sports_data["sportTypeName_en"] ?? "";
            $bet_type = $sports_data["betChoice_en"] ?? $sports_data["marketName_en"] ?? "";
            $odds = $sports_data["odds"] ?? "";
            // Check if it's actually SABA (SABA usually has marketName_en or leagueName_en)
            if (isset($sports_data["marketName_en"]) || isset($sports_data["leagueName_en"])) {
                $const_game_name = "SABA Sports";
                $is_sports = true;
                $const_provider = "SABA";
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

    $const_selection = $data["selection"] ?? $data["choice"] ?? $data["bet_data"]["choice"] ?? $data["bet_data"]["selection"] ?? "";
    if ($const_selection == "" && $bet_type != "") {
        $const_selection = $bet_type . ($odds != "" ? " @ " . $odds : "");
    }

    // Account ID extraction logic
    $account_raw = explode('_', $data["member_account"])[0];
    if (preg_match('/([0-9]{7,})$/', $account_raw, $matches)) {
        $const_user_id = $matches[1];
    } else {
        $const_user_id = preg_replace('/^[a-zA-Z]+/', '', $account_raw);
    }

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
            if ($incoming_action == "" && isset($sports_data["txns"]) && is_array($sports_data["txns"])) {
                foreach ($sports_data["txns"] as $txn) {
                    if (isset($txn["status"]) && in_array(strtolower($txn["status"]), ["won", "lost", "draw", "tie", "settled"])) {
                        $incoming_action = "settle";
                        break;
                    }
                }
            }

            $is_settlement = ($incoming_action == "settle" || $incoming_action == "settlement" || $win_amount > 0 || (isset($data["bet_amount"]) && floatval($data["bet_amount"]) == 0 && $win_amount == 0));

            // 3. IDEMPOTENT BALANCE CALCULATION
            $previous_cost = $merged_record ? floatval($merged_record["tbl_match_cost"]) : 0;
            $previous_profit = $merged_record ? floatval($merged_record["tbl_match_profit"]) : 0;

            $net_bet_change = $bet_amount;
            if ($merged_record) {
                if ($is_settlement) {
                    $net_bet_change = 0;
                } else {
                    $net_bet_change = max(0, $bet_amount - $previous_cost);
                }
            }

            $net_win_change = $win_amount;
            if ($merged_record && $previous_profit > 0) {
                $net_win_change = max(0, $win_amount - $previous_profit);
            }

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

            $credit_amount = $real_bal + $bonus_bal + $sports_bonus;

            if ($bonus_comp) {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=0, tbl_sports_bonus=0, tbl_requiredplay_balance=0, tbl_active_bonus_id=0, tbl_is_bonus_locked=0 WHERE tbl_uniq_id=?");
                $stmt->bind_param("ds", $real_bal, $const_user_id);
            } else {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=?, tbl_sports_bonus=?, tbl_requiredplay_balance=? WHERE tbl_uniq_id=?");
                $stmt->bind_param("dddds", $real_bal, $bonus_bal, $sports_bonus, $wagering, $const_user_id);
            }
            $stmt->execute();
            $stmt->close();

            // MATCH RECORDING
            $m_time = date("d-m-Y h:i a");
            if ($const_game_name == "") {
                $const_game_name = "Game " . strtoupper($const_game_uid);
            }

            $is_live_provider = (isset($data["provider"]) && (stripos($data["provider"], "Ezugi") !== false || stripos($data["provider"], "Evolution") !== false));

            $is_delayed = $is_sports || $is_live_provider ||
                (strpos(strtolower($const_game_name), "lobby") !== false) ||
                (strpos(strtolower($const_game_name), "roulette") !== false) ||
                (strpos(strtolower($const_game_name), "monopoly") !== false) ||
                (strpos(strtolower($const_game_name), "crazy time") !== false) ||
                (strpos(strtolower($const_game_name), "game ") === 0) ||
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
                $upd_sql = "UPDATE tblmatchplayed SET tbl_match_cost = $new_cost, tbl_match_invested = $new_cost, tbl_match_profit = $new_profit, tbl_last_acbalance = $e_bal, tbl_match_status = '$new_status', tbl_match_result = '$new_result', tbl_selection = '$const_selection' WHERE id = $rid";
                mysqli_query($conn, $upd_sql);
                $merged = true;
                file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " | UPD | ID: $rid | Status: $new_status\n", FILE_APPEND);
            }

            if (!$merged) {
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

                $istmt = $conn->prepare("INSERT IGNORE INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_match_invested, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_provider, tbl_match_details, tbl_bet_type, tbl_selection, tbl_odds, tbl_time_stamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $istmt->bind_param("ssssdddssssssssss", $const_user_id, $m_order_id, $const_game_uid, $bet_amount, $bet_amount, $bet_amount, $win_amount, $m_result, $real_bal, $m_status, $const_game_name, $const_provider, $match_details, $bet_type, $const_selection, $odds, $m_time);
                $istmt->execute();
                $istmt->close();
                file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " | INS | ID: $m_order_id | Status: $m_status\n", FILE_APPEND);
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