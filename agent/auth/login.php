<?php
/**
 * Agent Login Endpoint
 * Endpoint: POST /agent/auth/login
 * Validates agent credentials, checks account status, and mints JWT token.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/JWTHandler.php';

header('Content-Type: application/json; charset=utf-8');

// Allow OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

// Parse JSON or POST data
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$identifier = trim($input['username'] ?? $input['email'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email/Username and Password are required."]);
    exit;
}

// Fetch agent record including 2FA status
$sql = "SELECT id, agent_code, username, name, email, password_hash, rank_level, status, must_change_password, two_factor_secret, two_factor_enabled 
        FROM agents 
        WHERE email = ? OR username = ? OR agent_code = ? 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sss", $identifier, $identifier, $identifier);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($res);

if (!$agent) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
    exit;
}

// Verify password
if (!password_verify($password, $agent['password_hash'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
    exit;
}

// Check agent account status
if ($agent['status'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Account is " . ucfirst($agent['status']) . ". Please contact administrator."
    ]);
    exit;
}

$jwtHandler = new JWTHandler();

// Check if 2-Factor Authentication is enabled for this account
if ((int)($agent['two_factor_enabled'] ?? 0) === 1 && !empty($agent['two_factor_secret'])) {
    $preAuthPayload = [
        'user_id' => (int)$agent['id'],
        'username' => $agent['username'],
        'type' => 'pre_auth_2fa',
        'exp' => time() + 300 // 5 minutes
    ];
    $preAuthToken = $jwtHandler->encode($preAuthPayload);

    echo json_encode([
        "status" => "2fa_required",
        "message" => "2-Factor Verification Code required.",
        "pre_auth_token" => $preAuthToken,
        "agent" => [
            "id" => (int)$agent['id'],
            "agent_code" => $agent['agent_code'],
            "username" => $agent['username'],
            "name" => $agent['name']
        ]
    ]);
    exit;
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
    "message" => "Login successful",
    "token" => $token,
    "agent" => [
        "id" => (int)$agent['id'],
        "agent_code" => $agent['agent_code'],
        "username" => $agent['username'],
        "name" => $agent['name'],
        "email" => $agent['email'],
        "rank_level" => $agent['rank_level'],
        "must_change_password" => (bool)($agent['must_change_password'] ?? false)
    ]
]);
