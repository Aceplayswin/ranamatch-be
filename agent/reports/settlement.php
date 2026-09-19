<?php
/**
 * Agent Settlement Report Endpoint
 * Endpoint: GET /agent/reports/settlement
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

$sql = "SELECT id, transaction_type, amount, balance_before, balance_after, remark, created_at
        FROM agent_credit_ledger
        WHERE agent_id = ? AND transaction_type IN ('PNL_SETTLEMENT', 'TURNOVER_COMMISSION')
        ORDER BY id DESC LIMIT 50";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
} else {
    $rows = [];
}

$data = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "type" => str_replace('_', ' ', $r['transaction_type']),
        "amount" => (float)$r['amount'],
        "balance_before" => (float)$r['balance_before'],
        "balance_after" => (float)$r['balance_after'],
        "remark" => $r['remark'] ?: 'Settlement entry',
        "date" => $r['created_at'] ? date('Y-m-d H:i', strtotime($r['created_at'])) : date('Y-m-d H:i')
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
