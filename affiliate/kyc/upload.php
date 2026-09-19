<?php
/**
 * Affiliate KYC Document Upload Endpoint
 * Endpoint: POST /api/v1/affiliate/kyc/upload or /affiliate/kyc/upload
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

$fileName = 'document.png';
$docType = 'Identity Document';
$docTitle = 'Identity Document';

// If a multipart file is uploaded
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../uploads/affiliate_kyc/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $origName = basename($_FILES['file']['name']);
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $safeName = 'kyc_' . $affiliateId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        $fileName = $origName;
        $docTitle = pathinfo($origName, PATHINFO_FILENAME);
        $docTitle = ucwords(str_replace(['_', '-'], ' ', $docTitle)) . ' Document';
    }
} else {
    // Check JSON body
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    if (!empty($input['fileName'])) {
        $fileName = trim($input['fileName']);
        $docTitle = trim($input['title'] ?? $fileName);
    }
}

// Insert into affiliate_kyc_documents
$stmt = mysqli_prepare($conn, "INSERT INTO affiliate_kyc_documents (affiliate_id, document_type, document_name, file_path, status, created_at) 
                               VALUES (?, ?, ?, ?, 'pending', NOW())");
mysqli_stmt_bind_param($stmt, "isss", $affiliateId, $docType, $docTitle, $fileName);
mysqli_stmt_execute($stmt);
$newDocId = mysqli_insert_id($conn);

// Update affiliate kyc_status to pending
mysqli_query($conn, "UPDATE affiliates SET kyc_status = 'pending' WHERE id = $affiliateId");

echo json_encode([
    "status" => "success",
    "message" => "Document uploaded successfully and queued for admin review.",
    "data" => [
        "id" => $newDocId,
        "title" => $docTitle,
        "fileName" => $fileName,
        "status" => "pending",
        "badge" => "yellow",
        "label" => "Under Review"
    ]
]);
