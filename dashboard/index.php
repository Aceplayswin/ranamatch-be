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
        mysqli_set_charset($conn, 'utf8mb4');
    } else {
        throw new Exception("Unable to connect");
    }
} catch (Throwable $e) {
    echo $e->getMessage();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function decrypt($data, $key) {
        return openssl_decrypt(base64_decode($data), "AES-256-ECB", $key, OPENSSL_RAW_DATA);
    }

    function encrypt($data, $key) {
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

    if (!$data) { exit; }

    $const_game_uid = $data["game_uid"];
    $win_amount = floatval($data["win_amount"]);
    $bet_amount = floatval($data["bet_amount"]);
    $m_order_id = $data["game_round"] ?? $data["serial_number"] ?? ("API" . bin2hex(random_bytes(6)));

    // Standardized Parsing
    $match_details = ""; $bet_type = ""; $odds = ""; 
    $const_game_name = $data["game_name"] ?? $data["gameName"] ?? "";
    $const_game_uid = $data["game_uid"] ?? "N/A";

    // Common Game Mappings if provider doesn't send name
    $game_mappings = [
        "8a87aae7a3624d284306e9c6fe1b3e9c" => "Dice",
        "fb2a2ac51303c0a0801dbe6a72d936f7" => "Leprechaun Riches",
        "56a42a03c0908cf807ba251fc52b0338" => "Hotline",
        "8ef39602e589bf9f32fc351b1cbb338b" => "Evolution Lobby",
        "d0e052b031dfcdb08d1803f4bcc618ef" => "Ezugi Lobby",
        "a04d1f3eb8ccec8a4823bdf18e3f0e84" => "Aviator",
        "4a858d6b74c05260d3ea2762838798c7" => "Lightning Roulette",
        "917c0c51d248c33eb058e3210a2e7371" => "Crazy Time",
        "d496ac5fd91702331133e44b6bd12b26" => "MONOPOLY Live"
    ];

    if ($const_game_name == "" && isset($game_mappings[$const_game_uid])) {
        $const_game_name = $game_mappings[$const_game_uid];
    }

    // Dynamic Database Metadata Fallback
    if ($const_game_name == "") {
        $m_stmt = $conn->prepare("SELECT tbl_game_name FROM tbl_game_names WHERE tbl_game_id = ?");
        $m_stmt->bind_param("s", $const_game_uid);
        $m_stmt->execute();
        $m_res = $m_stmt->get_result();
        if ($m_row = $m_res->fetch_assoc()) {
            $const_game_name = $m_row['tbl_game_name'];
        }
        $m_stmt->close();
    }

    if (isset($data["data"])) {
        $sports_data = is_string($data["data"]) ? json_decode($data["data"], true) : $data["data"];
        if ($sports_data) {
            $match_details = $sports_data["leagueName_en"] ?? $sports_data["sportTypeName_en"] ?? "";
            $bet_type = $sports_data["betChoice_en"] ?? $sports_data["marketName_en"] ?? "";
            $odds = $sports_data["odds"] ?? "";
            $const_game_name = "SABA Sports"; // Standardized
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

            // Insufficient Balance Check
            if ($bet_amount > 0 && $total_avail_bal < $bet_amount) {
                echo json_encode(["code" => 1, "msg" => "Insufficient balance"]);
                exit;
            }

            $credit_amount = $total_avail_bal - $bet_amount + $win_amount;

            // Simple Balance Check (if requested)
            if ($bet_amount == 0 && $win_amount == 0) {
                $payloadData = json_encode(["credit_amount" => $credit_amount, "timestamp" => round(microtime(true) * 1000)]);
                $payload = encrypt($payloadData, $AES_KEY);
                echo json_encode(["code" => 0, "msg" => "", "payload" => $payload]);
                exit;
            }

            // Balance Deductions
            $rem_bet = $bet_amount;
            if ($bonus_bal > 0 && $rem_bet > 0) { $ded = min($bonus_bal, $rem_bet); $bonus_bal -= $ded; $rem_bet -= $ded; }
            if ($sports_bonus > 0 && $rem_bet > 0) { $ded = min($sports_bonus, $rem_bet); $sports_bonus -= $ded; $rem_bet -= $ded; }
            $real_bal = $real_bal - $rem_bet + $win_amount;
            if ($real_bal < 0) $real_bal = 0;

            $wagering = $wagering + $win_amount - $bet_amount;
            if ($wagering < 0) $wagering = 0;

            $bonus_comp = false;
            if (floatval($u_row["tbl_requiredplay_balance"]) > 0 && $wagering <= 0) {
                $bonus_comp = true;
                $real_bal += ($bonus_bal + $sports_bonus);
                $bonus_bal = 0; $sports_bonus = 0;
            }

            // Update Balances
            if ($bonus_comp) {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=0, tbl_sports_bonus=0, tbl_requiredplay_balance=0, tbl_active_bonus_id=0, tbl_is_bonus_locked=0 WHERE tbl_uniq_id=?");
                $stmt->bind_param("ds", $real_bal, $const_user_id);
            } else {
                $stmt = $conn->prepare("UPDATE tblusersdata SET tbl_balance=?, tbl_bonus_balance=?, tbl_sports_bonus=?, tbl_requiredplay_balance=? WHERE tbl_uniq_id=?");
                $stmt->bind_param("dddds", $real_bal, $bonus_bal, $sports_bonus, $wagering, $const_user_id);
            }
            $stmt->execute(); $stmt->close();

            // MATCH RECORDING - Synchronized with game/index.php
            $m_time = date("d-m-Y h:i a");
            if ($const_game_name == "") { $const_game_name = "Game " . $const_game_uid; }

            $merged = false;
            if ($bet_amount == 0) {
                $e_uid = mysqli_real_escape_string($conn, $const_user_id);
                $e_order_id = mysqli_real_escape_string($conn, $m_order_id);
                $res = mysqli_query($conn, "SELECT id, tbl_match_cost, tbl_match_profit FROM tblmatchplayed WHERE tbl_user_id='$e_uid' AND tbl_uniq_id='$e_order_id' ORDER BY id DESC LIMIT 1");
                
                if ($res && mysqli_num_rows($res) > 0) {
                    $row = mysqli_fetch_assoc($res);
                    $new_profit = floatval($row["tbl_match_profit"]) + $win_amount;
                    $cost = floatval($row["tbl_match_cost"]);
                    
                    if ($new_profit > $cost) { $new_status = "profit"; $new_result = "won"; }
                    else if ($new_profit == $cost && $cost > 0) { $new_status = "tie"; $new_result = "tie"; }
                    else { $new_status = "loss"; $new_result = "lost"; }
                    
                    $rid = intval($row["id"]); $e_bal = floatval($real_bal);
                    mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_match_profit = $new_profit, tbl_last_acbalance = $e_bal, tbl_match_status = '$new_status', tbl_match_result = '$new_result' WHERE id = $rid");
                    $merged = true;
                }
            }

            if (!$merged) {
                // INSERT NEW ROW for each distinct match (unique m_order_id)
                $is_sports = ($const_game_name === "SABA Sports" || !empty($match_details));
                
                if ($win_amount > 0) {
                    $m_status = "profit";
                } else {
                    // For sports, a bet with 0 win is 'wait' (pending settlement).
                    // For slots/non-sports, a bet with 0 win is 'loss' immediately.
                    $m_status = ($is_sports && $bet_amount > 0) ? "wait" : "loss";
                }
                
                $m_result = ($m_status == "profit") ? "won" : (($m_status == "wait") ? "pending" : "lost");

                $istmt = $conn->prepare("INSERT IGNORE INTO tblmatchplayed (tbl_user_id, tbl_uniq_id, tbl_period_id, tbl_invested_on, tbl_match_cost, tbl_lot_size, tbl_match_invested, tbl_match_fee, tbl_match_profit, tbl_match_result, tbl_last_acbalance, tbl_match_status, tbl_project_name, tbl_match_details, tbl_bet_type, tbl_odds, tbl_time_stamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $zero = "0";
                $istmt->bind_param("ssssddddsssssssss", $const_user_id, $m_order_id, $const_game_uid, $bet_amount, $bet_amount, $zero, $bet_amount, $zero, $win_amount, $m_result, $real_bal, $m_status, $const_game_name, $match_details, $bet_type, $odds, $m_time);
                $istmt->execute(); $istmt->close();
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