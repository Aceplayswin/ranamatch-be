<?php
/**
 * India Lotto (Running10) Game Launch Handler
 * 
 * Flow:
 * 1. Call /api/creat_or_default_player_login/ to get a Token
 * 2. Build the game URL: https://test.running10.tv/?Token=XXX&game=GAME_PARAM
 * 3. Return the URL to the frontend
 */

include __DIR__ . '/../../security/india_lotto_config.php';

// Check if we have a valid game parameter for this Game UID
// For Running10, the parameter is often the slug or ID
// We use our shared map to validate the ID exists
$lotto_game_id_sql = "SELECT lotto_game_id FROM tbl_games WHERE game_uid = '$const_game_uid' LIMIT 1";
$lotto_game_id_res = mysqli_query($conn, $lotto_game_id_sql);
$lotto_game_row = mysqli_fetch_assoc($lotto_game_id_res);
$lotto_game_id = $lotto_game_row['lotto_game_id'] ?? '';

if (empty($lotto_game_id) || !isset($INDIA_LOTTO_GAME_MAP[$lotto_game_id])) {
    $resArr["status_code"] = "server_error";
    $resArr["message"] = "Unknown India Lotto Game ID: $lotto_game_id for UID: $const_game_uid";
    $il_log = date('Y-m-d H:i:s') . " - IndiaLotto ERROR | Unknown Game ID: $lotto_game_id | UID: $const_game_uid\n";
    file_put_contents(__DIR__ . "/../launch_logs.txt", $il_log, FILE_APPEND);
} else {
    // Get the parameter from the map value (it contains the slug in parentheses if applicable)
    // E.g. "Bhagyathara (kerala-ww)" -> "kerala-ww"
    $val = $INDIA_LOTTO_GAME_MAP[$lotto_game_id];
    if (preg_match('/\(([^)]+)\)/', $val, $matches)) {
        $gameParam = $matches[1];
    } else {
        // If no slug in parens, assume it's just the ID or the slug is the value
        // Mapping specific ones for safety
        $paramMap = [
            '27' => '1.5min-car-race',
            '28' => 'wingo-1mins',
            '29' => 'wingo-3mins',
            '30' => 'dear-1pm',
            '31' => 'dear-6pm',
            '32' => 'dear-8pm',
            '33' => 'dear-online-1pm',
            '34' => 'dear-online-6pm',
            '35' => 'dear-online-8pm',
            '36' => 'matka-bse-sensex',
            '37' => '1min-3d-game',
            '40' => '3min-3d-game',
            '43' => '1min-4d-game',
            '46' => '3min-4d-game',
            '49' => '1min-5d-game',
            '52' => '3min-5d-game',
            '55' => '3min-car-race',
            '56' => 'matka-taiex',
            '57' => 'matka-dow-jones',
            '58' => 'matka-nasdaq',
            '59' => '10min-speed-matka',
            '60' => '4min-speed-matka',
            '61' => '6min-speed-matka',
            '62' => '1min-fast-matka',
            '63' => '3min-fast-matka',
            '64' => '1min-color-game',
            '65' => '3min-color-game',
            '66' => '1min-7up7down',
            '67' => '3min-7up7down',
            '68' => 'matka-nifty-50',
            '69' => 'matka-nifty-bank',
            '70' => 'matka-bse-midcap',
            '71' => 'matka-bse-smallcap',
            '72' => 'kerala-3d-game',
            '73' => 'kerala-4d-game',
            '74' => 'kerala-5d-game',
            '75' => 'dear-1pm-3d-game',
            '76' => 'dear-1pm-4d-game',
            '77' => 'dear-1pm-5d-game',
            '78' => 'dear-6pm-3d-game',
            '79' => 'dear-6pm-4d-game',
            '80' => 'dear-6pm-5d-game',
            '81' => 'dear-8pm-3d-game',
            '82' => 'dear-8pm-4d-game',
            '83' => 'dear-8pm-5d-game',
            '84' => 'shillong-teer',
            '85' => '1min-shillong-teer',
            '86' => '2min-shillong-teer',
            '87' => '1min-2d',
            '88' => '3min-2d',
            '89' => '1min-dice',
            '90' => '3min-dice',
            '91' => '1min-lucky28',
            '92' => '3min-lucky28',
        ];
        $gameParam = $paramMap[$const_game_uid] ?? strtolower(str_replace(' ', '-', $val));
    }
}

if (!isset($gameParam) || !$gameParam) {
    // Already handled in the IF block above, but keeping as safety
    if ($resArr["status_code"] !== "server_error") {
        $resArr["status_code"] = "server_error";
        $resArr["message"] = "Invalid India Lotto Game Parameter";
    }
} else {
    // ========================================
    // STEP 1: Create/Login Player to get Token
    // ========================================
    $playerAcc = $INDIALOTTO_PLATFORM_CODE . "_" . $const_user_id; // Prefix with platform code

    $dataObj = [
        "acc" => $playerAcc,
        "name" => "Player" . $const_user_id
    ];

    $dataStr = json_encode($dataObj);

    // Generate the Sign (MD5 of Data + SecretKey) - converted to lowercase
    $sign = md5($dataStr . $INDIALOTTO_SECRET_KEY);

    // Prepare the POST fields
    $postFields = [
        "Data" => $dataStr,
        "Sign" => $sign,
        "PlatformCode" => $INDIALOTTO_PLATFORM_CODE
    ];

    // Call the login API
    $loginUrl = $INDIALOTTO_API_URL . "creat_or_default_player_login/";
    $ch = curl_init($loginUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/x-www-form-urlencoded"
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the API interaction
    $error_info = $curl_error ? " | CURL_ERROR: $curl_error" : "";
    $il_log = date('Y-m-d H:i:s') . " - IndiaLotto Login | URL: $loginUrl | User: $playerAcc | HTTP: $http_code | Response: $response$error_info\n";
    file_put_contents(__DIR__ . "/../launch_logs.txt", $il_log, FILE_APPEND);

    $json_res = json_decode($response, true);

    if ($json_res && $json_res['code'] == 0) {
        // ========================================
        // STEP 2: Build the Game URL with Token
        // ========================================
        $responseData = json_decode($json_res['data'], true);
        $token = $responseData['Token'] ?? '';

        if (!empty($token)) {
            // Build game URL with Token and game parameter
            $gameUrl = "https://test.running10.tv/?Token=" . $token 
                     . "&game=" . $gameParam 
                     . "&homeurl=" . urlencode($API_ACCESS_URL)
                     . "&backmode=0";

            $resArr["data"]["game_url"] = $gameUrl;
            $resArr["status_code"] = "success";

            $il_log = date('Y-m-d H:i:s') . " - IndiaLotto SUCCESS | Game URL: $gameUrl\n";
            file_put_contents(__DIR__ . "/../launch_logs.txt", $il_log, FILE_APPEND);
        } else {
            $resArr["status_code"] = "server_error";
            $resArr["message"] = "Token not received from provider";
        }
    } else {
        // Handle error
        $resArr["status_code"] = "server_error";
        $resArr["message"] = $json_res['msg'] ?? "Provider connection failed";
        $resArr["api_error"] = $response;
    }
}
