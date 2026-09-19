<?php
/**
 * Affiliate Onboarding Complete Endpoint
 * Endpoint: POST /api/v1/affiliate/onboarding/complete or /affiliate/onboarding/complete
 * Marks onboarding_completed = 1 for the authenticated affiliate and saves onboarding bank/company details.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

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

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// Read payload
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

// 1. Mark onboarding as completed and update company/profile if provided
$companyName = trim($input['company_name'] ?? $input['company'] ?? '');
$website = trim($input['website'] ?? '');
$phone = trim($input['phone'] ?? '');
$fullName = trim($input['full_name'] ?? $input['name'] ?? '');

$updateParts = ["onboarding_completed = 1"];
$params = [];
$types = "";

if (!empty($companyName)) {
    $updateParts[] = "company_name = ?";
    $params[] = $companyName;
    $types .= "s";
}
if (!empty($website)) {
    $updateParts[] = "website = ?";
    $params[] = $website;
    $types .= "s";
}
if (!empty($phone)) {
    $updateParts[] = "phone = ?";
    $params[] = $phone;
    $types .= "s";
}
if (!empty($fullName)) {
    $updateParts[] = "full_name = ?";
    $params[] = $fullName;
    $types .= "s";
}

$params[] = $affiliateId;
$types .= "i";

$sql = "UPDATE affiliates SET " . implode(", ", $updateParts) . " WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
}

// 2. Save Bank / Payout Method if provided in onboarding payload
$bankDetails = $input['bank_details'] ?? $input['account_details'] ?? $input['payout_details'] ?? $input['bank'] ?? [];
$methodType = trim($input['method_type'] ?? $input['payout_method'] ?? $input['payment_method'] ?? '');

if (empty($methodType)) {
    if (!empty($input['upi_id']) || !empty($bankDetails['upi_id'])) {
        $methodType = 'UPI';
    } elseif (!empty($input['crypto_wallet']) || !empty($input['crypto_address']) || !empty($bankDetails['wallet_address'])) {
        $methodType = 'crypto';
    } else {
        $methodType = 'bank_transfer';
    }
}

if (!in_array($methodType, ['bank_transfer', 'UPI', 'crypto', 'ewallet'])) {
    $methodType = 'bank_transfer';
}

$detailsToSave = [];

if (is_array($bankDetails)) {
    $detailsToSave = $bankDetails;
} elseif (is_string($bankDetails) && !empty($bankDetails)) {
    $decoded = json_decode($bankDetails, true);
    $detailsToSave = is_array($decoded) ? $decoded : ["raw" => $bankDetails];
}

// Check top-level direct fields
if (!empty($input['bank_name'])) $detailsToSave['bank_name'] = trim($input['bank_name']);
if (!empty($input['account_number'])) $detailsToSave['account_number'] = trim($input['account_number']);
if (!empty($input['account_name'])) $detailsToSave['account_name'] = trim($input['account_name']);
if (!empty($input['account_holder'])) $detailsToSave['account_name'] = trim($input['account_holder']);
if (!empty($input['holder_name'])) $detailsToSave['account_name'] = trim($input['holder_name']);
if (!empty($input['ifsc_code'])) $detailsToSave['ifsc_code'] = trim($input['ifsc_code']);
if (!empty($input['ifsc'])) $detailsToSave['ifsc_code'] = trim($input['ifsc']);
if (!empty($input['branch_name'])) $detailsToSave['branch_name'] = trim($input['branch_name']);
if (!empty($input['account_type'])) $detailsToSave['account_type'] = trim($input['account_type']);
if (!empty($input['swift_code'])) $detailsToSave['swift_code'] = trim($input['swift_code']);
if (!empty($input['iban'])) $detailsToSave['iban'] = trim($input['iban']);
if (!empty($input['upi_id'])) $detailsToSave['upi_id'] = trim($input['upi_id']);
if (!empty($input['crypto_address'])) $detailsToSave['crypto_address'] = trim($input['crypto_address']);
if (!empty($input['crypto_network'])) $detailsToSave['network'] = trim($input['crypto_network']);

if (!empty($detailsToSave)) {
    $jsonDetails = json_encode($detailsToSave);
    
    // Check if affiliate already has payout method
    $checkQ = mysqli_query($conn, "SELECT id FROM affiliate_payout_methods WHERE affiliate_id = $affiliateId LIMIT 1");
    if ($existing = mysqli_fetch_assoc($checkQ)) {
        $pId = (int)$existing['id'];
        $uStmt = mysqli_prepare($conn, "UPDATE affiliate_payout_methods SET method_type = ?, account_details = ?, is_primary = 1 WHERE id = ?");
        mysqli_stmt_bind_param($uStmt, "ssi", $methodType, $jsonDetails, $pId);
        mysqli_stmt_execute($uStmt);
    } else {
        $iStmt = mysqli_prepare($conn, "INSERT INTO affiliate_payout_methods (affiliate_id, method_type, account_details, is_primary) VALUES (?, ?, ?, 1)");
        mysqli_stmt_bind_param($iStmt, "iss", $affiliateId, $methodType, $jsonDetails);
        mysqli_stmt_execute($iStmt);
    }
}

echo json_encode([
    "status" => "success",
    "message" => "Onboarding completed and payment details saved successfully!",
    "data" => [
        "affiliate_id" => $affiliateId,
        "onboarding_completed" => true,
        "payout_saved" => !empty($detailsToSave)
    ]
]);
