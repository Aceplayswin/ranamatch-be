<?php
/**
 * Admin Affiliate KYC Documents API Endpoint
 * Endpoint: GET/POST /admin/affiliates/api_kyc.php
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';
require_once __DIR__ . '/../../services/NotificationHelper.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = strtolower(trim($input['action'] ?? ''));
    $docId = (int)($input['document_id'] ?? $input['doc_id'] ?? 0);
    $affId = (int)($input['affiliate_id'] ?? 0);
    $note = trim($input['reviewer_note'] ?? $input['note'] ?? '');

    if (!$docId && !$affId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Document ID or Affiliate ID required."]);
        exit;
    }

    if ($action === 'approve') {
        if ($docId) {
            $stmt = mysqli_prepare($conn, "UPDATE affiliate_kyc_documents SET status = 'approved', reviewer_note = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $note, $docId);
            mysqli_stmt_execute($stmt);

            // Fetch affiliate id from doc
            $q = mysqli_query($conn, "SELECT affiliate_id FROM affiliate_kyc_documents WHERE id = $docId LIMIT 1");
            if ($r = mysqli_fetch_assoc($q)) {
                $affId = (int)$r['affiliate_id'];
            }
        }

        if ($affId) {
            mysqli_query($conn, "UPDATE affiliates SET kyc_status = 'verified' WHERE id = $affId");
            sendAffiliateNotification(
                $conn,
                $affId,
                'kyc',
                'KYC Documents Verified',
                'Your identity verification documents have been reviewed and approved by compliance.',
                '/settings'
            );
        }

        echo json_encode([
            "status" => "success",
            "message" => "KYC document approved successfully! Affiliate KYC verified.",
            "kyc_status" => "verified"
        ]);
        exit;
    }

    if ($action === 'reject') {
        if ($docId) {
            $stmt = mysqli_prepare($conn, "UPDATE affiliate_kyc_documents SET status = 'rejected', reviewer_note = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $note, $docId);
            mysqli_stmt_execute($stmt);

            $q = mysqli_query($conn, "SELECT affiliate_id FROM affiliate_kyc_documents WHERE id = $docId LIMIT 1");
            if ($r = mysqli_fetch_assoc($q)) {
                $affId = (int)$r['affiliate_id'];
            }
        }

        if ($affId) {
            mysqli_query($conn, "UPDATE affiliates SET kyc_status = 'rejected' WHERE id = $affId");
            $reasonTxt = !empty($note) ? " Reason: {$note}" : " Please ensure all documents are clearly legible and valid.";
            sendAffiliateNotification(
                $conn,
                $affId,
                'kyc',
                'KYC Documents Rejected',
                "Your identity verification was rejected.{$reasonTxt}",
                '/settings'
            );
        }

        echo json_encode([
            "status" => "success",
            "message" => "KYC document rejected.",
            "kyc_status" => "rejected"
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
    exit;
}

// GET: Fetch documents
$affId = (int)($_GET['affiliate_id'] ?? 0);
if ($affId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM affiliate_kyc_documents WHERE affiliate_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $affId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $docs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $docs[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $docs]);
    exit;
}

// Fetch all pending documents
$res = mysqli_query($conn, "SELECT d.*, a.full_name, a.email, a.affiliate_code 
                            FROM affiliate_kyc_documents d 
                            JOIN affiliates a ON d.affiliate_id = a.id 
                            ORDER BY d.created_at DESC LIMIT 50");
$docs = [];
while ($row = mysqli_fetch_assoc($res)) {
    $docs[] = $row;
}
echo json_encode(["status" => "success", "data" => $docs]);
