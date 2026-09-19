<?php
/**
 * Affiliate Payout Methods API Endpoint
 * Endpoint: GET /affiliate/methods  or  POST /affiliate/methods
 * Protected by JWT Auth Middleware. List or save payout methods (Bank, UPI, Crypto).
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// GET: List payout methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, method_type, account_details, is_primary, created_at 
            FROM affiliate_payout_methods 
            WHERE affiliate_id = ? 
            ORDER BY is_primary DESC, id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $affiliateId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $methods = mysqli_fetch_all($res, MYSQLI_ASSOC);

    $formattedMethods = array_map(function($m) {
        return [
            "id" => (int)$m['id'],
            "method_type" => $m['method_type'],
            "details" => json_decode($m['account_details'] ?? '{}', true),
            "is_primary" => (bool)($m['is_primary'] ?? false),
            "created_at" => $m['created_at']
        ];
    }, $methods);

    echo json_encode([
        "status" => "success",
        "count" => count($formattedMethods),
        "data" => $formattedMethods
    ]);
    exit;
}

// POST: Add new payout method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $methodType = trim($input['method_type'] ?? 'bank_transfer');
    $details = $input['account_details'] ?? $input['details'] ?? [];
    $isPrimary = !empty($input['is_primary']) ? 1 : 0;

    if (empty($details)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Account details are required."]);
        exit;
    }

    $detailsJson = is_string($details) ? $details : json_encode($details);

    mysqli_begin_transaction($conn);

    try {
        if ($isPrimary === 1) {
            // Unset previous primary method
            $unStmt = mysqli_prepare($conn, "UPDATE affiliate_payout_methods SET is_primary = 0 WHERE affiliate_id = ?");
            mysqli_stmt_bind_param($unStmt, "i", $affiliateId);
            mysqli_stmt_execute($unStmt);
        }

        $ins = mysqli_prepare($conn, 
            "INSERT INTO affiliate_payout_methods (affiliate_id, method_type, account_details, is_primary) 
             VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "issi", $affiliateId, $methodType, $detailsJson, $isPrimary);
        mysqli_stmt_execute($ins);

        $newMethodId = mysqli_insert_id($conn);

        mysqli_commit($conn);

        echo json_encode([
            "status" => "success",
            "message" => "Payout method saved successfully",
            "data" => [
                "id" => $newMethodId,
                "method_type" => $methodType,
                "details" => json_decode($detailsJson, true),
                "is_primary" => (bool)$isPrimary
            ]
        ]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to save payout method: " . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
