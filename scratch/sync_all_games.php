<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';
include 'd:/xampp/htdocs/security/constants.php';

echo "<pre>";
echo "========================================================\n";
echo "WINCO PROVIDER SYNC SYSTEM v3.0\n";
echo "========================================================\n\n";

function encrypt_payload($data, $key) {
    return base64_encode(openssl_encrypt($data, "AES-256-ECB", $key, OPENSSL_RAW_DATA));
}

$timestamp = round(microtime(true) * 1000);
$payloadData = json_encode([
    "agency_uid" => $AGENCY_UID,
    "timestamp" => $timestamp,
    "language" => "en"
]);

$payload = encrypt_payload($payloadData, $AES_SECRET_KEY);
$data = json_encode([
    "agency_uid" => $AGENCY_UID,
    "timestamp" => $timestamp,
    "payload" => $payload,
]);

$api_endpoint = $GAME_SERVER_URL . "/game/v1/games";
echo "Connecting to Provider: $api_endpoint...\n";

$is_local = (isset($_SERVER['HTTP_HOST']) && (
    stripos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === 0 ||
    strpos($_SERVER['HTTP_HOST'], '192.168.') === 0
)) || (php_sapi_name() === 'cli'); // CLI script on local developer PC is also local

if ($is_local) {
    $proxy_url = "https://api.velplay365.com/proxy";
    $proxy_payload = json_encode([
        "agency_uid" => $AGENCY_UID,
        "target_url" => $api_endpoint,
        "post_data" => $data,
        "headers" => ["Content-Type: application/json"]
    ]);

    $ch = curl_init($proxy_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $proxy_payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

if ($http_code !== 200) {
    echo "ERROR: Server returned HTTP $http_code\n";
    echo "Response: $response\n";
    exit;
}

$res = json_decode($response, true);

if ($res['code'] != 0) {
    echo "PROVIDER ERROR: " . ($res['message'] ?? 'Unknown Error') . "\n";
    exit;
}

// The games list is usually in $res['payload'] or $res['data']
$game_list = $res['payload'] ?? $res['data'] ?? [];

echo "Found " . count($game_list) . " games in provider catalog.\n\n";

$added = 0;
$updated = 0;

foreach ($game_list as $game) {
    $uid = $game['game_uid'] ?? $game['uid'] ?? '';
    $name = $game['game_name'] ?? $game['title'] ?? $game['name'] ?? '';
    
    if ($uid && $name) {
        $stmt = $conn->prepare("INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE tbl_game_name = VALUES(tbl_game_name)");
        $stmt->bind_param("ss", $uid, $name);
        $stmt->execute();
        
        if ($stmt->affected_rows == 1) $added++;
        else if ($stmt->affected_rows == 2) $updated++;
        $stmt->close();
    }
}

echo "--------------------------------------------------------\n";
echo "SYNC COMPLETED SUCCESSFULLY!\n";
echo "New Games Discovered: $added\n";
echo "Names Updated: $updated\n";
echo "--------------------------------------------------------\n";
echo "</pre>";
?>
