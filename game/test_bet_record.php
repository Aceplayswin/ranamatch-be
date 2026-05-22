<?php
/**
 * Brute-force field name discovery for /game/transaction/list
 * The API decrypts fine but says "Start and end date cannot be empty"
 */
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$AGENCY_UID = "d28a8d5f4fa53910826caa6640925239";
$AES_KEY    = "1f806d609f1ef42a131a187d1509ca98";
$SERVER_URL = "https://huidu.bet";

function aes_encrypt($data, $key) {
    return base64_encode(openssl_encrypt($data, "AES-256-ECB", $key, OPENSSL_RAW_DATA));
}
function aes_decrypt($data, $key) {
    return openssl_decrypt(base64_decode($data), "AES-256-ECB", $key, OPENSSL_RAW_DATA);
}
function call_api($url, $body) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $http_code, 'response' => $response];
}

$timestamp = strval(round(microtime(true) * 1000));
$now = time();

// Try many possible field name combos for dates
$date_combos = [
    ["startDate" => date("Y-m-d H:i:s", $now - 86400), "endDate" => date("Y-m-d H:i:s", $now)],
    ["startTime" => date("Y-m-d H:i:s", $now - 86400), "endTime" => date("Y-m-d H:i:s", $now)],
    ["start_date" => date("Y-m-d H:i:s", $now - 86400), "end_date" => date("Y-m-d H:i:s", $now)],
    ["start" => date("Y-m-d H:i:s", $now - 86400), "end" => date("Y-m-d H:i:s", $now)],
    ["startTime" => strval(($now - 86400) * 1000), "endTime" => strval($now * 1000)],
    ["start_time" => strval(($now - 86400) * 1000), "end_time" => strval($now * 1000)],
    ["startTime" => strval($now - 86400), "endTime" => strval($now)],
    ["start_time" => strval($now - 86400), "end_time" => strval($now)],
    ["startTime" => date("Y-m-d", $now - 86400), "endTime" => date("Y-m-d", $now)],
    ["start_time" => date("Y-m-d", $now - 86400), "end_time" => date("Y-m-d", $now)],
    ["startDate" => date("Y-m-d", $now - 86400), "endDate" => date("Y-m-d", $now)],
    ["start_date" => date("Y-m-d", $now - 86400), "end_date" => date("Y-m-d", $now)],
    ["begin_date" => date("Y-m-d H:i:s", $now - 86400), "finish_date" => date("Y-m-d H:i:s", $now)],
    ["from" => date("Y-m-d H:i:s", $now - 86400), "to" => date("Y-m-d H:i:s", $now)],
    ["from_date" => date("Y-m-d H:i:s", $now - 86400), "to_date" => date("Y-m-d H:i:s", $now)],
    ["begin" => date("Y-m-d H:i:s", $now - 86400), "end" => date("Y-m-d H:i:s", $now)],
    ["beginTime" => date("Y-m-d H:i:s", $now - 86400), "endTime" => date("Y-m-d H:i:s", $now)],
    ["begin_time" => date("Y-m-d H:i:s", $now - 86400), "end_time" => date("Y-m-d H:i:s", $now)],
    ["dateStart" => date("Y-m-d H:i:s", $now - 86400), "dateEnd" => date("Y-m-d H:i:s", $now)],
    ["date_start" => date("Y-m-d H:i:s", $now - 86400), "date_end" => date("Y-m-d H:i:s", $now)],
    // UTC timestamps
    ["startDate" => strval(($now - 86400) * 1000), "endDate" => strval($now * 1000)],
    // Unix timestamps (seconds)
    ["start_date" => strval($now - 86400), "end_date" => strval($now)],
    ["startDate" => strval($now - 86400), "endDate" => strval($now)],
    // Different date format: dd-mm-yyyy
    ["start_date" => date("d-m-Y H:i:s", $now - 86400), "end_date" => date("d-m-Y H:i:s", $now)],
    // ISO 8601
    ["start_date" => date("c", $now - 86400), "end_date" => date("c", $now)],
    // With T separator
    ["start_date" => date("Y-m-d\TH:i:s", $now - 86400), "end_date" => date("Y-m-d\TH:i:s", $now)],
];

$results = [];
foreach ($date_combos as $i => $dates) {
    $payload_arr = array_merge([
        "agency_uid" => $AGENCY_UID,
        "timestamp"  => $timestamp,
        "page"       => 1,
        "page_size"  => 5,
    ], $dates);
    
    $payload_json = json_encode($payload_arr);
    $encrypted = aes_encrypt($payload_json, $AES_KEY);
    $body = json_encode([
        "agency_uid" => $AGENCY_UID,
        "timestamp"  => $timestamp,
        "payload"    => $encrypted,
    ]);
    
    $r = call_api("$SERVER_URL/game/transaction/list", $body);
    $rd = json_decode($r['response'], true);
    
    $code = $rd['code'] ?? null;
    $msg = $rd['msg'] ?? $rd['message'] ?? null;
    
    // Decrypt response if success
    $decrypted = null;
    if ($code == 0 && isset($rd['payload'])) {
        if (is_string($rd['payload'])) {
            $dec = aes_decrypt($rd['payload'], $AES_KEY);
            $decrypted = json_decode($dec, true) ?: $dec;
        } else {
            $decrypted = $rd['payload'];
        }
    }
    
    $results["combo_$i"] = [
        "fields" => array_keys($dates),
        "values_sample" => array_values($dates)[0],
        "code" => $code,
        "msg"  => $msg,
    ];
    
    if ($decrypted) {
        $results["combo_$i"]["SUCCESS"] = true;
        $results["combo_$i"]["data"] = $decrypted;
        // Found it! Stop.
        break;
    }
    
    // If we got a DIFFERENT error (not 10028), that's interesting too
    if ($code != 10028) {
        $results["combo_$i"]["DIFFERENT_ERROR"] = true;
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
