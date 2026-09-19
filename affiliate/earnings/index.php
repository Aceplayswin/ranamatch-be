<?php
/**
 * Affiliate Commission Ledger API Endpoint
 * Endpoint: GET /affiliate/earnings
 * Protected by JWT Auth Middleware. Returns paginated commission ledger.
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

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$type = trim($_GET['type'] ?? '');

$where = "WHERE l.affiliate_id = ?";
$params = [$affiliateId];
$typesStr = "i";

if (!empty($type) && $type !== 'ALL') {
    $where .= " AND l.entry_type = ?";
    $params[] = $type;
    $typesStr .= "s";
}

// Count total matching entries
$countSql = "SELECT COUNT(*) AS total FROM affiliate_commission_ledger l $where";
$cStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($cStmt, $typesStr, ...$params);
mysqli_stmt_execute($cStmt);
$totalRecords = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'];

// Fetch paginated entries
$sql = "SELECT l.id, l.entry_type, l.amount AS gross_amount, l.status, l.created_at, l.base_kind, l.base_amount, l.rate
        FROM affiliate_commission_ledger l
        $where
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$typesStr .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$formattedRows = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "entry_type" => $r['entry_type'],
        "gross_amount" => (float)$r['gross_amount'],
        "status" => $r['status'],
        "period_start" => $r['created_at'],
        "period_end" => $r['created_at'],
        "created_at" => $r['created_at'],
        "processed_at" => $r['created_at']
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
