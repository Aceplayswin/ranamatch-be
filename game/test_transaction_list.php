<?php
/**
 * Test Script: HUIDU /game/transaction/list API
 * Purpose: Check if transaction records contain casino game selection details
 * (roulette numbers, colors, baccarat hands, etc.)
 */
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// --- Credentials ---
$AGENCY_UID = "d28a8d5f4fa53910826caa6640925239";
$AES_KEY    = "1f806d609f1ef42a131a187d1509ca98";
$SERVER_URL = "https://huidu.bet";

function aes_encrypt($data, $key) {
    return base64_encode(openssl_encrypt($data, "AES-256-ECB", $key, OPENSSL_RAW_DATA));
}

function aes_decrypt($data, $key) {
    return openssl_decrypt(base64_decode($data), "AES-256-ECB", $key, OPENSSL_RAW_DATA);
}

// --- Build the payload ---
// Query the last 24 hours of transactions
$now = time();
$start = date("Y-m-d H:i:s", $now - 86400); // 24 hours ago
$end   = date("Y-m-d H:i:s", $now);

$payload_json = json_encode([
    "agency_uid"     => $AGENCY_UID,
    "start_time"     => $start,
    "end_time"       => $end,
    "page"           => 1,
    "page_size"      => 20,
    "timestamp"      => strval(round(microtime(true) * 1000))
]);

$encrypted_payload = aes_encrypt($payload_json, $AES_KEY);

$request_body = json_encode([
    "agency_uid" => $AGENCY_UID,
    "timestamp"  => strval(round(microtime(true) * 1000)),
    "payload"    => $encrypted_payload
]);

// --- Send the request ---
$url = $SERVER_URL . "/game/transaction/list";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- Process response ---
$output = [
    "request_url"     => $url,
    "request_payload" => json_decode($payload_json, true),
    "http_code"       => $http_code,
];

if ($curl_error) {
    $output["curl_error"] = $curl_error;
}

$resp_data = json_decode($response, true);
$output["raw_response"] = $resp_data;

// Decrypt the response payload if present
if (isset($resp_data["payload"]) && is_string($resp_data["payload"])) {
    $decrypted = aes_decrypt($resp_data["payload"], $AES_KEY);
    $output["decrypted_payload"] = json_decode($decrypted, true) ?: $decrypted;
} elseif (isset($resp_data["payload"]) && is_array($resp_data["payload"])) {
    // Payload might already be decrypted JSON
    $output["decrypted_payload"] = $resp_data["payload"];
    
    // If records exist, try to decrypt each record's nested data
    if (isset($resp_data["payload"]["records"])) {
        $output["records_sample"] = array_slice($resp_data["payload"]["records"], 0, 5);
        $output["total_records"] = count($resp_data["payload"]["records"]);
    }
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
