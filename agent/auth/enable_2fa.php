<?php
/**
 * Enable/Disable 2FA Endpoint
 * Endpoint: POST /agent/auth/2fa/enable
 * Verifies initial 6-digit code provided by agent, updates two_factor_enabled status.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent session required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$code = trim($input['code'] ?? $input['otpCode'] ?? '');
$action = trim($input['action'] ?? 'enable'); // 'enable' or 'disable'

if (empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "6-digit 2FA verification code is required."]);
    exit;
}

// Fetch secret
$stmt = mysqli_prepare($conn, "SELECT two_factor_secret, two_factor_enabled FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent || empty($agent['two_factor_secret'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "2FA Secret is not setup. Please run setup first."]);
    exit;
}

$isValid = GoogleAuthenticatorService::verifyCode($agent['two_factor_secret'], $code, 1);

if (!$isValid) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid 2FA code. Please check your Google Authenticator app."]);
    exit;
}

$targetStatus = ($action === 'disable') ? 0 : 1;

$upd = mysqli_prepare($conn, "UPDATE agents SET two_factor_enabled = ? WHERE id = ?");
mysqli_stmt_bind_param($upd, "ii", $targetStatus, $agentId);
mysqli_stmt_execute($upd);

echo json_encode([
    "status" => "success",
    "message" => $targetStatus === 1 ? "2-Factor Authentication enabled successfully!" : "2-Factor Authentication disabled successfully.",
    "enabled" => (bool)$targetStatus
]);
