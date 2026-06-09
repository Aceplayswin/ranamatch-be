<?php
// Secure proxy for routing local development requests through production server.
define("ACCESS_SECURITY", "true");
include __DIR__ . '/security/config.php';
include __DIR__ . '/security/constants.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

// Verify using AGENCY_UID as a secure passphrase
$incoming_agency = $input["agency_uid"] ?? "";
if (empty($incoming_agency) || $incoming_agency !== $AGENCY_UID) {
    http_response_code(403);
    echo json_encode(["code" => 403, "message" => "Unauthorized proxy access"]);
    exit;
}

$target_url = $input["target_url"] ?? "";
$parsed_url = parse_url($target_url);
$host = $parsed_url["host"] ?? "";

// Strictly limit domain destinations for security
$allowed_hosts = ['huidu.bet', 'huidu365.com', 'running10.tv', 'server-test.running10.tv'];
$is_allowed = false;
foreach ($allowed_hosts as $allowed) {
    if (stripos($host, $allowed) !== false) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Invalid target host"]);
    exit;
}

$post_data = $input["post_data"] ?? "";
$headers = $input["headers"] ?? ["Content-Type: application/json"];

$ch = curl_init($target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(["code" => 500, "message" => "Proxy cURL error: " . $err]);
} else {
    echo $response;
}
?>
