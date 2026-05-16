<?php
/**
 * India Lotto (Running10) Game Launch Handler
 * 
 * Flow:
 * 1. Call /api/creat_or_default_player_login/ to get a Token
 * 2. Build the game URL: https://test.running10.tv/?Token=XXX&game=GAME_PARAM
 * 3. Return the URL to the frontend
 */

// Game ID to Game Parameter mapping (from documentation)
$gameParamMap = [
    '1'  => 'kerala-ww',       // Bhagyathara
    '2'  => 'kerala-ss',       // Sthree Sakthi
    '3'  => 'kerala-ff',       // Dhanalekshmi
    '4'  => 'kerala-kn',       // Karunya Plus
    '5'  => 'kerala-nr',       // Suvarana Keralam
    '6'  => 'kerala-kr',       // Karunya
    '7'  => 'kerala-ak',       // Samrudhi
    '8'  => 'kerala-oa',       // Online Only
    '9'  => 'kerala-br',       // Christmax New Year Bumper
    '10' => 'kerala-br',       // Summer Bumper
    '11' => 'kerala-br',       // Vishu Bumper
    '12' => 'kerala-br',       // Monsoon Bumper
    '13' => 'kerala-br',       // Thiruvonam Bumper
    '14' => 'kerala-br',       // Pooja Bumper
    '27' => '1.5min-car-race', // 1.5Min Car Race
    '28' => 'wingo-1mins',     // WinGo 1mins
    '29' => 'wingo-3mins',     // WinGo 3mins
    '30' => 'dear-1pm',        // Dear Nagaland Lottery 1PM
    '31' => 'dear-6pm',        // Dear Sikkim Lottery 6PM
    '32' => 'dear-8pm',        // Dear Nagaland Lottery 8PM
    '33' => 'dear-online-1pm', // Dear Lottery Online Only 1PM
    '34' => 'dear-online-6pm', // Dear Lottery Online Only 6PM
    '35' => 'dear-online-8pm', // Dear Lottery Online Only 8PM
    '36' => 'matka-bse-sensex',// BSE SENSEX Matka
    '37' => '1min-3d-game',    // 1Min 3D Game
    '40' => '3min-3d-game',    // 3Min 3D Game
    '43' => '1min-4d-game',    // 1Min 4D Game
    '46' => '3min-4d-game',    // 3Min 4D Game
    '49' => '1min-5d-game',    // 1Min 5D Game
    '52' => '3min-5d-game',    // 3Min 5D Game
    '55' => '3min-car-race',   // 3Min Car Race
    '56' => 'matka-taiex',     // TW TAIEX Matka
    '57' => 'matka-dow-jones', // DOW JONES Matka
    '58' => 'matka-nasdaq',    // NASDAQ Matka
    '59' => '10min-speed-matka',// 10Min Speed Matka
    '60' => '4min-speed-matka',// 4Min Speed Matka
    '61' => '6min-speed-matka',// 6Min Speed Matka
    '62' => '1min-fast-matka', // 1Min Fast Matka
    '63' => '3min-fast-matka', // 3Min Fast Matka
    '64' => '1min-color-game', // 1Min Color Game
    '65' => '3min-color-game', // 3Min Color Game
    '66' => '1min-7up7down',   // 1Min 7Up7Down
    '67' => '3min-7up7down',   // 3Min 7Up7Down
    '68' => 'matka-nifty-50',  // NIFTY 50 Matka
    '69' => 'matka-nifty-bank',// Nifty Bank Matka
    '70' => 'matka-bse-midcap',// BSE MIDCAP Matka
    '71' => 'matka-bse-smallcap',// BSE Smallcap Matka
    '72' => 'kerala-3d-game',  // KERALA 3D Game
    '73' => 'kerala-4d-game',  // KERALA 4D Game
    '74' => 'kerala-5d-game',  // KERALA 5D Game
    '75' => 'dear-1pm-3d-game',// DEAR 1PM 3D Game
    '76' => 'dear-1pm-4d-game',// DEAR 1PM 4D Game
    '77' => 'dear-1pm-5d-game',// DEAR 1PM 5D Game
    '78' => 'dear-6pm-3d-game',// DEAR 6PM 3D Game
    '79' => 'dear-6pm-4d-game',// DEAR 6PM 4D Game
    '80' => 'dear-6pm-5d-game',// DEAR 6PM 5D Game
    '81' => 'dear-8pm-3d-game',// DEAR 8PM 3D Game
    '82' => 'dear-8pm-4d-game',// DEAR 8PM 4D Game
    '83' => 'dear-8pm-5d-game',// DEAR 8PM 5D Game
    '84' => 'shillong-teer',   // SHILLONG TEER
    '85' => '1min-shillong-teer',// 1Min SHILLONG TEER ONLINE
    '86' => '2min-shillong-teer',// 2Min SHILLONG TEER ONLINE
    '87' => '1min-2d',         // 1Min 2D Game
    '88' => '3min-2d',         // 3Min 2D Game
    '89' => '1min-dice',       // 1Min Dice
    '90' => '3min-dice',       // 3Min Dice
    '91' => '1min-lucky28',    // Lucky 28 1Min
    '92' => '3min-lucky28',    // Lucky 28 3Min
];

// Check if we have a valid game parameter for this Game UID
$gameParam = $gameParamMap[$const_game_uid] ?? null;

if (!$gameParam) {
    $resArr["status_code"] = "server_error";
    $resArr["message"] = "Unknown India Lotto Game ID: $const_game_uid";
    $il_log = date('Y-m-d H:i:s') . " - IndiaLotto ERROR | Unknown Game ID: $const_game_uid\n";
    file_put_contents(__DIR__ . "/../launch_logs.txt", $il_log, FILE_APPEND);
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
