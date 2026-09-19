<?php
/**
 * Agent Payout History Endpoint
 * Endpoint: GET /agent/payouts/history.php
 * Protected by JWT Auth Middleware. Returns payout request history.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

$sql = "SELECT id, amount, pl_before, pl_after, period, note, status, payout_method, transaction_ref, paid_at, created_at 
        FROM agent_settlements 
        WHERE agent_id = ? 
        ORDER BY id DESC LIMIT 50";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$data = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "amount" => (float)$r['amount'],
        "pl_before" => (float)$r['pl_before'],
        "pl_after" => (float)$r['pl_after'],
        "period" => $r['period'],
        "status" => $r['status'] ?: 'approved',
        "payout_method" => $r['payout_method'] ?: 'bank',
        "transaction_ref" => $r['transaction_ref'] ?? '',
        "note" => $r['note'] ?? '',
        "paid_at" => $r['paid_at'] ? date('Y-m-d H:i', strtotime($r['paid_at'])) : null,
        "created_at" => $r['created_at'] ? date('Y-m-d H:i', strtotime($r['created_at'])) : date('Y-m-d H:i')
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
