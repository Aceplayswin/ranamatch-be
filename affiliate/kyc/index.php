<?php
/**
 * Affiliate KYC Documents Endpoint
 * Endpoint: GET /api/v1/affiliate/kyc or /affiliate/kyc
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// 1. Fetch affiliate kyc_status
$aStmt = mysqli_prepare($conn, "SELECT kyc_status FROM affiliates WHERE id = ?");
mysqli_stmt_bind_param($aStmt, "i", $affiliateId);
mysqli_stmt_execute($aStmt);
$aRow = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
$kycStatus = $aRow['kyc_status'] ?? 'not_submitted';

// 2. Fetch documents
$dStmt = mysqli_prepare($conn, "SELECT id, document_type, document_name, file_path, status, reviewer_note, created_at 
                                FROM affiliate_kyc_documents 
                                WHERE affiliate_id = ? 
                                ORDER BY created_at DESC");
mysqli_stmt_bind_param($dStmt, "i", $affiliateId);
mysqli_stmt_execute($dStmt);
$dRes = mysqli_stmt_get_result($dStmt);

$documents = [];
while ($doc = mysqli_fetch_assoc($dRes)) {
    $st = strtolower($doc['status']);
    $badge = $st === 'approved' ? 'green' : ($st === 'rejected' ? 'red' : 'yellow');
    $label = $st === 'approved' ? 'Verified' : ($st === 'rejected' ? 'Rejected' : 'Under Review');

    $documents[] = [
        "id" => (int)$doc['id'],
        "title" => $doc['document_name'] ?: ($doc['document_type'] . ' Document'),
        "fileName" => $doc['file_path'] ?: 'document.png',
        "status" => $st,
        "badge" => $badge,
        "label" => $label,
        "created_at" => $doc['created_at'],
        "reviewer_note" => $doc['reviewer_note']
    ];
}

$rejectionReason = '';
foreach ($documents as $doc) {
    if ($doc['status'] === 'rejected' && !empty($doc['reviewer_note'])) {
        $rejectionReason = $doc['reviewer_note'];
        break;
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "kyc_status" => $kycStatus,
        "rejection_reason" => $rejectionReason,
        "documents" => $documents
    ]
]);
