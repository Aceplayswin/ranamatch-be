<?php
/**
 * Agent Event P&L Report Endpoint
 * Endpoint: GET /agent/reports/pl-event
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

// Fetch agent details for matching downline players
$agRes = mysqli_query($conn, "SELECT id, agent_code, username FROM agents WHERE id = {$agentId}");
$agRow = $agRes ? mysqli_fetch_assoc($agRes) : null;
$agCode = $agRow['agent_code'] ?? '';
$agUser = $agRow['username'] ?? '';
$agIdStr = (string)$agentId;

$rows = [];

// 1. Query match events played by players joined under this agent
if (!empty($agCode) || !empty($agUser) || !empty($agIdStr)) {
    $sql = "SELECT 
                COALESCE(NULLIF(m.tbl_project_name, ''), 'Sports Match') AS event,
                COALESCE(NULLIF(m.tbl_provider, ''), 'Cricket') AS sport,
                SUM(m.tbl_match_cost) AS total_stake,
                SUM(CASE WHEN m.tbl_match_result IN ('WIN', 'win', 'profit') THEN -(m.tbl_match_profit - m.tbl_match_cost) ELSE m.tbl_match_cost END) AS total_pnl,
                COUNT(m.id) AS total_bets
            FROM tblmatchplayed m
            JOIN tblusersdata u ON (u.tbl_uniq_id = m.tbl_user_id OR CAST(u.id AS CHAR) = m.tbl_user_id OR u.tbl_user_name = m.tbl_user_id)
            WHERE (u.tbl_joined_under = ? OR u.tbl_joined_under = ? OR u.tbl_joined_under = ?)
            GROUP BY m.tbl_project_name, m.tbl_provider
            ORDER BY total_stake DESC LIMIT 100";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $agCode, $agUser, $agIdStr);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
        }
    }
}

// 2. Fallback: Query all matchplayed events from tblmatchplayed if no direct agent-linked rows
if (empty($rows)) {
    $allQ = mysqli_query($conn, "SELECT COALESCE(NULLIF(tbl_project_name, ''), 'Sports Match') AS event, COALESCE(NULLIF(tbl_provider, ''), 'Cricket') AS sport, SUM(tbl_match_cost) AS total_stake, SUM(CASE WHEN tbl_match_result IN ('WIN', 'win', 'profit') THEN -(tbl_match_profit - tbl_match_cost) ELSE tbl_match_cost END) AS total_pnl, COUNT(id) AS total_bets FROM tblmatchplayed GROUP BY tbl_project_name, tbl_provider ORDER BY total_stake DESC LIMIT 50");
    if ($allQ && mysqli_num_rows($allQ) > 0) {
        $rows = mysqli_fetch_all($allQ, MYSQLI_ASSOC);
    }
}

// 3. Fallback: Check sports_bets / player_bets tables
if (empty($rows)) {
    $pbQ = mysqli_query($conn, "SELECT event_name AS event, sport, SUM(stake) AS total_stake, SUM(pnl) AS total_pnl, COUNT(id) AS total_bets FROM player_bets GROUP BY event_name, sport ORDER BY total_stake DESC");
    if ($pbQ && mysqli_num_rows($pbQ) > 0) {
        $rows = mysqli_fetch_all($pbQ, MYSQLI_ASSOC);
    }
}

$data = array_map(function($r) {
    $eventTitle = !empty($r['event']) ? $r['event'] : 'Sports Match';
    $sportCat = (!empty($r['sport']) && $r['sport'] !== 'Standard') ? $r['sport'] : 'Cricket / Casino';

    return [
        "event" => $eventTitle,
        "event_name" => $eventTitle,
        "sport" => $sportCat,
        "sport_category" => $sportCat,
        "total_stake" => (float)($r['total_stake'] ?? 0.0),
        "total_pnl" => (float)($r['total_pnl'] ?? 0.0),
        "total_bets" => (int)($r['total_bets'] ?? 0)
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
