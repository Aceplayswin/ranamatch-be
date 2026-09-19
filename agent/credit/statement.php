<?php
/**
 * Agent Credit Statement Endpoint
 * Endpoint: GET /agent/credit/statement
 * Protected by JWT Auth Middleware. Returns paginated credit ledger statement.
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

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$type = trim($_GET['type'] ?? '');

// Build query
$where = "WHERE l.agent_id = ?";
$params = [$agentId];
$typesStr = "i";

if (!empty($type) && $type !== 'ALL') {
    $where .= " AND l.transaction_type = ?";
    $params[] = $type;
    $typesStr .= "s";
}

// 1. Get total records count
$countSql = "SELECT COUNT(*) AS total FROM agent_credit_ledger l $where";
$cStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($cStmt, $typesStr, ...$params);
mysqli_stmt_execute($cStmt);
$cRes = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt));
$totalRecords = (int)$cRes['total'];

// 2. Fetch paginated ledger rows
$sql = "SELECT l.id, l.transaction_type, l.amount, l.balance_before, l.balance_after, l.remark, l.created_at,
               a.agent_code AS counterparty_code, a.name AS counterparty_name
        FROM agent_credit_ledger l
        LEFT JOIN agents a ON a.id = l.transferring_agent_id
        $where
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$typesStr .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

$formattedRows = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "transaction_type" => $r['transaction_type'],
        "amount" => (float)$r['amount'],
        "balance_before" => (float)$r['balance_before'],
        "balance_after" => (float)$r['balance_after'],
        "remark" => $r['remark'],
        "counterparty_code" => $r['counterparty_code'],
        "counterparty_name" => $r['counterparty_name'],
        "created_at" => $r['created_at']
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRecords,
        "total_pages" => ceil($totalRecords / $limit)
    ],
    "data" => $formattedRows
]);
