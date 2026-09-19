<?php
/**
 * Agent Downlines List Endpoint
 * Endpoint: GET /agent/downlines
 * Protected by JWT Auth Middleware. Returns list of direct sub-agents.
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/create.php';
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

$sql = "SELECT a.id, a.agent_code, a.username, a.name, a.email, a.rank_level, a.status, 
               a.partnership_pct, a.turnover_commission_pct, a.current_credit, a.exposed_credit, a.created_at,
               (SELECT COUNT(*) FROM tblusersdata u WHERE u.tbl_joined_under = a.agent_code OR u.tbl_joined_under = a.username OR u.tbl_joined_under = CAST(a.id AS CHAR)) AS player_count
        FROM agents a
        JOIN agent_tree t ON a.id = t.descendant_id
        WHERE t.ancestor_id = ? AND t.depth = 1
        ORDER BY a.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$downlines = mysqli_fetch_all($res, MYSQLI_ASSOC);

// Format numeric types
$formatted = array_map(function($row) {
    return [
        "id" => (int)$row['id'],
        "agent_code" => $row['agent_code'],
        "username" => $row['username'],
        "name" => $row['name'],
        "email" => $row['email'],
        "rank_level" => $row['rank_level'],
        "status" => $row['status'],
        "partnership_pct" => (float)$row['partnership_pct'],
        "turnover_commission_pct" => (float)$row['turnover_commission_pct'],
        "current_credit" => (float)$row['current_credit'],
        "exposed_credit" => (float)$row['exposed_credit'],
        "available_credit" => max(0, (float)$row['current_credit'] - (float)$row['exposed_credit']),
        "player_count" => (int)$row['player_count'],
        "created_at" => $row['created_at']
    ];
}, $downlines);

echo json_encode([
    "status" => "success",
    "count" => count($formatted),
    "data" => $formatted
]);
