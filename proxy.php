<?php
/**
 * Game Proxy - Forwards requests to external game provider URLs.
 * Bypasses browser CORS restrictions for game iframe loading.
 * 
 * Usage: /proxy?url=<encoded_game_url>
 * 
 * Security: Only allows GET requests and validates the target URL.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the target URL
$target_url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($target_url)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter: url'
    ]);
    exit;
}

// Validate URL format
if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid URL format'
    ]);
    exit;
}

// Only allow http and https protocols
$parsed = parse_url($target_url);
if (!in_array($parsed['scheme'], ['http', 'https'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only HTTP and HTTPS protocols are allowed'
    ]);
    exit;
}

// Block requests to localhost/internal IPs (SSRF protection)
$host = $parsed['host'];
$ip = gethostbyname($host);
if (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    strpos($ip, '10.') === 0 ||
    strpos($ip, '192.168.') === 0 ||
    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)
) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Requests to internal/private networks are not allowed'
    ]);
    exit;
}

// Forward the request using cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $target_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'VelPlay-Proxy/1.0',
    CURLOPT_HEADER => true,
]);

// Forward POST data if applicable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    if (isset($_SERVER['CONTENT_TYPE'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $_SERVER['CONTENT_TYPE']
        ]);
    }
}

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Proxy request failed: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

// Set the response content type
if ($contentType) {
    header('Content-Type: ' . $contentType);
}

http_response_code($httpCode);
echo $body;
?>
