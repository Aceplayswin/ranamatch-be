<?php
/**
 * Agent Bet List Report Endpoint
 * Endpoint: GET /agent/reports/bet-list
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

$sql = "SELECT pb.id, pb.created_at AS placed, 
               COALESCE(u.tbl_user_name, u.tbl_full_name, CONCAT('Player #', pb.user_id)) AS player, 
               pb.event_name AS event, pb.market_name AS market, pb.selection_name AS selection, 
               pb.side, pb.odds, pb.stake, pb.liability, pb.pnl, pb.status
        FROM player_bets pb
        LEFT JOIN tblusersdata u ON (pb.user_id = u.id OR pb.user_id = u.tbl_uniq_id)
        WHERE pb.agent_id = ?
        ORDER BY pb.id DESC LIMIT 100";

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
        "placed" => $r['placed'] ? date('Y-m-d H:i', strtotime($r['placed'])) : date('Y-m-d H:i'),
        "player" => $r['player'] ?? 'User #'.$r['id'],
        "event" => $r['event'] ?? 'Cricket Fixture',
        "market" => $r['market'] ?? 'Match Winner',
        "selection" => $r['selection'] ?? 'Team A',
        "side" => ucfirst($r['side'] ?? 'Back'),
        "odds" => (float)($r['odds'] ?? 1.95),
        "stake" => (float)($r['stake'] ?? 0.0),
        "liability" => (float)($r['liability'] ?? 0.0),
        "pnl" => (float)($r['pnl'] ?? 0.0),
        "status" => ucfirst($r['status'] ?? 'Settled')
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
