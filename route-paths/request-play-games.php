<?php
$resArr = [];
$resArr["status_code"] = "failed";

date_default_timezone_set("Asia/Kolkata");
$curr_date_time = date("d-m-Y h:i a");
error_reporting(0);
function returnRequest($resArr)
{
    echo json_encode($resArr);
    exit();
}

function formatNumber($number)
{
    return number_format($number, 2, ".", "");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $const_user_id = "";
    function generateOrderID($length = 15)
    {
        $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $charactersLength = strlen($characters);
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return "GA0" . $randomString;
    }

    function encrypt($data, $key)
    {
        return base64_encode(
            openssl_encrypt($data, "AES-256-ECB", $key, OPENSSL_RAW_DATA)
        );
    }

    $json = file_get_contents("php://input");
    $data = json_decode($json);
    $const_user_id = $data->USER_ID;
    $const_game_name = $data->GAME_NAME;
    $const_game_uid = $data->GAME_UID;

    // Diagnostic logging
    $launch_log = date('Y-m-d H:i:s') . " - Launch Req: " . $json . "\n";
    file_put_contents(__DIR__ . "/launch_logs.txt", $launch_log, FILE_APPEND);

    if (
        $const_user_id != "" &&
        $const_game_name != "" &&
        $const_game_uid != ""
    ) {
        $headerObj = new RequestHeaders();
        $headerObj->checkAllHeaders();
        $secret_key = $headerObj->getAuthorization();

        if ($secret_key == "null" || $secret_key == "") {
            $resArr["status_code"] = "authorization_error";
            echo json_encode($resArr);
            return;
        }
    } else {
        $resArr["status_code"] = "invalid_params";
        returnRequest($resArr);
    }

    // Dynamic Game Name Syncing: Maintain a local map of UIDs to Names
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_game_names (
        tbl_game_id VARCHAR(255) PRIMARY KEY,
        tbl_game_name VARCHAR(255) NOT NULL,
        tbl_last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $sync_stmt = $conn->prepare("INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE tbl_game_name = ?");
    $sync_stmt->bind_param("sss", $const_game_uid, $const_game_name, $const_game_name);
    $sync_stmt->execute();
    $sync_stmt->close();
    $select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
    $select_query = mysqli_query($conn, $select_sql);
    if (mysqli_num_rows($select_query) > 0) {
        $select_sql = "SELECT tbl_balance, tbl_bonus_balance, tbl_sports_bonus, tbl_requiredplay_balance, tbl_withdrawl_balance, tbl_joined_under, tbl_account_status FROM tblusersdata WHERE tbl_uniq_id='$const_user_id'";
        $select_query = mysqli_query($conn, $select_sql);

        if (mysqli_num_rows($select_query) > 0) {
            $res_data = mysqli_fetch_assoc($select_query);
            $user_refered_by = $res_data["tbl_joined_under"];

            if ($res_data["tbl_account_status"] == "true") {
                $query = "SELECT tbl_service_value FROM tblservices WHERE tbl_service_name = 'GAME_STATUS'";
                $result = mysqli_query($conn, $query);
                if ($data = mysqli_fetch_assoc($result)) {
                    if ($data['tbl_service_value'] == "false") {
                        returnRequest(["status_code" => "game_off"]);
                    }
                }
                $total_bal = (float) $res_data["tbl_balance"] + (float) $res_data["tbl_bonus_balance"] + (float) $res_data["tbl_sports_bonus"];
                if ($total_bal < 0.01) {
                    $fail_log = date('Y-m-d H:i:s') . " - Balance Failure: User $const_user_id - Total: $total_bal\n";
                    file_put_contents(__DIR__ . "/launch_logs.txt", $fail_log, FILE_APPEND);
                    $resArr["status_code"] = "balance_error";
                    returnRequest($resArr);
                }
                $updated_balance = (float) $res_data["tbl_balance"];
                $timestamp = round(microtime(true) * 1000);

                $payloadData = json_encode([
                    "agency_uid" => $AGENCY_UID,
                    "timestamp" => $timestamp,
                    "member_account" => $PLAYER_PREFIX . $const_user_id,
                    "game_uid" => $const_game_uid,
                    "credit_amount" => formatNumber($updated_balance),
                    "currency_code" => "INR",
                    "is_cashout" => 1,
                    "language" => "en",
                    "home_url" => $API_ACCESS_URL,
                    "platform" => "web",
                    "callback_url" => $API_TARGET_URL . "game/",
                ]);
                $payload = encrypt($payloadData, $AES_SECRET_KEY);
                $headers = ["Content-Type: application/json"];
                $data = json_encode([
                    "agency_uid" => $AGENCY_UID,
                    "timestamp" => $timestamp,
                    "payload" => $payload,
                ]);

                $ch = curl_init($GAME_SERVER_URL . "/game/v1");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $response = curl_exec($ch);
                curl_close($ch);
                $json_data = json_decode($response, true);

                // Log full API response for debugging
                $api_log = date('Y-m-d H:i:s') . " - Game: $const_game_name | UID: $const_game_uid | API Response: " . $response . "\n";
                file_put_contents(__DIR__ . "/launch_logs.txt", $api_log, FILE_APPEND);

                if ($json_data["code"] != 0) {
                    $resArr["status_code"] = "server_error";
                    $resArr["message"] = $json_data["code"];
                    $resArr["api_error"] = $json_data["message"] ?? "Unknown error";
                    returnRequest($resArr);
                }
                $game_url = $json_data["payload"]["game_launch_url"];
                $resArr["data"]["game_url"] = $game_url;
                $resArr["status_code"] = "success";
            } else {
                $resArr["status_code"] = "account_error";
            }
        } else {
            $resArr["status_code"] = "auth_error";
        }
    } else {
        $resArr["status_code"] = "authorization_error";
    }
    mysqli_close($conn);
    echo json_encode($resArr);
}
?>