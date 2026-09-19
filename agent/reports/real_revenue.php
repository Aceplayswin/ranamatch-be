<?php
/**
 * Agent Real Revenue Report Endpoint
 * Endpoint: GET /agent/reports/real-revenue
 * Provides real-time revenue analytics combining:
 * - Direct Turnover Commission & Downline Turnover Spread
 * - Direct Net P&L Share & Downline Overriding P&L Spread
 * - Net Real Revenue (excluding non-revenue balance float transfers)
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

$downlinePattern = "(transferring_agent_id IS NOT NULL OR remark LIKE '%spread%' OR remark LIKE '%from %' OR remark LIKE '%Downline%' OR remark LIKE '%Depth: 1%' OR remark LIKE '%Depth: 2%' OR remark LIKE '%Depth: 3%')";
$directPattern = "(transferring_agent_id IS NULL AND remark NOT LIKE '%spread%' AND remark NOT LIKE '%from %' AND remark NOT LIKE '%Downline%' AND remark NOT LIKE '%Depth: 1%' AND remark NOT LIKE '%Depth: 2%' AND remark NOT LIKE '%Depth: 3%')";

$sql = "SELECT DATE(created_at) AS report_date,
               SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' AND {$directPattern} THEN amount ELSE 0 END) AS direct_commission,
               SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' AND {$downlinePattern} THEN amount ELSE 0 END) AS downline_commission,
               SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' THEN amount ELSE 0 END) AS turnover_commission,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND {$directPattern} THEN amount ELSE 0 END) AS direct_pnl,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND {$downlinePattern} THEN amount ELSE 0 END) AS downline_pnl,
               SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' THEN amount ELSE 0 END) AS pnl_settlement,
               SUM(CASE WHEN transaction_type IN ('TURNOVER_COMMISSION', 'PNL_SETTLEMENT') THEN amount ELSE 0 END) AS total_revenue
        FROM agent_credit_ledger
        WHERE agent_id = ?
        GROUP BY DATE(created_at)
        ORDER BY report_date DESC LIMIT 30";

$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

$data = array_map(function($r) {
    $turnover = (float)($r['turnover_commission'] ?? 0.0);
    $pnl = (float)($r['pnl_settlement'] ?? 0.0);
    $directComm = (float)($r['direct_commission'] ?? 0.0);
    $downlineComm = (float)($r['downline_commission'] ?? 0.0);
    $directPnl = (float)($r['direct_pnl'] ?? 0.0);
    $downlinePnl = (float)($r['downline_pnl'] ?? 0.0);
    // Real Revenue is strictly Turnover Commission + P&L Share (not float injections/transfers)
    $realRevenue = round($turnover + $pnl, 2);

    return [
        "date" => $r['report_date'],
        "direct_commission" => round($directComm, 2),
        "downline_commission" => round($downlineComm, 2),
        "turnover_commission" => round($turnover, 2),
        "direct_pnl" => round($directPnl, 2),
        "downline_pnl" => round($downlinePnl, 2),
        "pnl_settlement" => round($pnl, 2),
        "total_revenue" => $realRevenue
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
