<?php
/**
 * Admin Agent List API Endpoint
 * Endpoint: GET /admin/agents/api_list.php
 * Returns paginated agent list + top summary stats (active count, total credit, total exposure).
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

// 1. Fetch Summary Stats
$summarySql = "SELECT 
    COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_agents,
    COUNT(CASE WHEN status = 'suspended' THEN 1 END) AS suspended_agents,
    COALESCE(SUM(current_credit), 0) AS total_credit_outstanding,
    COALESCE(SUM(exposed_credit), 0) AS total_exposure
FROM agents";
$sumRes = mysqli_query($conn, $summarySql);
$summary = mysqli_fetch_assoc($sumRes);

// Pending applications count
$appCountRes = mysqli_query($conn, "SELECT COUNT(*) AS pending_apps FROM agent_applications WHERE status = 'pending'");
$appCountRow = mysqli_fetch_assoc($appCountRes);
$summary['pending_applications_count'] = (int)($appCountRow['pending_apps'] ?? 0);

// 2. Build Filtered Query
$whereClause = "WHERE 1=1";
$params = [];
$typesStr = "";

if (!empty($statusFilter) && $statusFilter !== 'ALL') {
    $whereClause .= " AND a.status = ?";
    $params[] = $statusFilter;
    $typesStr .= "s";
}

if (!empty($search)) {
    $whereClause .= " AND (a.name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ? OR a.username LIKE ?)";
    $searchWild = "%$search%";
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $typesStr .= "ssss";
}

// Count total matching records
$countSql = "SELECT COUNT(*) AS total FROM agents a $whereClause";
if (!empty($params)) {
    $cStmt = mysqli_prepare($conn, $countSql);
    mysqli_stmt_bind_param($cStmt, $typesStr, ...$params);
    mysqli_stmt_execute($cStmt);
    $totalRecords = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'];
} else {
    $totalRecords = (int)mysqli_fetch_assoc(mysqli_query($conn, $countSql))['total'];
}

// Fetch paginated agent list
$sql = "SELECT a.id, a.agent_code, a.username, a.name, a.email, a.rank_level, a.status, 
               a.partnership_pct, a.turnover_commission_pct, a.current_credit, a.exposed_credit, a.created_at,
               pa.name AS parent_agent_name, pa.agent_code AS parent_agent_code,
               (SELECT COUNT(*) FROM tblusersdata u WHERE u.tbl_joined_under = a.agent_code OR u.tbl_joined_under = a.username OR u.tbl_joined_under = CAST(a.id AS CHAR)) AS downline_players_count,
               (SELECT COUNT(*) FROM agent_tree t WHERE t.ancestor_id = a.id AND t.depth = 1) AS downline_agents_count
        FROM agents a
        LEFT JOIN agents pa ON a.parent_id = pa.id
        $whereClause
        ORDER BY a.created_at DESC
        LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$typesStr .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$agents = mysqli_fetch_all($res, MYSQLI_ASSOC);

$formattedAgents = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "agent_code" => $r['agent_code'],
        "username" => $r['username'],
        "name" => $r['name'],
        "email" => $r['email'],
        "rank_level" => $r['rank_level'],
        "status" => $r['status'],
        "partnership_pct" => (float)$r['partnership_pct'],
        "turnover_commission_pct" => (float)$r['turnover_commission_pct'],
        "current_credit" => (float)$r['current_credit'],
        "exposed_credit" => (float)$r['exposed_credit'],
        "available_credit" => max(0, (float)$r['current_credit'] - (float)$r['exposed_credit']),
        "parent_agent_name" => $r['parent_agent_name'] ?? 'Platform Root',
        "parent_agent_code" => $r['parent_agent_code'] ?? null,
        "downline_players_count" => (int)$r['downline_players_count'],
        "downline_agents_count" => (int)$r['downline_agents_count'],
        "created_at" => $r['created_at']
    ];
}, $agents);

echo json_encode([
    "status" => "success",
    "summary" => [
        "active_agents" => (int)$summary['active_agents'],
        "suspended_agents" => (int)$summary['suspended_agents'],
        "total_credit_outstanding" => (float)$summary['total_credit_outstanding'],
        "total_exposure" => (float)$summary['total_exposure'],
        "pending_applications_count" => (int)$summary['pending_applications_count']
    ],
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRecords,
        "total_pages" => ceil($totalRecords / $limit)
    ],
    "data" => $formattedAgents
]);
