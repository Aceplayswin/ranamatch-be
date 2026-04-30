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
    file_put_contents(__DIR__ . "/bet_logs.txt", $log_data, FILE_APPEND);

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
    if ($const_game_uid != "N/A" && $const_game_uid != "") {
        // Log that we received a request for discovery debugging
        file_put_contents(__DIR__ . "/discovery_debug.txt", date('H:i:s') . " - Processing UID: " . $const_game_uid . " Name: " . $const_game_name . "\n", FILE_APPEND);

        if ($const_game_name != "") {
            $sync_stmt = $conn->prepare("INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE tbl_game_name = VALUES(tbl_game_name)");
            $sync_stmt->bind_param("ss", $const_game_uid, $const_game_name);
            $sync_stmt->execute();
            $sync_stmt->close();
        }

        // --- Lobby Game Discovery Logger ---
        $discovery_file = __DIR__ . "/discovered_lobby_games.json";
        $current_discoveries = [];
        if (file_exists($discovery_file)) {
            $current_discoveries = json_decode(file_get_contents($discovery_file), true) ?: [];
        }

        $found = false;
        foreach ($current_discoveries as $idx => $game) {
            if ($game['uid'] === $const_game_uid) {
                // If we already have the UID but now we found a name, update it
                if (empty($game['name']) && !empty($const_game_name)) {
                    $current_discoveries[$idx]['name'] = $const_game_name;
                    file_put_contents($discovery_file, json_encode($current_discoveries, JSON_PRETTY_PRINT));
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            $current_discoveries[] = [
                'uid' => $const_game_uid,
                'name' => $const_game_name ?: "Unknown Name (Play again)",
                'discovered_at' => date('Y-m-d H:i:s'),
                'raw_data' => $data
            ];
            file_put_contents($discovery_file, json_encode($current_discoveries, JSON_PRETTY_PRINT));
        }
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
        "1c47c7cc3fd4ffc3d31dc095bc5dddc8" => "Dragon Hatch",
        "052442de87321dd97bb91b1a35fb8e65" => "Baccarat Deluxe",
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
        "7a3624d284306e9c6fe1b3e9c8a87aae" => "Golden Temple",
        "92b24e4c25107367a80e0fe1a97c24e4" => "Luck Sports",
        "08ced9dd788aed11ff3c7f387ae0f063" => "SABA Sports",
        "4ee8e0051a035b463b47c3c473ce317d" => "Esports",
        "48341a3bf62b6dd0814d7129e7e0834b" => "9 Wickets"

    ];

    if ($const_game_name == "" && isset($game_mappings[$const_game_uid])) {
        $const_game_name = $game_mappings[$const_game_uid];
    }

    // Explicit Sports Provider Detection by Game UID
    $sports_game_uids = [
        "92b24e4c25107367a80e0fe1a97c24e4", // Luck Sports
        "08ced9dd788aed11ff3c7f387ae0f063", // SABA Sports
        "4ee8e0051a035b463b47c3c473ce317d", // Esports
        "48341a3bf62b6dd0814d7129e7e0834b", // 9 Wickets
    ];
    if (in_array($const_game_uid, $sports_game_uids)) {
        $is_sports = true;
    }

    // SABA/Sports Advanced Extraction
    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            // SABA-style extraction
            $s_home = $sports_data["homeName_en"] ?? $sports_data["homeName"] ?? "";
            $s_away = $sports_data["awayName_en"] ?? $sports_data["awayName"] ?? "";
            $s_league = $sports_data["leagueName_en"] ?? $sports_data["leagueName"] ?? "";
            $s_sport = $sports_data["sportTypeName_en"] ?? $sports_data["sportTypeName"] ?? "";

            if (!empty($s_home) && !empty($s_away)) {
                $match_details = (!empty($s_league)) ? "$s_league - $s_home vs $s_away" : "$s_home vs $s_away";
            } else {
                $match_details = $s_league ?: $s_sport ?: "";
            }
            if (!empty($s_sport) && stripos($match_details, $s_sport) === false) {
                $match_details .= " ($s_sport)";
            }

            $s_market = $sports_data["betTypeName_en"] ?? $sports_data["marketName_en"] ?? "";
            $s_choice = $sports_data["betChoice_en"] ?? $sports_data["betChoice"] ?? "";
            if (!empty($s_market) && !empty($s_choice) && strcasecmp($s_market, $s_choice) !== 0) {
                $bet_type = "$s_market - $s_choice";
            } else {
                $bet_type = $s_choice ?: $s_market ?: "";
            }

            $odds = $sports_data["odds"] ?? "";

            // Luck Sports betslip extraction
            if (empty($match_details) && isset($sports_data["betslip"]["bets"][0])) {
                $bet_info = $sports_data["betslip"]["bets"][0];

                // Build match details: "Tournament - Team1 vs Team2 (Sport)"
                $tournament = $bet_info["tournament_name"] ?? "";
                $sport = $bet_info["sport_name"] ?? "";
                $competitors = $bet_info["competitor_name"] ?? [];
                $teams = is_array($competitors) ? implode(" vs ", $competitors) : $competitors;

                if (!empty($tournament) && !empty($teams)) {
                    $match_details = $tournament . " - " . $teams;
                } else if (!empty($tournament)) {
                    $match_details = $tournament;
                } else if (!empty($teams)) {
                    $match_details = $teams;
                }
                if (!empty($sport)) {
                    $match_details .= " (" . $sport . ")";
                }

                // Selection: "Market - Outcome"
                $market = $bet_info["market_name"] ?? "";
                $outcome = $bet_info["outcome_name"] ?? "";
                if (!empty($market) && !empty($outcome)) {
                    $bet_type = $market . " - " . $outcome;
                } else if (!empty($outcome)) {
                    $bet_type = $outcome;
                } else if (!empty($market)) {
                    $bet_type = $market;
                }

                // Odds
                if (empty($odds)) {
                    $odds = $bet_info["odds"] ?? $sports_data["betslip"]["k"] ?? "";
                }
            }

            // Check if it's actually SABA 
            if (isset($sports_data["marketName_en"]) || isset($sports_data["leagueName_en"]) || (isset($data["provider"]) && stripos($data["provider"], "SABA") !== false)) {
                $const_game_name = "SABA Sports";
                $is_sports = true;
                $const_provider = "SABA";

                // SABA sends actual bet amount inside txns for 'ConfirmBet' or 'Bet' actions
                $action = strtolower($sports_data["action"] ?? "");
                // SABA sends actual bet amount inside txns for 'ConfirmBet' or 'Bet' actions
                $action = strtolower($sports_data["action"] ?? "");
                if (($action == "bet" || $action == "confirmbet") && $bet_amount == 0 && isset($sports_data["txns"][0])) {
                    $bet_amount = floatval($sports_data["txns"][0]["actualAmount"] ?? $sports_data["txns"][0]["debitAmount"] ?? 0);
                }
            }

            // DETECT CASHOUT from txns
            if (isset($sports_data["txns"]) && is_array($sports_data["txns"])) {
                foreach ($sports_data["txns"] as $txn) {
                    if (isset($txn["extraStatus"]) && stripos($txn["extraStatus"], "cashout") !== false) {
                        $is_cashout_req = true;
                        break;
                    }
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

    // Smart Fallback: For sports providers that don't send structured data (like Luck Sports),
    // scan the entire decoded payload for any usable match/event information
    if ($is_sports && empty($match_details)) {
        // Log the full payload for debugging so we can verify what the provider sends
        $sports_debug = date('Y-m-d H:i:s') . " | SPORTS-PAYLOAD | Game: $const_game_name | UID: $const_game_uid | Full Data: " . json_encode($data) . "\n";
        file_put_contents(__DIR__ . "/sports_debug.log", $sports_debug, FILE_APPEND);

        // Deep-scan all string values in payload for potential details
        $detail_keys = [
            "leagueName_en",
            "leagueName",
            "league_name",
            "league",
            "matchName",
            "match_name",
            "match",
            "eventName",
            "event_name",
            "event",
            "sportName",
            "sport_name",
            "sport_type",
            "gameName",
            "game_name",
            "title",
            "description",
            "homeName",
            "awayName",
            "home_name",
            "away_name",
            "home_team",
            "away_team",
            "team1",
            "team2",
            "matchTitle",
            "eventTitle"
        ];
        $selection_keys = [
            "betChoice_en",
            "betChoice",
            "marketName_en",
            "marketName",
            "market_name",
            "market",
            "selectionName",
            "selection_name",
            "selection",
            "betName",
            "bet_name",
            "betType",
            "bet_type",
            "outcome",
            "pick",
            "choice"
        ];
        $odds_keys = ["odds", "price", "oddsValue", "odds_value", "rate", "decimal_odds"];

        // Scan all top-level and nested data
        $scan_sources = [$data];
        if (!empty($sports_data) && is_array($sports_data))
            $scan_sources[] = $sports_data;
        if (isset($data["bet_data"]) && is_array($data["bet_data"]))
            $scan_sources[] = $data["bet_data"];
        // Also check inside txns if present
        if (isset($sports_data["txns"][0]) && is_array($sports_data["txns"][0]))
            $scan_sources[] = $sports_data["txns"][0];

        foreach ($scan_sources as $source) {
            if (!is_array($source))
                continue;
            if (empty($match_details)) {
                foreach ($detail_keys as $k) {
                    if (isset($source[$k]) && is_string($source[$k]) && !empty($source[$k])) {
                        $match_details = $source[$k];
                        break;
                    }
                }
                // Team name fallback
                if (empty($match_details) && isset($source["homeName"]) && isset($source["awayName"])) {
                    $match_details = $source["homeName"] . " vs " . $source["awayName"];
                }
            }
            if (empty($bet_type)) {
                foreach ($selection_keys as $k) {
                    if (isset($source[$k]) && is_string($source[$k]) && !empty($source[$k])) {
                        $bet_type = $source[$k];
                        break;
                    }
                }
            }
            if (empty($odds)) {
                foreach ($odds_keys as $k) {
                    if (isset($source[$k]) && !empty($source[$k])) {
                        $odds = $source[$k];
                        break;
                    }
                }
            }
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

            // --- SPORTS: MAX ODDS LIMIT ---
            $is_saba = (stripos($const_game_name, "SABA") !== false || stripos($data["provider"] ?? "", "SABA") !== false);
            $is_luck = (stripos($const_game_name, "Luck") !== false || $const_game_uid == "92b24e4c25107367a80e0fe1a97c24e4");

            if ($is_sports || $is_saba || $is_luck) {
                $saba_action = strtolower($data["action"] ?? $sports_data["action"] ?? "");
                $is_placement = in_array($saba_action, ["bet", "confirmbet", "placebet", "place_exchange_order", "place_order"]);

                // Ensure odds are extracted (sometimes they are nested in txns or betslip)
                $check_odds = $odds;
                if (empty($check_odds)) {
                    $check_odds = $sports_data["betslip"]["k"] ?? $sports_data["k"] ?? $sports_data["txns"][0]["odds"] ?? "";
                }

                if ($is_placement && !empty($check_odds) && floatval($check_odds) > $SABA_MAX_ODDS) {
                    $reject_log = date('Y-m-d H:i:s') . " | SPORTS-LIMIT | REJECTED | Game: $const_game_name | Odds: $check_odds | User: $const_user_id\n";
                    file_put_contents(__DIR__ . "/bet_logs.txt", $reject_log, FILE_APPEND);
                    
                    // Trigger Frontend Notification
                    $n_title = "Bet Rejected";
                    $n_msg = "Maximum odds limit for Sports is {$SABA_MAX_ODDS}x. Your bet with odds {$check_odds}x was rejected. (Ref: " . date("His") . ")";
                    $n_time = date("d-m-Y h:i:s a");
                    $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                    $e_title = mysqli_real_escape_string($conn, $n_title);
                    $e_msg = mysqli_real_escape_string($conn, $n_msg);
                    $e_time = mysqli_real_escape_string($conn, $n_time);
                    
                    $not_res = mysqli_query($conn, "INSERT INTO tblallnotices (tbl_user_id, tbl_notice_title, tbl_notice_note, tbl_notice_status, tbl_time_stamp) VALUES ('$e_uid', '$e_title', '$e_msg', 'true', '$e_time')");
                    if (!$not_res) {
                        file_put_contents(__DIR__ . "/bet_logs.txt", "NOTICE-ERR | " . mysqli_error($conn) . "\n", FILE_APPEND);
                    }

                    // IMPORTANT: We MUST return the current balance in the payload, otherwise SABA shows 0 balance
                    $payloadData = json_encode(["credit_amount" => $real_bal, "timestamp" => round(microtime(true) * 1000)]);
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

            // 1. IMPROVED: Identify if this is a known transaction/round BEFORE updating balance
            $merged_record = null;
            $search_ids = array_unique([$m_order_id, $data["game_round"] ?? "", $data["serial_number"] ?? ""]);
            foreach ($search_ids as $sid) {
                if (empty($sid))
                    continue;
                $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                $e_sid = mysqli_real_escape_string($conn, $sid);
                $chk_res = mysqli_query($conn, "SELECT id, tbl_match_cost, tbl_match_profit, tbl_match_status, tbl_match_result, tbl_result_time FROM tblmatchplayed WHERE tbl_user_id='$e_uid' AND tbl_uniq_id='$e_sid' ORDER BY id DESC LIMIT 1");
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

            // Check balance against net change
            if ($net_bet_change > 0 && $total_avail_bal < $net_bet_change) {
                echo json_encode(["code" => 1, "msg" => "Insufficient balance"]);
                exit;
            }

            // Balance Adjustment: Deduct from Real Balance FIRST, then Secondary/Bonus
            $rem_bet = $net_bet_change;

            // 1. Deduct from Real Balance
            if ($real_bal > 0 && $rem_bet > 0) {
                $ded = min($real_bal, $rem_bet);
                $real_bal -= $ded;
                $rem_bet -= $ded;
            }

            // 2. Deduct from Sports Bonus (if any remains)
            if ($sports_bonus > 0 && $rem_bet > 0) {
                $ded = min($sports_bonus, $rem_bet);
                $sports_bonus -= $ded;
                $rem_bet -= $ded;
            }

            // 3. Deduct from Casino Bonus (if any still remains)
            if ($bonus_bal > 0 && $rem_bet > 0) {
                $ded = min($bonus_bal, $rem_bet);
                $bonus_bal -= $ded;
                $rem_bet -= $ded;
            }

            // Winnings logic: According to user request, winnings ALWAYS go to Real Account.
            // Bonus balance remains "locked" at its initial amount and does not increase from wins.
            $real_bal += $net_win_change;

            // --- CATEGORY SPECIFIC WAGERING ---
            // Only decrement wagering if the game category matches the bonus category
            $bonus_category = 'casino'; // Default
            $active_b_id = (int) ($u_row["tbl_active_bonus_id"] ?? 0);
            if ($active_b_id > 0) {
                // We fetch the category of the active bonus to ensure correct wagering logic
                $cat_res = mysqli_query($conn, "SELECT bonus_category FROM tbl_bonuses WHERE id = $active_b_id");
                if ($cat_row = mysqli_fetch_assoc($cat_res)) {
                    $bonus_category = strtolower($cat_row['bonus_category']);
                }
            }

            // Check if current game type matches the bonus focus
            $wagering_matches = ($bonus_category === 'sports' && $is_sports) || ($bonus_category !== 'sports' && !$is_sports);

            if ($wagering_matches) {
                $wagering = max(0, $wagering - $net_bet_change);
            }
            // ----------------------------------

            $bonus_comp = false;
            // Transition bonus to real money only when wagering hits 0
            if (floatval($u_row["tbl_requiredplay_balance"]) > 0 && $wagering <= 0) {
                $bonus_comp = true;
                $real_bal += ($bonus_bal + $sports_bonus);
                $bonus_bal = 0;
                $sports_bonus = 0;
            }

            $credit_amount = $real_bal; // Only show Real Balance in game according to user request

            if ($bonus_comp) {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=0, tbl_sports_bonus=0, tbl_requiredplay_balance=0, tbl_active_bonus_id=0, tbl_is_bonus_locked=0 WHERE tbl_uniq_id=?");
                $stmt->bind_param("ds", $real_bal, $const_user_id);
                $stmt->execute();
                $stmt->close();

                // Update redemption history to 'completed'
                $active_b_id = (int) $u_row["tbl_active_bonus_id"];
                $upt_red = $conn->prepare("UPDATE tbl_bonus_redemptions SET wagering_completed = wagering_required, status = 'completed', updated_at = NOW() WHERE user_id = ? AND bonus_id = ? AND status = 'active'");
                $upt_red->bind_param("si", $const_user_id, $active_b_id);
                $upt_red->execute();
                $upt_red->close();
            } else {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=?, tbl_sports_bonus=?, tbl_requiredplay_balance=? WHERE tbl_uniq_id=?");
                $stmt->bind_param("dddds", $real_bal, $bonus_bal, $sports_bonus, $wagering, $const_user_id);
                $stmt->execute();
                $stmt->close();

                // Sync incremental progress to redemption table
                $active_b_id = (int) $u_row["tbl_active_bonus_id"];
                if ($active_b_id > 0) {
                    $upt_red = $conn->prepare("UPDATE tbl_bonus_redemptions SET wagering_completed = wagering_required - ?, updated_at = NOW() WHERE user_id = ? AND bonus_id = ? AND status = 'active'");
                    $upt_red->bind_param("dsi", $wagering, $const_user_id, $active_b_id);
                    $upt_red->execute();
                    $upt_red->close();
                }
            }

            // MATCH RECORDING - FIX: Get readable game names from the lookup table
            $m_time = date("d-m-Y h:i a");
            $real_name_res = mysqli_query($conn, "SELECT tbl_game_name FROM tbl_game_names WHERE tbl_game_id = '$const_game_uid' LIMIT 1");
            if ($name_row = mysqli_fetch_assoc($real_name_res)) {
                $const_game_name = $name_row['tbl_game_name'];
            }

            if ($const_game_name == "") {
                if (!empty($const_provider) && $const_provider != "Standard" && $const_provider != "N/A") {
                    $const_game_name = $const_provider . " Live Casino";
                } else {
                    $const_game_name = "Casino Game"; // Elegant fallback instead of showing ugly API UIDs
                }
            }

            $raw_packet = json_encode($data);
            $is_live_marker = (
                stripos($raw_packet, "Ezugi") !== false ||
                stripos($raw_packet, "Evolution") !== false ||
                stripos($raw_packet, "PragmaticLive") !== false ||
                stripos($raw_packet, "LiveCasino") !== false ||
                stripos($raw_packet, "LiveDealer") !== false
            );

            $is_live_provider = (isset($data["provider"]) && (stripos($data["provider"], "Ezugi") !== false || stripos($data["provider"], "Evolution") !== false));

            // Safety Fallback: If name is unknown, we MUST wait to be safe
            $is_unknown_game = ($const_game_name == "" || strpos($const_game_name, "Game ") === 0);

            $lower_name = strtolower($const_game_name);
            $is_delayed = ($is_unknown_game || $is_live_marker || $is_live_provider || $is_sports ||
                in_array($const_game_uid, [
                    "edef29b5eda8e2eaf721d7315491c51d",
                    "a669c993b0e1f1b7da100fcf95516bdf",
                    "8a87aae7a3624d284306e9c6fe1b3e9c",
                    "5c4a12fb0a9b296d9b0d5f9e1cd41d65",
                    "c68a515f0b3b10eec96cf6d33299f4e2",
                    "052442de87321dd97bb91b1a35fb8e65",
                    "4a858d6b74c05260d3ea2762838798c7", // Roulette
                    "394fe6a2cde24bc487767236cc6eccd6", // Roulette
                    "b4af506243cafae52908e8fa266f8ff6", // Speed Roulette
                    "1c47c7cc3fd4ffc3d31dc095bc5dddc8", // Dragon Hatch
                    "d7e5f6258dd0dfdd29b3798f124b6b9d"  // Unknown Live Game
                ]) ||
                (strpos($lower_name, "lobby") !== false) ||
                (strpos($lower_name, "casino") !== false) ||
                (strpos($lower_name, "roulette") !== false) ||
                (strpos($lower_name, "rulet") !== false) ||
                (strpos($lower_name, "ruleta") !== false) ||
                (strpos($lower_name, "monopoly") !== false) ||
                (strpos($lower_name, "crazy") !== false) ||
                (strpos($lower_name, "mega ball") !== false) ||
                (strpos($lower_name, "dream catcher") !== false) ||
                (strpos($lower_name, "luck") !== false) ||
                (strpos($lower_name, "sports") !== false) ||
                (strpos($lower_name, "wicket") !== false) ||
                (strpos($lower_name, "esport") !== false) ||
                (strpos($lower_name, "aviator") !== false) ||
                (strpos($lower_name, "dice") !== false) ||
                (strpos($lower_name, "pachinko") !== false) ||
                (strpos($lower_name, "evolution") !== false) ||
                (strpos($lower_name, "ezugi") !== false) ||
                (strpos($lower_name, "baccarat") !== false) ||
                (strpos($lower_name, "poker") !== false) ||
                (strpos($lower_name, "rummy") !== false) ||
                (strpos($lower_name, "patti") !== false) ||
                (strpos($lower_name, "andar") !== false) ||
                (strpos($lower_name, "tiger") !== false) ||
                (strpos($lower_name, "lucky 7") !== false) ||
                (strpos($lower_name, "32 cards") !== false) ||
                (strpos($lower_name, "sic bo") !== false) ||
                (strpos($lower_name, "fan tan") !== false) ||
                (strpos($lower_name, "bac bo") !== false) ||
                (strpos($lower_name, "ludo") !== false) ||
                (strpos($lower_name, "tongits") !== false) ||
                (strpos($lower_name, "pusoy") !== false) ||
                (strpos($lower_name, "blackjack") !== false) ||
                (strpos($lower_name, "dealer") !== false) ||
                (strpos($lower_name, "table") !== false) ||
                (strpos($lower_name, "flush") !== false) ||
                (strpos($lower_name, "ak47") !== false) ||
                (count(explode("-", $m_order_id)) > 1) ||
                !empty($match_details)) &&
                    // Exclude known slot providers (JILI, CQ9, JDB) ONLY for named games.
                    // Unknown games (Game UID format) must ALWAYS wait for the result callback.
                ($is_unknown_game || !(isset($data["provider"]) && (stripos($data["provider"], "jili") !== false || stripos($data["provider"], "cq9") !== false || stripos($data["provider"], "jdb") !== false)));

            // Log decision for debugging
            $const_provider = $data["provider"] ?? "N/A";
            $debug_log = date('Y-m-d H:i:s') . " | DEBUG | Game: $const_game_name | UID: $const_game_uid | Provider: $const_provider | Delayed: " . ($is_delayed ? "YES" : "NO") . "\n";
            file_put_contents(__DIR__ . "/bet_logs.txt", $debug_log, FILE_APPEND);


            $merged = false;
            if ($merged_record) {
                $rid = intval($merged_record["id"]);
                $existing_profit = floatval($merged_record["tbl_match_profit"]);
                $new_profit = max($existing_profit, $win_amount);
                $new_cost = $previous_cost + $net_bet_change;

                if ($is_cashout_req) {
                    $new_status = "cashout";
                    $new_result = "won";
                } else if ($new_profit > $new_cost || ($new_profit > 0 && !$is_sports)) {
                    // Force profit status for any win > 0 in non-sports games
                    $new_status = "profit";
                    $new_result = "won";
                } else if ($new_profit == $new_cost && $new_cost > 0) {
                    $new_status = "tie";
                    $new_result = "tie";
                } else if ($is_sports && $new_profit > 0 && $new_profit < $new_cost) {
                    $new_status = "cashout";
                    $new_result = "won";
                } else if ($is_settlement || ($merged_record["tbl_match_status"] == "wait" && $bet_amount == 0 && !$is_sports)) {
                    // Settle if it's a settlement action OR if we're follow-up result for a waiting slot/casino game
                    if ($new_profit > 0) {
                        $new_status = "profit";
                        $new_result = "won";
                    } else {
                        $new_status = "loss";
                        $new_result = "lost";
                    }
                } else {
                    $new_status = $merged_record["tbl_match_status"];
                    $new_result = $merged_record["tbl_match_result"];
                }

                $e_bal = floatval($real_bal);
                // Only update result_time if the record is actually settling (not 'wait' anymore)
                $existing_res_time = $merged_record['tbl_result_time'] ?? null;
                $final_res_time = (strtolower($new_status) == "wait") ? $existing_res_time : $m_time;

                $ustmt = $conn->prepare("UPDATE tblmatchplayed SET tbl_match_cost = ?, tbl_match_invested = ?, tbl_match_profit = ?, tbl_last_acbalance = ?, tbl_match_status = ?, tbl_match_result = ?, tbl_result_time = ? WHERE id = ?");
                $ustmt->bind_param("ddddsssi", $new_cost, $new_cost, $new_profit, $e_bal, $new_status, $new_result, $final_res_time, $rid);
                $ustmt->execute();
                $ustmt->close();
                $merged = true;

                // DIAGNOSTIC LOGGING
                $log_upd = date("Y-m-d H:i:s") . " | UPD | Match Found ID: $rid | New Status: $new_status | Profit: $new_profit | Cost: $new_cost\n";
                file_put_contents("debug_log.txt", $log_upd, FILE_APPEND);
            }

            if (!$merged) {
                // INSERT NEW ROW if not found
                if ($win_amount > 0) {
                    if ($is_sports && $win_amount < $bet_amount) {
                        $m_status = "cashout";
                    } else {
                        // Any win > 0 for slots/casino is profit
                        $m_status = "profit";
                    }
                } else {
                    // Fallback to "wait" ONLY for explicitly identified delayed/asynchronous games 
                    // (e.g. Live Casino Roulette, Baccarat, Sportsbook, etc).
                    // For slots (like PG Soft / JILI) the response is fully resolved in the first callback;
                    // if win_amount is 0, it unequivocally means a loss.
                    if ($bet_amount > 0) {
                        $m_status = ($is_delayed) ? "wait" : "loss";
                    } else {
                        $m_status = "loss";
                    }
                }

                $m_result = ($m_status == "profit" || $m_status == "cashout") ? "won" : ((strtolower($m_status) == "wait") ? "pending" : "lost");
                // If it's an instant result (not wait), we store it as result time too
                $r_time_val = (strtolower($m_status) == "wait") ? null : $m_time;

                $istmt = $conn->prepare("INSERT IGNORE INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_match_invested, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp, tbl_result_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $istmt->bind_param("ssssdddsssssssss", $const_user_id, $m_order_id, $const_game_uid, $const_game_name, $bet_amount, $bet_amount, $win_amount, $m_result, $real_bal, $m_status, $const_game_name, $match_details, $bet_type, $odds, $m_time, $r_time_val);
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