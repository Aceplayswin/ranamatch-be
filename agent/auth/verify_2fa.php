<?php
/**
 * Login 2FA Code Verification Endpoint
 * Endpoint: POST /agent/auth/2fa/verify
 * Validates 6-digit TOTP code during login and issues final JWT token.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/JWTHandler.php';
require_once __DIR__ . '/../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$preAuthToken = trim($input['pre_auth_token'] ?? '');
$agentId = (int)($input['user_id'] ?? $input['agent_id'] ?? 0);
$username = trim($input['username'] ?? '');
$code = trim($input['code'] ?? $input['otpCode'] ?? '');

if (empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "6-Digit 2FA Code is required."]);
    exit;
}

// Decode pre_auth_token if provided
$jwtHandler = new JWTHandler();
if (!empty($preAuthToken)) {
    $decoded = $jwtHandler->decode($preAuthToken);
    if ($decoded && isset($decoded['user_id']) && ($decoded['type'] ?? '') === 'pre_auth_2fa') {
        $agentId = (int)$decoded['user_id'];
    }
}

if ($agentId <= 0 && !empty($username)) {
    $uStmt = mysqli_prepare($conn, "SELECT id FROM agents WHERE username = ? OR email = ? OR agent_code = ? LIMIT 1");
    mysqli_stmt_bind_param($uStmt, "sss", $username, $username, $username);
    mysqli_stmt_execute($uStmt);
    $uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
    if ($uRow) {
        $agentId = (int)$uRow['id'];
    }
}

if ($agentId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid pre-authentication session or agent ID."]);
    exit;
}

// Fetch agent record
$stmt = mysqli_prepare($conn, "SELECT id, agent_code, username, name, email, rank_level, status, partnership_pct, current_credit, exposed_credit, two_factor_secret, two_factor_enabled FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent account not found."]);
    exit;
}

if ($agent['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Account is " . $agent['status'] . "."]);
    exit;
}

// Verify 2FA TOTP code
if ($agent['two_factor_enabled'] == 1 && !empty($agent['two_factor_secret'])) {
    $isValid = GoogleAuthenticatorService::verifyCode($agent['two_factor_secret'], $code, 1);
    if (!$isValid) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid 2FA Code. Please enter the current 6-digit code from Google Authenticator."]);
        exit;
    }
}

// Mint 24-Hour Final JWT Token
$tokenPayload = [
    'user_id' => (int)$agent['id'],
    'user_type' => 'agent',
    'user_code' => $agent['agent_code'],
    'rank_level' => $agent['rank_level']
];

$token = $jwtHandler->encode($tokenPayload);

echo json_encode([
    "status" => "success",
    "message" => "2FA verification successful",
    "token" => $token,
    "agent" => [
        "id" => (int)$agent['id'],
        "agent_code" => $agent['agent_code'],
        "username" => $agent['username'],
        "name" => $agent['name'],
        "email" => $agent['email'],
        "rank_level" => $agent['rank_level'],
        "partnership_pct" => (float)($agent['partnership_pct'] ?? 0),
        "current_credit" => (float)($agent['current_credit'] ?? 0),
        "exposed_credit" => (float)($agent['exposed_credit'] ?? 0)
    ]
]);
