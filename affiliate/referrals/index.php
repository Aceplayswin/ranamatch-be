<?php
/**
 * Affiliate Referrals API Endpoint
 * Endpoint: GET /affiliate/referrals or /api/v1/affiliate/referrals
 * Protected by JWT Auth Middleware.
 * Returns paginated list of referred players (direct + sub-affiliate downlines)
 * with complete financial metrics (balance, deposits, withdrawals, bets, wins, losses, commission)
 * and settlement cycle watermark awareness (fresh calculation starting from last_settled_at).
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// Fetch affiliate settlement watermark
$affQ = mysqli_query($conn, "SELECT id, affiliate_code, full_name, last_settled_at FROM affiliates WHERE id = {$affiliateId} LIMIT 1");
$affData = mysqli_fetch_assoc($affQ);
$lastSettledAt = $affData['last_settled_at'] ?? null;
$settlementCycle = 'weekly_monday';

$cycle = strtolower(trim($_GET['cycle'] ?? 'all'));
$search = trim($_GET['search'] ?? '');
$filterSource = trim($_GET['source'] ?? 'all'); // 'all', 'direct', 'sub'

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Build date boundary clause if cycle=current and lastSettledAt is present
$dateDepClause = "";
$dateWdClause = "";
$dateBetClause = "";
$dateCommClause = "";

if ($cycle === 'current' && !empty($lastSettledAt)) {
    // tblmatchplayed uses `created_at` (datetime column)
    $dateBetClause = " AND created_at >= '{$lastSettledAt}'";
    // tblusersrecharge.tbl_time_stamp is varchar ('DD-MM-YYYY HH:MM AM/PM') - use STR_TO_DATE
    $dateDepClause = " AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= '{$lastSettledAt}'";
    // tbluserswithdraw.tbl_time_stamp is same varchar format
    $dateWdClause = " AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= '{$lastSettledAt}'";
    $dateCommClause = " AND created_at >= '{$lastSettledAt}'";
}

// Base WHERE condition: direct referrals OR downline sub-affiliate referrals
$whereConditions = ["(ar.affiliate_id = {$affiliateId} OR sub_af.parent_id = {$affiliateId})"];

if ($filterSource === 'direct') {
    $whereConditions[] = "ar.affiliate_id = {$affiliateId}";
} elseif ($filterSource === 'sub') {
    $whereConditions[] = "sub_af.parent_id = {$affiliateId} AND ar.affiliate_id != {$affiliateId}";
}

if (!empty($search)) {
    $escSearch = mysqli_real_escape_string($conn, $search);
    $whereConditions[] = "(u.tbl_user_name LIKE '%{$escSearch}%' OR u.tbl_full_name LIKE '%{$escSearch}%' OR u.tbl_uniq_id LIKE '%{$escSearch}%' OR sub_af.affiliate_code LIKE '%{$escSearch}%' OR sub_af.full_name LIKE '%{$escSearch}%')";
}

$whereStr = implode(" AND ", $whereConditions);

// Total records count
$countSql = "SELECT COUNT(DISTINCT ar.id) AS total 
             FROM affiliate_referrals ar
             JOIN affiliates sub_af ON sub_af.id = ar.affiliate_id
             LEFT JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
             WHERE {$whereStr}";
$countRes = mysqli_query($conn, $countSql);
$totalRecords = ($cRow = mysqli_fetch_assoc($countRes)) ? (int)$cRow['total'] : 0;

// Referrals Query with live player balance and financial metrics
$sql = "SELECT ar.id AS referral_id, ar.affiliate_id, ar.user_id, ar.signup_at, ar.first_deposit_at, ar.first_deposit_amount, ar.is_cpa_qualified,
               u.id AS internal_user_id, u.tbl_uniq_id, u.tbl_user_name, u.tbl_full_name, u.tbl_email_id, u.tbl_mobile_num, u.tbl_balance, u.tbl_account_status, u.tbl_user_joined,
               al.name AS campaign_name, al.code AS tracking_code,
               sub_af.id AS sub_aff_id, sub_af.affiliate_code AS sub_aff_code, sub_af.full_name AS sub_aff_name, sub_af.parent_id AS sub_parent_id,
               DATEDIFF(NOW(), ar.signup_at) AS days_since_signup
        FROM affiliate_referrals ar
        JOIN affiliates sub_af ON sub_af.id = ar.affiliate_id
        LEFT JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
        LEFT JOIN affiliate_links al ON al.id = ar.link_id
        WHERE {$whereStr}
        ORDER BY ar.signup_at DESC, ar.id DESC
        LIMIT {$offset}, {$limit}";

$res = mysqli_query($conn, $sql);
$rows = [];

$summary = [
    "total_players" => $totalRecords,
    "total_deposits" => 0.00,
    "total_withdrawals" => 0.00,
    "total_turnover" => 0.00,
    "total_wins" => 0.00,
    "total_losses" => 0.00,
    "total_commission" => 0.00
];

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $uniqId = $r['tbl_uniq_id'] ?? '';
        $internalId = (int)($r['internal_user_id'] ?? $r['user_id']);
        $refId = (int)$r['referral_id'];

        $currentBalance = (float)($r['tbl_balance'] ?? 0.00);

        // Player total deposits
        $dep = 0.00;
        if (!empty($uniqId)) {
            $depQ = mysqli_query($conn, "SELECT COALESCE(SUM(tbl_recharge_amount), 0) AS total FROM tblusersrecharge WHERE tbl_user_id = '{$uniqId}' AND tbl_request_status = 'success' {$dateDepClause}");
            if ($depRow = mysqli_fetch_assoc($depQ)) $dep = (float)$depRow['total'];
        }

        // Player total withdrawals
        $wit = 0.00;
        if (!empty($uniqId)) {
            $witQ = mysqli_query($conn, "SELECT COALESCE(SUM(tbl_withdraw_amount), 0) AS total FROM tbluserswithdraw WHERE tbl_user_id = '{$uniqId}' AND tbl_request_status = 'success' {$dateWdClause}");
            if ($witRow = mysqli_fetch_assoc($witQ)) $wit = (float)$witRow['total'];
        }

        // Player bets, wins, and losses
        $bets = 0.00;
        $wins = 0.00;
        $losses = 0.00;
        if (!empty($uniqId)) {
            $betQ = mysqli_query($conn, "SELECT 
                        COALESCE(SUM(tbl_match_cost), 0) AS total_bet,
                        COALESCE(SUM(tbl_match_profit), 0) AS total_win,
                        COALESCE(SUM(CASE WHEN tbl_match_profit = 0 THEN tbl_match_cost 
                                          WHEN tbl_match_profit < tbl_match_cost THEN (tbl_match_cost - tbl_match_profit) 
                                          ELSE 0 END), 0) AS total_loss
                    FROM tblmatchplayed 
                    WHERE tbl_user_id = '{$uniqId}' {$dateBetClause}");
            if ($betRow = mysqli_fetch_assoc($betQ)) {
                $bets = (float)$betRow['total_bet'];
                $wins = (float)$betRow['total_win'];
                $losses = (float)$betRow['total_loss'];
            }
        }

        // Commission earned from this player by this affiliate (direct revshare/cpa or sub-affiliate override)
        $commQ = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_comm 
                                      FROM affiliate_commission_ledger 
                                      WHERE affiliate_id = {$affiliateId} 
                                        AND (referral_id = {$refId} OR source_affiliate_id = {$r['affiliate_id']}) {$dateCommClause}");
        $comm = ($cRow = mysqli_fetch_assoc($commQ)) ? (float)$cRow['total_comm'] : 0.00;

        $hasFtd = !empty($r['first_deposit_at']) || (float)($r['first_deposit_amount'] ?? 0) > 0 || $dep > 0;
        $days = (int)($r['days_since_signup'] ?? 0);
        $status = 'signup';
        if ($hasFtd) {
            $status = 'ftd';
        } elseif ($days > 14) {
            $status = 'dormant';
        }

        // Is this referred via a sub-affiliate downline?
        $isSubAffiliate = ((int)$r['affiliate_id'] !== $affiliateId);
        $subAffiliateInfo = null;
        if ($isSubAffiliate) {
            $subAffiliateInfo = [
                "id" => (int)$r['sub_aff_id'],
                "code" => $r['sub_aff_code'],
                "name" => $r['sub_aff_name'] ?: ('Sub-Affiliate #' . $r['sub_aff_id'])
            ];
        }

        $summary["total_deposits"] += $dep;
        $summary["total_withdrawals"] += $wit;
        $summary["total_turnover"] += $bets;
        $summary["total_wins"] += $wins;
        $summary["total_losses"] += $losses;
        $summary["total_commission"] += $comm;

        $rows[] = [
            "id" => $refId,
            "user_id" => $r['user_id'],
            "uniq_id" => $uniqId,
            "username" => $r['tbl_user_name'] ?? ('Player #' . $r['user_id']),
            "full_name" => $r['tbl_full_name'] ?? '',
            "user_email" => $r['tbl_email_id'] ?? '',
            "mobile" => $r['tbl_mobile_num'] ?? '',
            "current_balance" => $currentBalance,
            "total_deposits" => $dep,
            "total_withdrawals" => $wit,
            "total_bets" => $bets,
            "total_wins" => $wins,
            "total_losses" => $losses,
            "player_commission" => $comm,
            "campaign_name" => $r['campaign_name'] ?? 'Direct Referral',
            "tracking_code" => $r['tracking_code'] ?? null,
            "is_sub_affiliate" => $isSubAffiliate,
            "sub_affiliate" => $subAffiliateInfo,
            "attributed_at" => $r['signup_at'] ?? 'N/A',
            "first_deposit_at" => $r['first_deposit_at'],
            "first_deposit_amount" => (float)($r['first_deposit_amount'] ?? 0.00),
            "is_ftd" => $hasFtd,
            "is_cpa_qualified" => (bool)($r['is_cpa_qualified'] ?? false),
            "status" => $status,
            "days_since_signup" => $days,
            "account_status" => $r['tbl_account_status'] ?? 'active'
        ];
    }
}

echo json_encode([
    "status" => "success",
    "cycle" => $cycle,
    "last_settled_at" => $lastSettledAt,
    "settlement_cycle" => $settlementCycle,
    "summary" => $summary,
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRecords,
        "total_pages" => ceil($totalRecords / max(1, $limit))
    ],
    "data" => $rows
]);
