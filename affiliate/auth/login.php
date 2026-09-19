<?php
/**
 * Affiliate Login Endpoint
 * Endpoint: POST /affiliate/auth/login
 * Validates affiliate credentials and returns signed JWT token.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/JWTHandler.php';

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

$email = trim($input['email'] ?? $input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and Password are required."]);
    exit;
}

$sql = "SELECT id, affiliate_code, email, password_hash, full_name, phone, company_name, website, status, onboarding_completed, tier, deal_type, revshare_pct, cpa_amount, two_factor_enabled 
        FROM affiliates 
        WHERE LOWER(TRIM(email)) = LOWER(?) 
           OR UPPER(TRIM(affiliate_code)) = UPPER(?) 
           OR TRIM(phone) = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sss", $email, $email, $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$aff = mysqli_fetch_assoc($res);

if (!$aff) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
    exit;
}

if (!password_verify($password, $aff['password_hash'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
    exit;
}

if ($aff['status'] !== 'approved' && $aff['status'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Your affiliate account is currently " . ucfirst($aff['status']) . ". Please await admin approval."
    ]);
    exit;
}

$jwtHandler = new JWTHandler();

// Check 2FA
if ((int)($aff['two_factor_enabled'] ?? 0) === 1) {
    $preAuthPayload = [
        'user_id' => (int)$aff['id'],
        'user_type' => 'affiliate',
        'action' => '2fa_required'
    ];
    $preAuthToken = $jwtHandler->encode($preAuthPayload);

    echo json_encode([
        "status" => "2fa_required",
        "message" => "Two-factor authentication code required.",
        "pre_auth_token" => $preAuthToken
    ]);
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
    "message" => "Login successful",
    "token" => $token,
    "affiliate" => [
        "id" => (int)$aff['id'],
        "affiliate_code" => $aff['affiliate_code'],
        "name" => $aff['full_name'] ?? $aff['name'],
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
