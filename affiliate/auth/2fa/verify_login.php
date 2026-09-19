<?php
/**
 * Verify Affiliate Login 2FA Endpoint
 * Endpoint: POST /affiliate/auth/2fa/verify_login
 * Verifies 6-digit TOTP code during login when two_factor_enabled = 1.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../../config/JWTHandler.php';
require_once __DIR__ . '/../../../services/GoogleAuthenticatorService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$preAuthToken = trim($data['pre_auth_token'] ?? '');
$code = trim($data['code'] ?? $data['otp'] ?? '');

if (empty($preAuthToken) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "pre_auth_token and 6-digit verification code are required."]);
    exit;
}

$jwtHandler = new JWTHandler();
$decoded = $jwtHandler->decode($preAuthToken);

if (!$decoded || ($decoded['action'] ?? '') !== '2fa_required') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid or expired pre-authentication token. Please log in again."]);
    exit;
}

$affiliateId = (int)$decoded['user_id'];

$stmt = mysqli_prepare($conn, "SELECT id, affiliate_code, email, full_name, phone, company_name, website, status, onboarding_completed, tier, deal_type, revshare_pct, cpa_amount, two_factor_secret, two_factor_enabled FROM affiliates WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$aff || (int)$aff['two_factor_enabled'] !== 1 || empty($aff['two_factor_secret'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "2FA is not active for this account."]);
    exit;
}

if (!GoogleAuthenticatorService::verifyCode($aff['two_factor_secret'], $code)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Google Authenticator 6-digit code. Please try again."]);
    exit;
}

// Mint 24-Hour JWT Token
$tokenPayload = [
    'user_id' => (int)$aff['id'],
    'user_type' => 'affiliate',
    'user_code' => $aff['affiliate_code'],
    'tier' => $aff['tier']
];

$token = $jwtHandler->encode($tokenPayload);

echo json_encode([
    "status" => "success",
    "message" => "2FA verification successful. Login granted.",
    "token" => $token,
    "affiliate" => [
        "id" => (int)$aff['id'],
        "affiliate_code" => $aff['affiliate_code'],
        "name" => $aff['full_name'],
        "full_name" => $aff['full_name'],
        "email" => $aff['email'],
        "phone" => $aff['phone'] ?? '',
        "company_name" => $aff['company_name'] ?? '',
        "website" => $aff['website'] ?? '',
        "status" => $aff['status'],
        "onboarding_completed" => (bool)($aff['onboarding_completed'] ?? 0),
        "tier" => $aff['tier'],
        "deal_type" => $aff['deal_type'],
        "revshare_pct" => (float)$aff['revshare_pct'],
        "cpa_amount" => (float)$aff['cpa_amount']
    ]
]);
