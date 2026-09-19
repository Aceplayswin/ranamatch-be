<?php
/**
 * Affiliate Self-Registration Endpoint
 * Endpoint: POST /affiliate/auth/register
 * Allows new performance marketers to register. Inserts account in 'pending' status.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

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

$fullName = trim($input['full_name'] ?? $input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$company = trim($input['company_name'] ?? $input['company'] ?? '');
$phone = trim($input['phone'] ?? '');
$website = trim($input['website'] ?? '');
$parentCode = trim($input['parent_code'] ?? $input['referral_code'] ?? $input['ref'] ?? $input['ref_code'] ?? $input['sponsor_code'] ?? '');

if (empty($fullName) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Full Name, Email, and Password are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email address format."]);
    exit;
}

// 1. Check if email already registered
$chk = mysqli_prepare($conn, "SELECT id FROM affiliates WHERE email = ?");
mysqli_stmt_bind_param($chk, "s", $email);
mysqli_stmt_execute($chk);
if (mysqli_num_rows(mysqli_stmt_get_result($chk)) > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "An affiliate account with this email already exists."]);
    exit;
}

// 2. Resolve parent affiliate ID if sub-affiliate referral code supplied
$parentId = null;
if (!empty($parentCode)) {
    $cleanParentCode = strtoupper(trim($parentCode));
    $pStmt = mysqli_prepare($conn, "SELECT id, affiliate_code FROM affiliates WHERE UPPER(affiliate_code) = ? AND status IN ('active', 'approved') LIMIT 1");
    mysqli_stmt_bind_param($pStmt, "s", $cleanParentCode);
    mysqli_stmt_execute($pStmt);
    $pRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));
    if ($pRow) {
        $parentId = (int)$pRow['id'];
    }
}

// 3. Fetch program defaults from program_settings
$settRes = mysqli_query($conn, "SELECT affiliate_default_tier, affiliate_default_revshare_pct, affiliate_default_cpa_amount FROM program_settings WHERE id = 1");
$sett = mysqli_fetch_assoc($settRes);

$tier = $sett['affiliate_default_tier'] ?? 'bronze';
$revsharePct = (float)($sett['affiliate_default_revshare_pct'] ?? 35.00);
$cpaAmount = (float)($sett['affiliate_default_cpa_amount'] ?? 50.00);
$dealType = 'revenue_share';

// 4. Generate unique affiliate code and hash password
$affCode = 'AFF-' . strtoupper(substr(md5(uniqid()), 0, 6));
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$parentSqlVal = ($parentId && $parentId > 0) ? (int)$parentId : "NULL";
$sql = "INSERT INTO affiliates (affiliate_code, full_name, email, password_hash, company_name, phone, website, parent_id, status, tier, deal_type, revshare_pct, cpa_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, $parentSqlVal, 'pending', ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssssssdd", 
    $affCode, $fullName, $email, $passwordHash, 
    $company, $phone, $website, 
    $tier, $dealType, $revsharePct, $cpaAmount
);

if (mysqli_stmt_execute($stmt)) {
    $newAffId = mysqli_insert_id($conn);

    $audit = mysqli_prepare($conn, "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_after, ip_address) VALUES (0, 'AFFILIATE_APPLICATION', 'application.submitted', ?, 'affiliate', ?, ?)");
    $auditPayload = json_encode([
        'email' => $email,
        'affiliate_code' => $affCode,
        'parent_id' => $parentId
    ]);
    $auditIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    mysqli_stmt_bind_param($audit, "iss", $newAffId, $auditPayload, $auditIp);
    mysqli_stmt_execute($audit);
    
    echo json_encode([
        "status" => "success",
        "message" => "Registration successful! Your affiliate account is pending admin approval.",
        "data" => [
            "id" => $newAffId,
            "affiliate_code" => $affCode,
            "full_name" => $fullName,
            "email" => $email,
            "status" => "pending",
            "tier" => $tier,
            "revshare_pct" => $revsharePct
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration failed: " . mysqli_error($conn)]);
}
