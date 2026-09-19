<?php
/**
 * Agent P&L Report Endpoint
 * Endpoint: GET /agent/reports/pnl
 * Protected by JWT Auth Middleware. Returns daily breakdown of turnover commissions & net P&L.
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

// Date range parameters (default to current month)
$fromDate = trim($_GET['from'] ?? date('Y-m-01'));
$toDate = trim($_GET['to'] ?? date('Y-m-d'));

$sql = "SELECT DATE(created_at) AS report_date,
               SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' THEN amount ELSE 0 END) AS turnover_commission,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND amount > 0 THEN amount ELSE 0 END) AS pnl_profit,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND amount < 0 THEN ABS(amount) ELSE 0 END) AS pnl_loss,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' THEN amount ELSE 0 END) AS net_pnl,
               SUM(amount) AS total_net_earnings
        FROM agent_credit_ledger
        WHERE agent_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY report_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $agentId, $fromDate, $toDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

$totalTurnoverComm = 0.0;
$totalPnlProfit = 0.0;
$totalPnlLoss = 0.0;
$totalNetPnl = 0.0;
$totalNetEarnings = 0.0;

$reportData = array_map(function($r) use (&$totalTurnoverComm, &$totalPnlProfit, &$totalPnlLoss, &$totalNetPnl, &$totalNetEarnings) {
    $tComm = (float)$r['turnover_commission'];
    $pProfit = (float)$r['pnl_profit'];
    $pLoss = (float)$r['pnl_loss'];
    $nPnl = (float)$r['net_pnl'];
    $nEarnings = (float)$r['total_net_earnings'];

    $totalTurnoverComm += $tComm;
    $totalPnlProfit += $pProfit;
    $totalPnlLoss += $pLoss;
    $totalNetPnl += $nPnl;
    $totalNetEarnings += $nEarnings;

    return [
        "date" => $r['report_date'],
        "turnover_commission" => $tComm,
        "pnl_profit" => $pProfit,
        "pnl_loss" => $pLoss,
        "net_pnl" => $nPnl,
        "total_net_earnings" => $nEarnings
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "period" => [
        "from" => $fromDate,
        "to" => $toDate
    ],
    "summary" => [
        "total_turnover_commission" => $totalTurnoverComm,
        "total_pnl_profit" => $totalPnlProfit,
        "total_pnl_loss" => $totalPnlLoss,
        "total_net_pnl" => $totalNetPnl,
        "total_net_earnings" => $totalNetEarnings
    ],
    "data" => $reportData
]);
