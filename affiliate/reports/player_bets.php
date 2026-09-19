<?php
/**
 * Affiliate Player Match & Bet Activity API Endpoint
 * Endpoint: GET /affiliate/player-bets or /api/v1/affiliate/player-bets
 * Protected by JWT Auth Middleware. Returns detailed matchplayed rounds for referred players.
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

// 1. Fetch affiliate commercial deal details & settlement watermark
$affQ = mysqli_query($conn, "SELECT id, affiliate_code, deal_type, revshare_pct, last_settled_at FROM affiliates WHERE id = {$affiliateId} LIMIT 1");
$aff = mysqli_fetch_assoc($affQ);
$revsharePct = (float)($aff['revshare_pct'] ?? 30.0);
$dealType = strtolower($aff['deal_type'] ?? 'revshare');
$lastSettledAt = $aff['last_settled_at'] ?? null;

// 2. Fetch all players referred by this affiliate (direct + downline sub-affiliates)
$refUsersQ = mysqli_query($conn, "
    SELECT ar.user_id, u.id AS internal_id, u.tbl_uniq_id, u.tbl_user_name, u.tbl_full_name, u.tbl_email_id, u.tbl_mobile_num,
           sub_af.id AS sub_aff_id, sub_af.affiliate_code AS sub_aff_code, sub_af.full_name AS sub_aff_name
    FROM affiliate_referrals ar
    JOIN affiliates sub_af ON sub_af.id = ar.affiliate_id
    LEFT JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
    WHERE ar.affiliate_id = {$affiliateId} OR sub_af.parent_id = {$affiliateId}
");

$referredPlayers = [];
$uidMap = [];
$allUids = [];

while ($uRow = mysqli_fetch_assoc($refUsersQ)) {
    $isSub = ((int)$uRow['sub_aff_id'] !== $affiliateId);
    $baseName = $uRow['tbl_user_name'] ?: ($uRow['tbl_full_name'] ?: ('Player #' . $uRow['user_id']));
    $displayName = $isSub ? ($baseName . " (Sub: " . ($uRow['sub_aff_name'] ?: $uRow['sub_aff_code']) . ")") : $baseName;

    $pObj = [
        'user_id' => $uRow['user_id'],
        'internal_id' => $uRow['internal_id'],
        'tbl_uniq_id' => $uRow['tbl_uniq_id'],
        'username' => $uRow['tbl_user_name'] ?: '',
        'full_name' => $uRow['tbl_full_name'] ?: '',
        'display_name' => $displayName,
        'is_sub_affiliate' => $isSub,
        'sub_affiliate_name' => $isSub ? ($uRow['sub_aff_name'] ?: $uRow['sub_aff_code']) : null
    ];
    $referredPlayers[] = $pObj;

    if (!empty($uRow['user_id'])) {
        $allUids[] = (int)$uRow['user_id'];
        $uidMap[(string)$uRow['user_id']] = $displayName;
    }
    if (!empty($uRow['internal_id'])) {
        $allUids[] = (int)$uRow['internal_id'];
        $uidMap[(string)$uRow['internal_id']] = $displayName;
    }
    if (!empty($uRow['tbl_uniq_id'])) {
        $uidMap[(string)$uRow['tbl_uniq_id']] = $displayName;
    }
}

$allUids = array_unique($allUids);

// If affiliate has no referred players, return early
if (empty($allUids)) {
    echo json_encode([
        "status" => "success",
        "summary" => [
            "total_turnover" => 0.0,
            "total_wins" => 0.0,
            "total_losses" => 0.0,
            "net_ggr" => 0.0,
            "net_ngr" => 0.0,
            "estimated_revshare" => 0.0,
            "total_rounds" => 0,
            "revshare_rate" => $revsharePct
        ],
        "pagination" => [
            "page" => 1,
            "limit" => 25,
            "total_records" => 0,
            "total_pages" => 1
        ],
        "referred_players" => [],
        "data" => []
    ]);
    exit;
}

// 3. Filter Parameters
$filterUserId = trim($_GET['user_id'] ?? '');
$filterPlayer = trim($_GET['player'] ?? '');
$filterStatus = strtolower(trim($_GET['status'] ?? 'all'));
$filterDate = trim($_GET['date_range'] ?? 'all');
$filterGame = trim($_GET['game'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

// Target player tokens (supports internal_id, user_id, tbl_uniq_id, username)
$targetTokens = [];
foreach ($referredPlayers as $p) {
    if (!empty($p['user_id'])) $targetTokens[] = "'" . mysqli_real_escape_string($conn, $p['user_id']) . "'";
    if (!empty($p['internal_id'])) $targetTokens[] = "'" . mysqli_real_escape_string($conn, $p['internal_id']) . "'";
    if (!empty($p['tbl_uniq_id'])) $targetTokens[] = "'" . mysqli_real_escape_string($conn, $p['tbl_uniq_id']) . "'";
    if (!empty($p['username'])) $targetTokens[] = "'" . mysqli_real_escape_string($conn, $p['username']) . "'";
}

if (!empty($filterUserId)) {
    $matchedTokens = [];
    foreach ($referredPlayers as $p) {
        if ((string)$p['user_id'] === $filterUserId || (string)$p['internal_id'] === $filterUserId || (string)$p['tbl_uniq_id'] === $filterUserId) {
            if (!empty($p['user_id'])) $matchedTokens[] = "'" . mysqli_real_escape_string($conn, $p['user_id']) . "'";
            if (!empty($p['internal_id'])) $matchedTokens[] = "'" . mysqli_real_escape_string($conn, $p['internal_id']) . "'";
            if (!empty($p['tbl_uniq_id'])) $matchedTokens[] = "'" . mysqli_real_escape_string($conn, $p['tbl_uniq_id']) . "'";
        }
    }
    if (!empty($matchedTokens)) {
        $targetTokens = $matchedTokens;
    } else {
        $targetTokens = ["'" . mysqli_real_escape_string($conn, $filterUserId) . "'"];
    }
}

$targetTokens = array_unique($targetTokens);
$tokenListStr = implode(',', $targetTokens);
$whereClauses = ["(m.tbl_user_id IN ({$tokenListStr}) OR u.id IN ({$tokenListStr}) OR u.tbl_uniq_id IN ({$tokenListStr}))"];

// Status filter
if ($filterStatus === 'win' || $filterStatus === 'won') {
    $whereClauses[] = "(m.tbl_match_profit > 0 OR m.tbl_match_status IN ('win', 'profit'))";
} elseif ($filterStatus === 'loss' || $filterStatus === 'lost') {
    $whereClauses[] = "((m.tbl_match_profit = 0 AND m.tbl_match_cost > 0) OR m.tbl_match_status IN ('loss', 'lost'))";
} else {
    $whereClauses[] = "m.tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')";
}

// Date filter
if ($filterDate === 'current_cycle' || $filterDate === 'since_settlement' || $filterDate === 'Since Last Settlement') {
    if (!empty($lastSettledAt)) {
        // tblmatchplayed uses `created_at` (datetime) and `tbl_updated_at` (timestamp) for reliable comparison
        $whereClauses[] = "(m.created_at >= '{$lastSettledAt}' OR m.tbl_updated_at >= '{$lastSettledAt}')";
    }
} elseif ($filterDate === 'today' || $filterDate === 'Today') {
    $whereClauses[] = "(DATE(m.tbl_updated_at) = CURDATE() OR DATE(m.created_at) = CURDATE() OR m.tbl_time_stamp LIKE CONCAT(DATE_FORMAT(CURDATE(), '%d-%m-%Y'), '%'))";
} elseif ($filterDate === 'yesterday' || $filterDate === 'Yesterday') {
    $whereClauses[] = "(DATE(m.tbl_updated_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) OR DATE(m.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) OR m.tbl_time_stamp LIKE CONCAT(DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%d-%m-%Y'), '%'))";
} elseif ($filterDate === 'last_7_days' || $filterDate === 'Last 7 Days') {
    $whereClauses[] = "(m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR m.tbl_updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))";
} elseif ($filterDate === 'this_month' || $filterDate === 'This Month') {
    $whereClauses[] = "(m.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') OR m.tbl_updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'))";
}

// Game filter
if (!empty($filterGame)) {
    $escapedGame = mysqli_real_escape_string($conn, $filterGame);
    $whereClauses[] = "(m.tbl_project_name LIKE '%{$escapedGame}%' OR m.tbl_provider LIKE '%{$escapedGame}%' OR m.tbl_match_details LIKE '%{$escapedGame}%')";
}

// Search filter
if (!empty($filterSearch)) {
    $escapedSearch = mysqli_real_escape_string($conn, $filterSearch);
    $whereClauses[] = "(m.id LIKE '%{$escapedSearch}%' OR m.tbl_period_id LIKE '%{$escapedSearch}%' OR m.tbl_project_name LIKE '%{$escapedSearch}%' OR m.tbl_provider LIKE '%{$escapedSearch}%' OR u.tbl_user_name LIKE '%{$escapedSearch}%')";
}

$whereSql = implode(' AND ', $whereClauses);

// 4. Aggregated Summary
$summaryQ = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_rounds,
        COALESCE(SUM(m.tbl_match_cost), 0) AS total_bets,
        COALESCE(SUM(m.tbl_match_profit), 0) AS total_wins,
        COALESCE(SUM(CASE WHEN m.tbl_match_status IN ('loss', 'lost') OR m.tbl_match_profit = 0 THEN m.tbl_match_cost ELSE 0 END), 0) AS total_losses
    FROM tblmatchplayed m
    LEFT JOIN tblusersdata u ON (u.id = m.tbl_user_id OR u.tbl_uniq_id = m.tbl_user_id)
    WHERE {$whereSql}
");

$summaryRow = mysqli_fetch_assoc($summaryQ);
$totalBets = (float)($summaryRow['total_bets'] ?? 0.0);
$totalWins = (float)($summaryRow['total_wins'] ?? 0.0);
$totalLosses = (float)($summaryRow['total_losses'] ?? 0.0);
$totalRounds = (int)($summaryRow['total_rounds'] ?? 0);
$netGgr = $totalBets - $totalWins;
$netNgr = max(0, $netGgr * 0.85);
$estRevShare = in_array($dealType, ['revenue_share', 'revshare', 'hybrid']) ? round($netNgr * ($revsharePct / 100.0), 2) : 0.0;

// 5. Paginated Match Rows
$dataQ = mysqli_query($conn, "
    SELECT m.id, m.tbl_user_id, m.tbl_provider, m.tbl_project_name, m.tbl_match_details, m.tbl_period_id,
           m.tbl_match_cost, m.tbl_match_profit, m.tbl_match_status, m.tbl_match_result,
           m.tbl_time_stamp, m.tbl_updated_at, m.created_at,
           u.tbl_user_name, u.tbl_full_name
    FROM tblmatchplayed m
    LEFT JOIN tblusersdata u ON (u.id = m.tbl_user_id OR u.tbl_uniq_id = m.tbl_user_id)
    WHERE {$whereSql}
    ORDER BY m.id DESC
    LIMIT {$offset}, {$limit}
");

$rows = [];
if ($dataQ) {
    while ($r = mysqli_fetch_assoc($dataQ)) {
        $cost = (float)$r['tbl_match_cost'];
        $profit = (float)$r['tbl_match_profit'];
        $netResult = round($profit - $cost, 2); // Player Net Profit (+ = player won, - = house won)
        $houseMargin = round($cost - $profit, 2); // Platform Gross Margin (+ = profit for platform)
        $isLoss = in_array($r['tbl_match_status'], ['loss', 'lost']) || ($profit == 0 && $cost > 0);

        $playerName = $r['tbl_user_name'] ?: ($r['tbl_full_name'] ?: ($uidMap[(string)$r['tbl_user_id']] ?? ('Player #' . $r['tbl_user_id'])));
        $gameName = $r['tbl_project_name'] ?: ($r['tbl_provider'] ?: 'Casino');

        // Formatted timestamp
        $timeStr = !empty($r['created_at']) ? $r['created_at'] : (!empty($r['tbl_updated_at']) ? $r['tbl_updated_at'] : $r['tbl_time_stamp']);

        $rows[] = [
            "id" => (int)$r['id'],
            "period_id" => $r['tbl_period_id'] ?: ('RND-' . $r['id']),
            "player_id" => $r['tbl_user_id'],
            "player_name" => $playerName,
            "game_name" => ucfirst($gameName),
            "project_name" => $r['tbl_project_name'] ?: 'Game',
            "provider" => $r['tbl_provider'] ?: '',
            "match_details" => $r['tbl_match_details'] ?: '',
            "bet_amount" => $cost,
            "win_amount" => $profit,
            "player_net" => $netResult,
            "house_margin" => $houseMargin,
            "status" => $isLoss ? 'LOSS' : 'WIN',
            "game_result" => $r['tbl_match_result'] ?: ($isLoss ? 'Lost' : 'Won'),
            "timestamp" => $timeStr
        ];
    }
}

echo json_encode([
    "status" => "success",
    "summary" => [
        "total_turnover" => round($totalBets, 2),
        "total_wins" => round($totalWins, 2),
        "total_losses" => round($totalLosses, 2),
        "net_ggr" => round($netGgr, 2),
        "net_ngr" => round($netNgr, 2),
        "estimated_revshare" => round($estRevShare, 2),
        "total_rounds" => $totalRounds,
        "revshare_rate" => $revsharePct,
        "last_settled_at" => $lastSettledAt
    ],
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRounds,
        "total_pages" => ceil($totalRounds / max(1, $limit))
    ],
    "referred_players" => array_values($referredPlayers),
    "data" => $rows
]);
