<?php
/**
 * Setup 2FA Endpoint
 * Endpoint: POST /agent/auth/2fa/setup
 * Generates Base32 secret key and QR code for scanning in Google Authenticator.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent session required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

// Fetch current agent 2FA status & secret
$stmt = mysqli_prepare($conn, "SELECT id, username, email, two_factor_secret, two_factor_enabled FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent not found."]);
    exit;
}

$secret = $agent['two_factor_secret'];
if (empty($secret)) {
    $secret = GoogleAuthenticatorService::generateSecret(16);
    // Save generated secret (unverified until enable_2fa step)
    $upd = mysqli_prepare($conn, "UPDATE agents SET two_factor_secret = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $secret, $agentId);
    mysqli_stmt_execute($upd);
}

$qrData = GoogleAuthenticatorService::getQrCodeUrl($agent['username'], $secret, 'Velplay Agent Console');

echo json_encode([
    "status" => "success",
    "data" => [
        "enabled" => (bool)$agent['two_factor_enabled'],
        "secret" => $secret,
        "qr_code_url" => $qrData['qr_code_url'],
        "otpauth_url" => $qrData['otpauth_url'],
        "username" => $agent['username']
    ]
]);
