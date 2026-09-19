<?php
/**
 * Affiliate Application Status Check Endpoint
 * Endpoint: GET/POST /api/v1/affiliate/auth/status or /affiliate/auth/status
 * Queries database to check real-time approval status for an email or code.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$email = trim($_GET['email'] ?? '');
if (empty($email)) {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;
    $email = trim($input['email'] ?? $input['affiliate_code'] ?? '');
}

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email address or affiliate code is required."]);
    exit;
}

$sql = "SELECT id, affiliate_code, full_name, email, phone, company_name, website, status, onboarding_completed, tier, deal_type, revshare_pct, cpa_amount, created_at 
        FROM affiliates 
        WHERE email = ? OR affiliate_code = ? 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $email, $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$aff = mysqli_fetch_assoc($res);

if (!$aff) {
    http_response_code(404);
    echo json_encode([
        "status" => "error", 
        "message" => "No affiliate application found with this email."
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => [
        "id" => (int)$aff['id'],
        "email" => $aff['email'],
        "full_name" => $aff['full_name'],
        "phone" => $aff['phone'] ?? '',
        "company_name" => $aff['company_name'] ?? '',
        "website" => $aff['website'] ?? '',
        "affiliate_code" => $aff['affiliate_code'],
        "status" => strtolower($aff['status']),
        "onboarding_completed" => (bool)($aff['onboarding_completed'] ?? 0),
        "tier" => $aff['tier'],
        "deal_type" => $aff['deal_type'],
        "revshare_pct" => (float)$aff['revshare_pct'],
        "created_at" => $aff['created_at']
    ]
]);
