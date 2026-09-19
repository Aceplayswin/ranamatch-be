<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$targetUrl = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);

if (!$targetUrl) {
    echo json_encode(['status_code' => 'invalid_url', 'message' => 'Invalid or missing target webhook URL']);
    exit();
}

$payload = [
    'event' => 'test.ping',
    'timestamp' => date('c'),
    'source' => 'Velplay Affiliate Backend Engine',
    'data' => [
        'message' => 'Webhook live dispatch successful!',
        'affiliate_id' => 'AFF-9477F0',
        'sample_event' => 'player.registration',
        'sample_data' => [
            'player_id' => 'USR_98214',
            'referral_code' => 'AFF-9477F0',
            'currency' => 'INR',
            'amount' => 100.00
        ]
    ]
];

$jsonPayload = json_encode($payload);

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonPayload),
    'User-Agent: Velplay-Webhook-Engine/1.0',
    'X-Signature: sha256=' . hash_hmac('sha256', $jsonPayload, 'secret_key')
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode([
        'status_code' => 'success',
        'http_code' => $httpCode,
        'message' => 'Webhook delivered successfully'
    ]);
} else {
    $err = $curlError ?: ('HTTP error ' . $httpCode);
    if ($httpCode == 404) {
        $err = 'HTTP 404: Webhook endpoint not found (URL may be expired or deleted)';
    } elseif ($httpCode == 0) {
        $err = 'Connection failed or timed out reaching destination server';
    }
    echo json_encode([
        'status_code' => 'delivery_failed',
        'http_code' => $httpCode ?: 500,
        'error' => $err
    ]);
}
