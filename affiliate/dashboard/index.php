<?php
/**
 * Affiliate Dashboard Endpoint
 * Endpoint: GET /affiliate/dashboard
 * Protected by JWT Auth Middleware. Returns live metrics for Affiliate React App.
 */

if (!defined("ACCESS_SECURITY")) define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// 1. Fetch Affiliate profile & balance metrics
$sql = "SELECT id, affiliate_code, email, full_name, phone, company_name, website, status, onboarding_completed, tier, deal_type, revshare_pct, cpa_amount, sub_override_pct, 
               available_balance, pending_balance, lifetime_earnings, created_at 
        FROM affiliates 
        WHERE id = ? 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$aff) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Affiliate profile not found."]);
    exit;
}

$dateRange = trim($_GET['date_range'] ?? $_GET['range'] ?? 'Last 7 Days');

// Date-filtered metrics logic
$refDateSql = "";
$commDateSql = "";

if ($dateRange === 'Today') {
    $refDateSql = " AND DATE(signup_at) = CURDATE()";
    $commDateSql = " AND DATE(created_at) = CURDATE()";
} elseif ($dateRange === 'Yesterday') {
    $refDateSql = " AND DATE(signup_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $commDateSql = " AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($dateRange === 'Last 7 Days') {
    $refDateSql = " AND signup_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $commDateSql = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateRange === 'This Month') {
    $refDateSql = " AND signup_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
    $commDateSql = " AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
}

// 2. Count total clicks on affiliate tracking links
$clickStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(clicks_count), 0) AS total_clicks FROM affiliate_links WHERE affiliate_id = ?");
mysqli_stmt_bind_param($clickStmt, "i", $affiliateId);
mysqli_stmt_execute($clickStmt);
$totalClicks = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($clickStmt))['total_clicks'];

// Period Clicks matching date range
$periodClicks = 0;
if ($dateRange === 'Today' || $dateRange === 'Last 7 Days' || $dateRange === 'This Month') {
    $periodClicks = $totalClicks;
} elseif ($dateRange === 'Yesterday') {
    $periodClicks = 0;
}

// Filtered Period Referrals & FTDs
$refRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_referrals,
        COUNT(CASE WHEN first_deposit_at IS NOT NULL THEN 1 END) AS total_ftds
    FROM affiliate_referrals
    WHERE affiliate_id = {$affiliateId} {$refDateSql}
");
$refData = mysqli_fetch_assoc($refRes);
$periodReferrals = (int)($refData['total_referrals'] ?? 0);
$periodFtds = (int)($refData['total_ftds'] ?? 0);

// Period Commission
$commRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) AS period_commission 
    FROM affiliate_commission_ledger 
    WHERE affiliate_id = {$affiliateId} {$commDateSql}
");
$periodCommission = (float)(mysqli_fetch_assoc($commRes)['period_commission'] ?? 0.00);

// Period NGR
$ngrRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(base_amount), 0) AS total_ngr 
    FROM affiliate_commission_ledger 
    WHERE affiliate_id = {$affiliateId} AND entry_type IN ('revshare', 'rev_share', 'ngr') {$commDateSql}
");
$totalNgr = (float)(mysqli_fetch_assoc($ngrRes)['total_ngr'] ?? 0.00);

// 4. Calculate this month's earnings
$monthStmt = mysqli_prepare($conn, 
    "SELECT COALESCE(SUM(amount), 0) AS month_earnings 
     FROM affiliate_commission_ledger 
     WHERE affiliate_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status IN ('pending', 'approved', 'paid')");
mysqli_stmt_bind_param($monthStmt, "i", $affiliateId);
mysqli_stmt_execute($monthStmt);
$monthEarnings = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($monthStmt))['month_earnings'];

// 5. Daily Breakdown for Revenue & Activity Trends chart
$dailyTrends = [];
if ($dateRange === 'Today') {
    for ($h = 0; $h < 24; $h += 4) {
        $slotLabel = sprintf("%02d:00", $h);
        $fRes = mysqli_query($conn, "SELECT COUNT(*) AS ftds FROM affiliate_referrals WHERE affiliate_id = {$affiliateId} AND DATE(first_deposit_at) = CURDATE() AND HOUR(first_deposit_at) >= {$h} AND HOUR(first_deposit_at) <= " . ($h + 3));
        $fCount = (int)(mysqli_fetch_assoc($fRes)['ftds'] ?? 0);

        $cRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS comm FROM affiliate_commission_ledger WHERE affiliate_id = {$affiliateId} AND DATE(created_at) = CURDATE() AND HOUR(created_at) >= {$h} AND HOUR(created_at) <= " . ($h + 3));
        $cComm = (float)(mysqli_fetch_assoc($cRes)['comm'] ?? 0);

        $dailyTrends[] = [
            'day' => $slotLabel,
            'date' => date('Y-m-d'),
            'clicks' => ($h === 16) ? $totalClicks : 0,
            'ftds' => $fCount,
            'commission' => $cComm
        ];
    }
} elseif ($dateRange === 'Yesterday') {
    for ($h = 0; $h < 24; $h += 4) {
        $slotLabel = sprintf("%02d:00", $h);
        $dailyTrends[] = [
            'day' => $slotLabel,
            'date' => date('Y-m-d', strtotime('-1 day')),
            'clicks' => 0,
            'ftds' => 0,
            'commission' => 0
        ];
    }
} else {
    $numDays = ($dateRange === 'This Month') ? (int)date('j') : 7;
    for ($i = $numDays - 1; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = ($dateRange === 'This Month') ? date('j M', strtotime("-$i days")) : date('D', strtotime("-$i days"));

        $fRes = mysqli_query($conn, "SELECT COUNT(*) AS ftds FROM affiliate_referrals WHERE affiliate_id = {$affiliateId} AND DATE(first_deposit_at) = '{$dateStr}'");
        $fCount = (int)(mysqli_fetch_assoc($fRes)['ftds'] ?? 0);

        $sRes = mysqli_query($conn, "SELECT COUNT(*) AS signups FROM affiliate_referrals WHERE affiliate_id = {$affiliateId} AND DATE(signup_at) = '{$dateStr}'");
        $sCount = (int)(mysqli_fetch_assoc($sRes)['signups'] ?? 0);

        $cRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS comm FROM affiliate_commission_ledger WHERE affiliate_id = {$affiliateId} AND DATE(created_at) = '{$dateStr}'");
        $cComm = (float)(mysqli_fetch_assoc($cRes)['comm'] ?? 0);

        $dailyTrends[] = [
            'day' => $dayLabel,
            'date' => $dateStr,
            'clicks' => ($dateStr === date('Y-m-d')) ? $totalClicks : 0,
            'signups' => $sCount,
            'ftds' => $fCount,
            'commission' => $cComm
        ];
    }
}

// 6. Live Real-Time Gameplay & Estimated Revenue (Today)
$liveUids = [];
$uidMap = [];

$refQuery = mysqli_query($conn, "
    SELECT ar.user_id, u.tbl_uniq_id, u.tbl_user_name, u.tbl_full_name 
    FROM affiliate_referrals ar
    LEFT JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
    WHERE ar.affiliate_id = {$affiliateId}
");

while ($rRow = mysqli_fetch_assoc($refQuery)) {
    $displayName = !empty($rRow['tbl_user_name']) ? $rRow['tbl_user_name'] : (!empty($rRow['tbl_full_name']) ? $rRow['tbl_full_name'] : ('Player #' . $rRow['user_id']));
    if (!empty($rRow['user_id'])) {
        $uVal = mysqli_real_escape_string($conn, (string)$rRow['user_id']);
        $liveUids[] = "'{$uVal}'";
        $uidMap[$uVal] = $displayName;
    }
    if (!empty($rRow['tbl_uniq_id'])) {
        $uVal = mysqli_real_escape_string($conn, (string)$rRow['tbl_uniq_id']);
        $liveUids[] = "'{$uVal}'";
        $uidMap[$uVal] = $displayName;
    }
}

$liveStats = [
    "today_turnover" => 0.0,
    "today_player_wins" => 0.0,
    "today_player_losses" => 0.0,
    "today_ggr" => 0.0,
    "today_ngr" => 0.0,
    "today_estimated_revshare" => 0.0,
    "already_settled_today" => 0.0,
    "unsettled_revshare" => 0.0,
    "active_players_today" => 0,
    "total_rounds_today" => 0
];
$liveRecentBets = [];

if (!empty($liveUids)) {
    $uidList = implode(',', array_unique($liveUids));

    // Live Aggregation for Today from tblmatchplayed
    $liveAggRes = mysqli_query($conn, "
        SELECT 
            COUNT(*) AS total_rounds,
            COUNT(DISTINCT tbl_user_id) AS active_players,
            COALESCE(SUM(tbl_match_cost), 0) AS total_bets,
            COALESCE(SUM(tbl_match_profit), 0) AS total_wins,
            COALESCE(SUM(CASE WHEN tbl_match_status IN ('loss', 'lost') OR tbl_match_profit = 0 THEN tbl_match_cost ELSE 0 END), 0) AS total_losses
        FROM tblmatchplayed 
        WHERE tbl_user_id IN ({$uidList})
          AND tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')
          AND (
            DATE(tbl_updated_at) = CURDATE() 
            OR DATE(created_at) = CURDATE() 
            OR tbl_time_stamp LIKE CONCAT(DATE_FORMAT(CURDATE(), '%d-%m-%Y'), '%')
          )
    ");

    if ($liveAggRes && $aggRow = mysqli_fetch_assoc($liveAggRes)) {
        $bets = (float)$aggRow['total_bets'];
        $wins = (float)$aggRow['total_wins'];
        $ggr = $bets - $wins;
        $platformFee = $ggr > 0 ? ($ggr * 0.15) : 0.0;
        $ngr = max(0, $ggr - $platformFee);
        $revsharePct = (float)($aff['revshare_pct'] ?? 30.0);
        $estimatedRevshare = in_array($aff['deal_type'], ['revenue_share', 'revshare', 'hybrid']) ? ($ngr * ($revsharePct / 100.0)) : 0.0;

        // Check how much has already been settled in ledger today
        $settledTodayRes = mysqli_query($conn, "
            SELECT COALESCE(SUM(amount), 0) AS settled_today 
            FROM affiliate_commission_ledger 
            WHERE affiliate_id = {$affiliateId} AND entry_type = 'rev_share' AND DATE(created_at) = CURDATE()
        ");
        $alreadySettled = (float)(mysqli_fetch_assoc($settledTodayRes)['settled_today'] ?? 0.0);
        $unsettled = max(0, $estimatedRevshare - $alreadySettled);

        // Also calculate Active Settlement Cycle metrics (since last_settled_at)
        $lastSettledAt = $aff['last_settled_at'] ?? null;
        $cycleDateClause = !empty($lastSettledAt) ? "AND (tbl_updated_at >= '{$lastSettledAt}' OR created_at >= '{$lastSettledAt}')" : "";

        $cycleAggRes = mysqli_query($conn, "
            SELECT 
                COUNT(*) AS total_rounds,
                COUNT(DISTINCT tbl_user_id) AS active_players,
                COALESCE(SUM(tbl_match_cost), 0) AS total_bets,
                COALESCE(SUM(tbl_match_profit), 0) AS total_wins,
                COALESCE(SUM(CASE WHEN tbl_match_status IN ('loss', 'lost') OR tbl_match_profit = 0 THEN tbl_match_cost ELSE 0 END), 0) AS total_losses
            FROM tblmatchplayed 
            WHERE tbl_user_id IN ({$uidList})
              AND tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')
              {$cycleDateClause}
        ");

        $cycleBets = $bets;
        $cycleWins = $wins;
        $cycleGgr = $ggr;
        $cycleNgr = $ngr;
        $cycleRevshare = $estimatedRevshare;
        $cyclePlayers = (int)$aggRow['active_players'];
        $cycleRounds = (int)$aggRow['total_rounds'];

        if ($cycleAggRes && $cRow = mysqli_fetch_assoc($cycleAggRes)) {
            $cycleBets = (float)$cRow['total_bets'];
            $cycleWins = (float)$cRow['total_wins'];
            $cycleGgr = $cycleBets - $cycleWins;
            $cPlatformFee = $cycleGgr > 0 ? ($cycleGgr * 0.15) : 0.0;
            $cycleNgr = max(0, $cycleGgr - $cPlatformFee);
            $cycleRevshare = in_array($aff['deal_type'], ['revenue_share', 'revshare', 'hybrid']) ? ($cycleNgr * ($revsharePct / 100.0)) : 0.0;
            $cyclePlayers = (int)$cRow['active_players'];
            $cycleRounds = (int)$cRow['total_rounds'];
        }

        $liveStats = [
            "cycle_turnover" => round($cycleBets, 2),
            "cycle_player_wins" => round($cycleWins, 2),
            "cycle_player_losses" => round((float)($cRow['total_losses'] ?? 0), 2),
            "cycle_ggr" => round($cycleGgr, 2),
            "cycle_ngr" => round($cycleNgr, 2),
            "cycle_estimated_revshare" => round($cycleRevshare, 2),
            "active_players_cycle" => $cyclePlayers,
            "total_rounds_cycle" => $cycleRounds,
            "today_turnover" => round($bets, 2),
            "today_player_wins" => round($wins, 2),
            "today_player_losses" => round((float)$aggRow['total_losses'], 2),
            "today_ggr" => round($ggr, 2),
            "today_ngr" => round($ngr, 2),
            "today_estimated_revshare" => round($estimatedRevshare, 2),
            "already_settled_today" => round($alreadySettled, 2),
            "unsettled_revshare" => round($unsettled, 2),
            "active_players_today" => (int)$aggRow['active_players'],
            "total_rounds_today" => (int)$aggRow['total_rounds']
        ];
    }

    // Recent 10 live bets from referred players
    $recentBetsRes = mysqli_query($conn, "
        SELECT id, tbl_user_id, tbl_project_name, tbl_period_id, tbl_match_cost, tbl_match_profit, tbl_match_status, tbl_match_result, tbl_time_stamp, tbl_updated_at, created_at
        FROM tblmatchplayed 
        WHERE tbl_user_id IN ({$uidList})
          AND tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')
        ORDER BY id DESC 
        LIMIT 10
    ");

    if ($recentBetsRes) {
        while ($bRow = mysqli_fetch_assoc($recentBetsRes)) {
            $cost = (float)$bRow['tbl_match_cost'];
            $profit = (float)$bRow['tbl_match_profit'];
            $net = round($profit - $cost, 2);
            $isLoss = ($net < 0) || ($profit == 0 && $cost > 0) || in_array($bRow['tbl_match_status'], ['loss', 'lost']);
            
            // Estimated affiliate cut on this round if house won
            $roundNgr = $cost > $profit ? (($cost - $profit) * 0.85) : 0;
            $estCut = round($roundNgr * ((float)($aff['revshare_pct'] ?? 30.0) / 100.0), 2);

            $uKey = (string)$bRow['tbl_user_id'];
            $pName = $uidMap[$uKey] ?? ('Player #' . substr($uKey, 0, 8));

            $liveRecentBets[] = [
                "id" => (int)$bRow['id'],
                "round_id" => $bRow['tbl_period_id'] ?: ('R-' . $bRow['id']),
                "player_name" => $pName,
                "game_name" => !empty($bRow['tbl_project_name']) ? $bRow['tbl_project_name'] : 'Casino Game',
                "bet_amount" => $cost,
                "win_amount" => $profit,
                "net_result" => $net,
                "is_loss" => $isLoss,
                "status" => $isLoss ? 'LOSS' : 'WIN',
                "estimated_affiliate_share" => $estCut,
                "time" => !empty($bRow['tbl_time_stamp']) ? $bRow['tbl_time_stamp'] : (!empty($bRow['tbl_updated_at']) ? $bRow['tbl_updated_at'] : $bRow['created_at'])
            ];
        }
    }
}

// Fetch minimum payout threshold from program_settings
$settRes = mysqli_query($conn, "SELECT affiliate_minimum_payout FROM program_settings WHERE id = 1");
$minPayout = (float)(mysqli_fetch_assoc($settRes)['affiliate_minimum_payout'] ?? 1000.00);

echo json_encode([
    "status" => "success",
    "date_range" => $dateRange,
    "data" => [
        "identity" => [
            "id" => (int)$aff['id'],
            "affiliate_code" => $aff['affiliate_code'],
            "name" => $aff['full_name'] ?? $aff['name'],
            "full_name" => $aff['full_name'],
            "email" => $aff['email'],
            "phone" => $aff['phone'] ?? '',
            "company_name" => $aff['company_name'] ?? '',
            "website" => $aff['website'] ?? '',
            "status" => $aff['status'],
            "onboarding_completed" => (bool)($aff['onboarding_completed'] ?? 0),
            "tier" => $aff['tier'],
            "deal_type" => $aff['deal_type'],
            "revshare_pct" => (float)$aff['revshare_pct'],
            "cpa_amount" => (float)$aff['cpa_amount'],
            "sub_override_pct" => (float)$aff['sub_override_pct']
        ],
        "balances" => [
            "available_balance" => (float)$aff['available_balance'],
            "pending_balance" => (float)$aff['pending_balance'],
            "lifetime_earnings" => (float)$aff['lifetime_earnings'],
            "minimum_payout_threshold" => $minPayout
        ],
        "metrics" => [
            "total_clicks" => $periodClicks,
            "total_referrals" => $periodReferrals,
            "total_ftds" => $periodFtds,
            "total_ngr" => $totalNgr,
            "period_commission" => $periodCommission,
            "month_earnings" => $monthEarnings
        ],
        "live_stats" => $liveStats,
        "live_recent_bets" => $liveRecentBets,
        "daily_trends" => $dailyTrends
    ]
]);
