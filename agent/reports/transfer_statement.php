<?php
/**
 * Agent Transfer Statement Endpoint
 * Endpoint: GET /agent/reports/transfer-statement
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
        WHERE agent_id = ? AND transaction_type IN ('TRANSFER_OUT', 'TRANSFER_IN', 'INJECTION', 'CLAWBACK', 'CREDIT_TRANSFER', 'CHIP_DEPOSIT', 'CHIP_WITHDRAWAL')
        ORDER BY id DESC LIMIT 100";

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
    $rawAmt = (float)$r['amount'];
    $txType = $r['transaction_type'];
    $balBefore = isset($r['balance_before']) ? (float)$r['balance_before'] : null;
    $balAfter = isset($r['balance_after']) ? (float)$r['balance_after'] : null;

    $isOutflow = ($txType === 'TRANSFER_OUT' || $txType === 'CLAWBACK' || ($balBefore !== null && $balAfter !== null && $balAfter < $balBefore));
    $signedAmt = $isOutflow ? -abs($rawAmt) : abs($rawAmt);

    $typeLabel = match($txType) {
        'TRANSFER_OUT' => 'Downline Transfer Out',
        'TRANSFER_IN' => 'Downline Transfer In',
        'INJECTION' => 'Float Deposit',
        'CLAWBACK' => 'Chip Recall',
        default => str_replace('_', ' ', $txType)
    };

    return [
        "id" => (int)$r['id'],
        "type" => $typeLabel,
        "amount" => $signedAmt,
        "balance_before" => (float)($r['balance_before'] ?? 0),
        "balance_after" => (float)($r['balance_after'] ?? 0),
        "remark" => $r['remark'] ?: 'Transfer statement entry',
        "date" => $r['created_at'] ? date('Y-m-d H:i', strtotime($r['created_at'])) : date('Y-m-d H:i')
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
