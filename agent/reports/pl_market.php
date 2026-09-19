<?php
/**
 * Agent Market P&L Report Endpoint
 * Endpoint: GET /agent/reports/pl-market
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

$sql = "SELECT pb.market_name AS market,
               SUM(pb.stake) AS total_stake,
               SUM(pb.pnl) AS total_pnl,
               COUNT(pb.id) AS total_bets
        FROM player_bets pb
        WHERE pb.agent_id = ?
        GROUP BY pb.market_name
        ORDER BY total_stake DESC LIMIT 50";

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
        "market" => $r['market'] ?: 'Match Winner',
        "total_stake" => (float)($r['total_stake'] ?? 0.0),
        "total_pnl" => (float)($r['total_pnl'] ?? 0.0),
        "total_bets" => (int)($r['total_bets'] ?? 0)
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
