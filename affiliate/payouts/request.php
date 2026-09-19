<?php
/**
 * Affiliate Payout Request Endpoint
 * Endpoint: POST /affiliate/payouts/request
 * Protected by JWT Auth Middleware. Submits withdrawal request via PayoutService.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/PayoutService.php';

header('Content-Type: application/json; charset=utf-8');

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

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$amount = (float)($input['amount'] ?? 0.00);
$methodId = (int)($input['method_id'] ?? $input['payout_method_id'] ?? 0);
$methodType = trim($input['payout_method_type'] ?? $input['method_type'] ?? 'bank');

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid withdrawal amount is required."]);
    exit;
}

// Auto-resolve or create payout method ID if missing
if ($methodId <= 0) {
    $pmStmt = mysqli_prepare($conn, "SELECT id FROM affiliate_payout_methods WHERE affiliate_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
    mysqli_stmt_bind_param($pmStmt, "i", $affiliateId);
    mysqli_stmt_execute($pmStmt);
    $pmRes = mysqli_stmt_get_result($pmStmt);
    $pmRow = mysqli_fetch_assoc($pmRes);

    if ($pmRow) {
        $methodId = (int)$pmRow['id'];
    } else {
        // Auto-create payout method record
        $insPm = mysqli_prepare($conn, "INSERT INTO affiliate_payout_methods (affiliate_id, method_type, account_details, is_primary) VALUES (?, ?, '{}', 1)");
        mysqli_stmt_bind_param($insPm, "is", $affiliateId, $methodType);
        mysqli_stmt_execute($insPm);
        $methodId = mysqli_insert_id($conn);
    }
}

try {
    $payoutService = new PayoutService($conn);
    $result = $payoutService->requestPayout($affiliateId, $amount, $methodId);

    if ($result['success']) {
        echo json_encode([
            "status" => "success",
            "message" => $result['message'],
            "data" => [
                "payout_id" => $result['payout_id'],
                "amount" => $result['amount'],
                "remaining_balance" => $result['remaining_balance']
            ]
        ]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $result['message']]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Payout request failed: " . $e->getMessage()]);
}
