<?php
/**
 * Affiliate Payout History API Endpoint
 * Endpoint: GET /affiliate/payouts/history
 * Protected by JWT Auth Middleware. Returns list of withdrawal requests and status.
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

$sql = "SELECT id, amount, status, payout_method_type, transaction_reference, rejection_reason, requested_at, paid_at 
        FROM affiliate_payouts 
        WHERE affiliate_id = ? 
        ORDER BY requested_at DESC, id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$payouts = mysqli_fetch_all($res, MYSQLI_ASSOC);

$formattedPayouts = array_map(function($p) {
    return [
        "id" => (int)$p['id'],
        "amount" => (float)$p['amount'],
        "status" => $p['status'],
        "payout_method_type" => $p['payout_method_type'],
        "transaction_reference" => $p['transaction_reference'],
        "rejection_reason" => $p['rejection_reason'],
        "requested_at" => $p['requested_at'],
        "paid_at" => $p['paid_at']
    ];
}, $payouts);

$settRes = mysqli_query($conn, "SELECT affiliate_minimum_payout, affiliate_payout_hold_days FROM program_settings WHERE id = 1");
$sett = mysqli_fetch_assoc($settRes);
$minPayout = (float)($sett['affiliate_minimum_payout'] ?? 1000.00);
$holdDays = (int)($sett['affiliate_payout_hold_days'] ?? 7);

echo json_encode([
    "status" => "success",
    "minimum_payout" => $minPayout,
    "hold_days" => $holdDays,
    "count" => count($formattedPayouts),
    "data" => $formattedPayouts
]);
