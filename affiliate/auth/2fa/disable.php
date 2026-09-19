<?php
/**
 * Disable Affiliate 2FA Endpoint
 * Endpoint: POST /affiliate/auth/2fa/disable
 * Disables 2FA after verifying current 6-digit TOTP code.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../../config/auth_middleware.php';
require_once __DIR__ . '/../../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate session required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;
$code = trim($data['code'] ?? $data['otp'] ?? '');

if (empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "6-digit verification code is required to disable 2FA."]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT two_factor_secret, two_factor_enabled FROM affiliates WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$aff || empty($aff['two_factor_secret'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "2FA is not enabled."]);
    exit;
}

if (!GoogleAuthenticatorService::verifyCode($aff['two_factor_secret'], $code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid verification code."]);
    exit;
}

// Disable 2FA
$upd = mysqli_prepare($conn, "UPDATE affiliates SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
mysqli_stmt_bind_param($upd, "i", $affiliateId);
mysqli_stmt_execute($upd);

echo json_encode([
    "status" => "success",
    "message" => "Google Authenticator 2FA disabled successfully."
]);
