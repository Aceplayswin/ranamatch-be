<?php
/**
 * Agent Downline P&L Report Endpoint
 * Endpoint: GET /agent/reports/pl-agent
 * Calculates both Downline Agent earnings and Upline Overriding Spread earnings.
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

// 1. Fetch Upline's own commercial terms
$uplineStmt = mysqli_prepare($conn, "SELECT id, username, name, partnership_pct, turnover_commission_pct FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($uplineStmt, "i", $agentId);
mysqli_stmt_execute($uplineStmt);
$upline = mysqli_fetch_assoc(mysqli_stmt_get_result($uplineStmt));
$uplinePartnership = (float)($upline['partnership_pct'] ?? 0.0);
$uplineTurnover = (float)($upline['turnover_commission_pct'] ?? 0.0);

// 2. Fetch direct downlines (depth = 1)
$sql = "SELECT a.id, a.username, a.name, a.rank_level, a.partnership_pct, a.turnover_commission_pct,
               COALESCE(SUM(l.amount), 0) AS downline_pnl
        FROM agent_tree t
        JOIN agents a ON t.descendant_id = a.id
        LEFT JOIN agent_credit_ledger l ON a.id = l.agent_id AND l.transaction_type = 'PNL_SETTLEMENT'
        WHERE t.ancestor_id = ? AND t.depth = 1
        GROUP BY a.id, a.username, a.name, a.rank_level, a.partnership_pct, a.turnover_commission_pct
        ORDER BY a.id DESC";

$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

$data = [];
$totalDownlinesPnl = 0.0;
$totalUplineEarned = 0.0;

foreach ($rows as $r) {
    $downlineId = (int)$r['id'];
    $downlineRate = (float)($r['partnership_pct'] ?? 0.0);
    $downlinePnl = (float)($r['downline_pnl'] ?? 0.0);
    $spreadPct = max(0.0, $uplinePartnership - $downlineRate);

    // Check exact upline earnings logged in ledger for this downline
    $uplineLedgerStmt = mysqli_prepare($conn, 
        "SELECT COALESCE(SUM(amount), 0) AS direct_spread 
         FROM agent_credit_ledger 
         WHERE agent_id = ? AND transaction_type = 'PNL_SETTLEMENT' 
           AND (transferring_agent_id = ? OR remark LIKE CONCAT('%', ?, '%'))");
    $downlineUser = $r['username'];
    mysqli_stmt_bind_param($uplineLedgerStmt, "iis", $agentId, $downlineId, $downlineUser);
    mysqli_stmt_execute($uplineLedgerStmt);
    $ledgerSpread = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($uplineLedgerStmt))['direct_spread'];

    // If ledger has explicit rows, use them; otherwise calculate proportional spread
    if ($ledgerSpread != 0) {
        $uplineEarned = $ledgerSpread;
        $playerNetLoss = ($spreadPct > 0) ? ($uplineEarned / ($spreadPct / 100.0)) : ($downlineRate > 0 ? ($downlinePnl / ($downlineRate / 100.0)) : 0.0);
    } else {
        $playerNetLoss = $downlineRate > 0 ? ($downlinePnl / ($downlineRate / 100.0)) : 0.0;
        $uplineEarned = $playerNetLoss * ($spreadPct / 100.0);
    }

    $totalDownlinesPnl += $downlinePnl;
    $totalUplineEarned += $uplineEarned;

    $data[] = [
        "id" => $downlineId,
        "agent" => $r['name'] ?: $r['username'],
        "username" => $r['username'],
        "rank" => ucwords(str_replace('_', ' ', $r['rank_level'] ?? 'agent')),
        "partnership" => $downlineRate,
        "downline_pnl" => $downlinePnl,
        "total_pnl" => $downlinePnl, // Backward-compatibility
        "upline_partnership" => $uplinePartnership,
        "spread_pct" => $spreadPct,
        "your_earnings" => round($uplineEarned, 2),
        "total_player_pnl" => round($playerNetLoss, 2)
    ];
}

echo json_encode([
    "status" => "success",
    "meta" => [
        "upline_agent_id" => $agentId,
        "upline_username" => $upline['username'] ?? '',
        "upline_partnership_pct" => $uplinePartnership,
        "total_downlines" => count($data),
        "total_downlines_pnl" => round($totalDownlinesPnl, 2),
        "total_your_overriding_earnings" => round($totalUplineEarned, 2)
    ],
    "data" => $data
]);
