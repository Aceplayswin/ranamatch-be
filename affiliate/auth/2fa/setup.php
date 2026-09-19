<?php
/**
 * Setup Affiliate 2FA Endpoint
 * Endpoint: GET|POST /affiliate/auth/2fa/setup
 * Generates Base32 secret key and QR code for scanning in Google Authenticator.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../../config/auth_middleware.php';
require_once __DIR__ . '/../../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate session required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

$stmt = mysqli_prepare($conn, "SELECT id, email, full_name, affiliate_code, two_factor_secret, two_factor_enabled FROM affiliates WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$aff) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Affiliate not found."]);
    exit;
}

$secret = $aff['two_factor_secret'];
if (empty($secret)) {
    $secret = GoogleAuthenticatorService::generateSecret(16);
    $upd = mysqli_prepare($conn, "UPDATE affiliates SET two_factor_secret = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $secret, $affiliateId);
    mysqli_stmt_execute($upd);
}

$accountLabel = $aff['email'] ?: $aff['affiliate_code'];
$qrData = GoogleAuthenticatorService::getQrCodeUrl($accountLabel, $secret, 'Velplay Affiliate');

echo json_encode([
    "status" => "success",
    "data" => [
        "enabled" => (bool)$aff['two_factor_enabled'],
        "secret" => $secret,
        "qr_code_url" => $qrData['qr_code_url'],
        "otpauth_url" => $qrData['otpauth_url'],
        "account_label" => $accountLabel
    ]
]);
