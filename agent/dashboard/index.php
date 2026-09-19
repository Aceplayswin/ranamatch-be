<?php
/**
 * Agent Dashboard Endpoint
 * Endpoint: GET /agent/dashboard
 * Protected by JWT Auth Middleware. Returns live metrics for Agent React App.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure token role is 'agent'
if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

// 1. Fetch Agent basic financial metrics
$sql = "SELECT id, agent_code, username, name, email, rank_level, status, partnership_pct, turnover_commission_pct, current_credit, exposed_credit, balance, withdrawable_profit, cycle_net_pnl, last_settled_at 
        FROM agents 
        WHERE id = ? 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(444);
    echo json_encode(["status" => "error", "message" => "Agent profile not found."]);
    exit;
}

$currentCredit = (float)$agent['current_credit'];
$exposedCredit = (float)$agent['exposed_credit'];
$availableCredit = max(0, $currentCredit - $exposedCredit);
$withdrawableProfit = (float)($agent['withdrawable_profit'] ?? 0.00);
$cycleNetPnl = (float)($agent['cycle_net_pnl'] ?? 0.00);

// Fetch program settlement cycle rules
$psRes = mysqli_query($conn, "SELECT agent_settlement_cycle, agent_minimum_payout, agent_negative_carryover FROM program_settings WHERE id = 1");
$ps = mysqli_fetch_assoc($psRes);
$settlementCycle = $ps['agent_settlement_cycle'] ?? 'weekly_monday';
$minimumPayout = (float)($ps['agent_minimum_payout'] ?? 1000.00);
$canWithdraw = ($withdrawableProfit >= $minimumPayout);

$downlinePattern = "(transferring_agent_id IS NOT NULL OR remark LIKE '%spread%' OR remark LIKE '%from %' OR remark LIKE '%Downline%' OR remark LIKE '%Depth: 1%' OR remark LIKE '%Depth: 2%' OR remark LIKE '%Depth: 3%')";
$directPattern = "(transferring_agent_id IS NULL AND remark NOT LIKE '%spread%' AND remark NOT LIKE '%from %' AND remark NOT LIKE '%Downline%' AND remark NOT LIKE '%Depth: 1%' AND remark NOT LIKE '%Depth: 2%' AND remark NOT LIKE '%Depth: 3%')";

// 2. Fetch today's turnover commission total from ledger (total, downline spread, and direct)
$commStmt = mysqli_prepare($conn, 
    "SELECT COALESCE(SUM(amount), 0) AS today_commission,
            COALESCE(SUM(CASE WHEN {$downlinePattern} THEN amount ELSE 0 END), 0) AS downline_commission,
            COALESCE(SUM(CASE WHEN {$directPattern} THEN amount ELSE 0 END), 0) AS direct_commission
     FROM agent_credit_ledger 
     WHERE agent_id = ? AND transaction_type = 'TURNOVER_COMMISSION' AND DATE(created_at) = CURDATE()");
mysqli_stmt_bind_param($commStmt, "i", $agentId);
mysqli_stmt_execute($commStmt);
$commRes = mysqli_fetch_assoc(mysqli_stmt_get_result($commStmt));
$todayCommission = (float)$commRes['today_commission'];
$todayDownlineCommission = (float)$commRes['downline_commission'];
$todayDirectCommission = (float)$commRes['direct_commission'];

// 3. Fetch today's P&L settlement total from ledger (total, downline spread, and direct)
$pnlStmt = mysqli_prepare($conn, 
    "SELECT COALESCE(SUM(amount), 0) AS today_pnl,
            COALESCE(SUM(CASE WHEN {$downlinePattern} THEN amount ELSE 0 END), 0) AS downline_pnl,
            COALESCE(SUM(CASE WHEN {$directPattern} THEN amount ELSE 0 END), 0) AS direct_pnl
     FROM agent_credit_ledger 
     WHERE agent_id = ? AND transaction_type = 'PNL_SETTLEMENT' AND DATE(created_at) = CURDATE()");
mysqli_stmt_bind_param($pnlStmt, "i", $agentId);
mysqli_stmt_execute($pnlStmt);
$pnlRes = mysqli_fetch_assoc(mysqli_stmt_get_result($pnlStmt));
$todayPnl = (float)$pnlRes['today_pnl'];
$todayDownlinePnl = (float)$pnlRes['downline_pnl'];
$todayDirectPnl = (float)$pnlRes['direct_pnl'];

// 4. Count direct downline agents
$downlineStmt = mysqli_prepare($conn, 
    "SELECT COUNT(*) AS downline_count 
     FROM agent_tree 
     WHERE ancestor_id = ? AND depth = 1");
mysqli_stmt_bind_param($downlineStmt, "i", $agentId);
mysqli_stmt_execute($downlineStmt);
$downlineRes = mysqli_fetch_assoc(mysqli_stmt_get_result($downlineStmt));
$downlineCount = (int)$downlineRes['downline_count'];

// 5. Count assigned players
$parentIdStr = (string)$agentId;
$playerStmt = mysqli_prepare($conn, 
    "SELECT COUNT(*) AS player_count 
     FROM tblusersdata 
     WHERE tbl_joined_under = ? OR tbl_joined_under = ? OR tbl_joined_under = ?");
mysqli_stmt_bind_param($playerStmt, "sss", $agent['agent_code'], $agent['username'], $parentIdStr);
mysqli_stmt_execute($playerStmt);
$playerRes = mysqli_fetch_assoc(mysqli_stmt_get_result($playerStmt));
$playerCount = (int)($playerRes['player_count'] ?? 0);

// Fetch turnover breakdown by category
$sportsVolume = 0.0;
$casinoVolume = 0.0;
$volStmt = mysqli_prepare($conn, 
    "SELECT 
        SUM(CASE WHEN game_type = 'casino' THEN stake ELSE 0 END) AS casino_vol,
        SUM(CASE WHEN game_type != 'casino' OR game_type IS NULL THEN stake ELSE 0 END) AS sports_vol
     FROM player_bets
     WHERE agent_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($volStmt) {
    mysqli_stmt_bind_param($volStmt, "i", $agentId);
    if (mysqli_stmt_execute($volStmt)) {
        $vRes = mysqli_fetch_assoc(mysqli_stmt_get_result($volStmt));
        $sportsVolume = (float)($vRes['sports_vol'] ?? 0.0);
        $casinoVolume = (float)($vRes['casino_vol'] ?? 0.0);
    }
}

// Fetch Recent Downline Transfers (allocations, float transfers, clawbacks)
$transferStmt = mysqli_prepare($conn, 
    "SELECT id, transaction_type, amount, balance_before, balance_after, remark, created_at 
     FROM agent_credit_ledger 
     WHERE agent_id = ? AND transaction_type IN ('TRANSFER_OUT', 'TRANSFER_IN', 'INJECTION', 'CLAWBACK', 'CREDIT_TRANSFER', 'CHIP_DEPOSIT', 'CHIP_WITHDRAWAL')
     ORDER BY id DESC LIMIT 8");
$recentTransfers = [];
if ($transferStmt) {
    mysqli_stmt_bind_param($transferStmt, "i", $agentId);
    mysqli_stmt_execute($transferStmt);
    $transferRes = mysqli_stmt_get_result($transferStmt);
    while ($row = mysqli_fetch_assoc($transferRes)) {
        $rawAmt = (float)$row['amount'];
        $txType = $row['transaction_type'];
        $balBefore = isset($row['balance_before']) ? (float)$row['balance_before'] : null;
        $balAfter = isset($row['balance_after']) ? (float)$row['balance_after'] : null;

        $isOutflow = ($txType === 'TRANSFER_OUT' || $txType === 'CLAWBACK' || ($balBefore !== null && $balAfter !== null && $balAfter < $balBefore));
        $signedAmt = $isOutflow ? -abs($rawAmt) : abs($rawAmt);

        $targetLabel = match($txType) {
            'TRANSFER_OUT' => 'Downline Transfer Out',
            'TRANSFER_IN' => 'Downline Transfer In',
            'INJECTION' => 'Float Deposit',
            'CLAWBACK' => 'Chip Recall',
            default => str_replace('_', ' ', $txType)
        };

        $recentTransfers[] = [
            "id" => (int)$row['id'],
            "type" => $txType,
            "target" => $targetLabel,
            "amount" => $signedAmt,
            "balance_after" => (float)($balAfter ?? 0),
            "remark" => $row['remark'] ?: 'Credit Transfer',
            "time" => $row['created_at'] ? date('M d, H:i', strtotime($row['created_at'])) : 'Just now'
        ];
    }
}

// All recent ledger transactions (including bet commissions and P&L settlements)
$recentTxStmt = mysqli_prepare($conn, 
    "SELECT id, transaction_type, amount, balance_before, balance_after, remark, created_at 
     FROM agent_credit_ledger 
     WHERE agent_id = ? 
     ORDER BY id DESC LIMIT 8");
$recentTransactions = [];
if ($recentTxStmt) {
    mysqli_stmt_bind_param($recentTxStmt, "i", $agentId);
    mysqli_stmt_execute($recentTxStmt);
    $recentTxRes = mysqli_stmt_get_result($recentTxStmt);
    while ($row = mysqli_fetch_assoc($recentTxRes)) {
        $rawAmt = (float)$row['amount'];
        $txType = $row['transaction_type'];
        $balBefore = isset($row['balance_before']) ? (float)$row['balance_before'] : null;
        $balAfter = isset($row['balance_after']) ? (float)$row['balance_after'] : null;

        if ($txType === 'TRANSFER_OUT' || $txType === 'CLAWBACK' || ($balBefore !== null && $balAfter !== null && $balAfter < $balBefore)) {
            $signedAmt = -abs($rawAmt);
        } else {
            $signedAmt = ($txType === 'PNL_SETTLEMENT') ? $rawAmt : abs($rawAmt);
        }

        $targetLabel = match($txType) {
            'TRANSFER_OUT' => 'Downline Transfer Out',
            'TRANSFER_IN' => 'Downline Transfer In',
            'TURNOVER_COMMISSION' => 'Turnover Commission',
            'PNL_SETTLEMENT' => 'Settled P&L',
            'INJECTION' => 'Float Allocation',
            'CLAWBACK' => 'Credit Recall',
            default => str_replace('_', ' ', $txType)
        };

        $recentTransactions[] = [
            "id" => (int)$row['id'],
            "type" => $txType,
            "target" => $targetLabel,
            "amount" => $signedAmt,
            "balance_after" => (float)($balAfter ?? 0),
            "remark" => $row['remark'] ?: 'Credit Transaction',
            "time" => $row['created_at'] ? date('M d, H:i', strtotime($row['created_at'])) : 'Just now'
        ];
    }
}

// Response payload matching React Agent SPA contract
echo json_encode([
    "status" => "success",
    "data" => [
        "identity" => [
            "id" => (int)$agent['id'],
            "agent_code" => $agent['agent_code'],
            "username" => $agent['username'],
            "name" => $agent['name'],
            "email" => $agent['email'],
            "rank_level" => $agent['rank_level'],
            "status" => $agent['status']
        ],
        "agent" => [
            "id" => (int)$agent['id'],
            "agent_code" => $agent['agent_code'],
            "username" => $agent['username'],
            "name" => $agent['name'],
            "email" => $agent['email'],
            "rank_level" => $agent['rank_level'],
            "commission_rate" => (float)$agent['partnership_pct'],
            "partnership_pct" => (float)$agent['partnership_pct'],
            "current_balance" => $currentCredit,
            "current_credit" => $currentCredit,
            "exposure_amount" => $exposedCredit,
            "exposed_credit" => $exposedCredit,
            "available_credit" => $availableCredit
        ],
        "metrics" => [
            "current_credit" => $currentCredit,
            "exposed_credit" => $exposedCredit,
            "available_credit" => $availableCredit,
            "partnership_pct" => (float)$agent['partnership_pct'],
            "turnover_commission_pct" => (float)$agent['turnover_commission_pct'],
            "today_commission" => $todayCommission,
            "today_direct_commission" => $todayDirectCommission,
            "today_downline_commission" => $todayDownlineCommission,
            "today_pnl" => $todayPnl,
            "today_direct_pnl" => $todayDirectPnl,
            "today_downline_pnl" => $todayDownlinePnl,
            "sports_volume" => $sportsVolume,
            "casino_volume" => $casinoVolume,
            "downline_agents_count" => $downlineCount,
            "downline_players_count" => $playerCount
        ],
        "settlement" => [
            "settlement_cycle" => $settlementCycle,
            "minimum_payout" => $minimumPayout,
            "withdrawable_profit" => $withdrawableProfit,
            "cycle_net_pnl" => $cycleNetPnl,
            "can_withdraw" => $canWithdraw,
            "last_settled_at" => $agent['last_settled_at']
        ],
        "recent_transfers" => $recentTransfers,
        "recent_transactions" => $recentTransactions
    ]
]);
