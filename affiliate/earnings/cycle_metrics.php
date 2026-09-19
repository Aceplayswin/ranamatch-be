<?php
/**
 * Affiliate Settlement Cycle Metrics API Endpoint
 * Endpoint: GET /affiliate/earnings/cycle-metrics
 * Protected by JWT Auth Middleware.
 * Returns:
 * - Current unsettled cycle metrics starting from last_settled_at watermark (fresh calculation)
 * - Past settlement snapshots from affiliate_settlements
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/AffiliateSettlementService.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;
$service = new AffiliateSettlementService($conn);

// 1. Fetch current active cycle stats (from last_settled_at to NOW)
$activeMetrics = $service->getUnsettledCycleMetrics($affiliateId);

// 2. Fetch past settlement history
$histSql = "SELECT id, cycle_label, start_time, end_time, total_bets, total_wins, total_losses, 
                   total_deposits, total_withdrawals, total_ggr, total_ngr, commission_amount, 
                   sub_override_amount, total_payout, status, settled_at
            FROM affiliate_settlements 
            WHERE affiliate_id = {$affiliateId} 
            ORDER BY settled_at DESC, id DESC 
            LIMIT 20";
$histRes = mysqli_query($conn, $histSql);
$settlementHistory = [];
if ($histRes) {
    while ($h = mysqli_fetch_assoc($histRes)) {
        $settlementHistory[] = [
            "id" => (int)$h['id'],
            "cycle_label" => $h['cycle_label'],
            "start_time" => $h['start_time'],
            "end_time" => $h['end_time'],
            "total_bets" => (float)$h['total_bets'],
            "total_wins" => (float)$h['total_wins'],
            "total_losses" => (float)$h['total_losses'],
            "total_deposits" => (float)$h['total_deposits'],
            "total_withdrawals" => (float)$h['total_withdrawals'],
            "total_ggr" => (float)$h['total_ggr'],
            "total_ngr" => (float)$h['total_ngr'],
            "commission_amount" => (float)$h['commission_amount'],
            "sub_override_amount" => (float)$h['sub_override_amount'],
            "total_payout" => (float)$h['total_payout'],
            "status" => $h['status'],
            "settled_at" => $h['settled_at']
        ];
    }
}

echo json_encode([
    "status" => "success",
    "active_cycle" => $activeMetrics['data'] ?? null,
    "settlement_history" => $settlementHistory
]);
