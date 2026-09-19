<?php
/**
 * Agent Exposure Report Endpoint
 * Endpoint: GET /agent/reports/exposure
 * Protected by JWT Auth Middleware. Returns live risk exposure breakdown across downlines.
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

// 1. Fetch agent's own exposure metrics
$agentStmt = mysqli_prepare($conn, "SELECT agent_code, username, name, current_credit, exposed_credit FROM agents WHERE id = ?");
mysqli_stmt_bind_param($agentStmt, "i", $agentId);
mysqli_stmt_execute($agentStmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($agentStmt));

$totalExposed = (float)$agent['exposed_credit'];

// 2. Fetch exposure breakdown by direct downline agents
$downlineSql = "SELECT a.id, a.agent_code, a.username, a.name, a.rank_level, a.current_credit, a.exposed_credit
                FROM agents a
                JOIN agent_tree t ON a.id = t.descendant_id
                WHERE t.ancestor_id = ? AND t.depth = 1
                ORDER BY a.exposed_credit DESC";

$dStmt = mysqli_prepare($conn, $downlineSql);
mysqli_stmt_bind_param($dStmt, "i", $agentId);
mysqli_stmt_execute($dStmt);
$dRes = mysqli_stmt_get_result($dStmt);
$downlineExposures = mysqli_fetch_all($dRes, MYSQLI_ASSOC);

$formattedDownlines = array_map(function($d) {
    return [
        "id" => (int)$d['id'],
        "agent_code" => $d['agent_code'],
        "username" => $d['username'],
        "name" => $d['name'],
        "rank_level" => $d['rank_level'],
        "current_credit" => (float)$d['current_credit'],
        "exposed_credit" => (float)$d['exposed_credit']
    ];
}, $downlineExposures);

// 3. Fetch active open pending bets for players under this agent
$openSportsSql = "SELECT sb.id, sb.user_id, sb.bet_amount, sb.status, sb.created_at, u.tbl_full_name AS player_name
                  FROM sports_bets sb
                  JOIN tblusersdata u ON u.id = sb.user_id OR u.tbl_uniq_id = sb.user_id
                  WHERE (u.tbl_joined_under = ? OR u.tbl_joined_under = ?) AND sb.status = 'pending'
                  ORDER BY sb.created_at DESC LIMIT 50";

$spStmt = mysqli_prepare($conn, $openSportsSql);
mysqli_stmt_bind_param($spStmt, "ss", $agent['agent_code'], $agent['username']);
mysqli_stmt_execute($spStmt);
$spRes = mysqli_stmt_get_result($spStmt);
$openBets = mysqli_fetch_all($spRes, MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "summary" => [
        "total_exposed_credit" => $totalExposed,
        "total_current_credit" => (float)$agent['current_credit'],
        "available_credit" => max(0, (float)$agent['current_credit'] - $totalExposed)
    ],
    "downline_exposures" => $formattedDownlines,
    "active_open_bets" => $openBets
]);
