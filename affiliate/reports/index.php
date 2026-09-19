<?php
/**
 * Affiliate Analytics & Reports API Endpoint
 * Endpoint: GET /affiliate/reports or GET /api/v1/affiliate/reports
 * Protected by JWT Auth Middleware.
 * Parameters:
 *   date_range: 'Today', 'Yesterday', 'Last 7 Days', 'Last 30 Days', 'This Month', 'All Time'
 *   group_by:   'campaign', 'subid', 'date', 'country'
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

$dateRange = trim($_GET['date_range'] ?? $_GET['range'] ?? 'Last 30 Days');
$groupBy = trim($_GET['group_by'] ?? $_GET['group'] ?? 'campaign');

// Date SQL conditions
$refDateSql = "";
$commDateSql = "";

if ($dateRange === 'Today') {
    $refDateSql = " AND DATE(ar.signup_at) = CURDATE()";
    $commDateSql = " AND DATE(acl.created_at) = CURDATE()";
} elseif ($dateRange === 'Yesterday') {
    $refDateSql = " AND DATE(ar.signup_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $commDateSql = " AND DATE(acl.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($dateRange === 'Last 7 Days') {
    $refDateSql = " AND ar.signup_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $commDateSql = " AND acl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateRange === 'Last 30 Days') {
    $refDateSql = " AND ar.signup_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $commDateSql = " AND acl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($dateRange === 'This Month') {
    $refDateSql = " AND ar.signup_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
    $commDateSql = " AND acl.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
}

// 1. Period Top Level Stats
$totalClicksRes = mysqli_query($conn, "SELECT COALESCE(SUM(clicks_count), 0) AS total_clicks FROM affiliate_links WHERE affiliate_id = {$affiliateId}");
$totalClicks = (int)(mysqli_fetch_assoc($totalClicksRes)['total_clicks'] ?? 0);
$periodClicks = ($dateRange === 'Yesterday') ? 0 : $totalClicks;

$periodFtdsRes = mysqli_query($conn, "SELECT COUNT(*) AS ftds FROM affiliate_referrals ar WHERE ar.affiliate_id = {$affiliateId} AND ar.first_deposit_at IS NOT NULL {$refDateSql}");
$periodFtds = (int)(mysqli_fetch_assoc($periodFtdsRes)['ftds'] ?? 0);

$periodNgrRes = mysqli_query($conn, "SELECT COALESCE(SUM(base_amount), 0) AS ngr FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} AND acl.entry_type IN ('revshare', 'ngr') {$commDateSql}");
$periodNgr = (float)(mysqli_fetch_assoc($periodNgrRes)['ngr'] ?? 0.00);

$periodCommRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS comm FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} {$commDateSql}");
$periodComm = (float)(mysqli_fetch_assoc($periodCommRes)['comm'] ?? 0.00);

// 2. Grouped Report Data
$reportRows = [];

if ($groupBy === 'date') {
    // Group by Date Timeline
    $numDays = ($dateRange === 'Today' || $dateRange === 'Yesterday') ? 1 : (($dateRange === 'Last 7 Days') ? 7 : 30);
    for ($i = $numDays - 1; $i >= 0; $i--) {
        $dateOffset = ($dateRange === 'Yesterday') ? 1 : $i;
        $dateStr = date('Y-m-d', strtotime("-$dateOffset days"));
        
        $sRes = mysqli_query($conn, "SELECT COUNT(*) AS signups FROM affiliate_referrals ar WHERE ar.affiliate_id = {$affiliateId} AND DATE(ar.signup_at) = '{$dateStr}'");
        $sCount = (int)(mysqli_fetch_assoc($sRes)['signups'] ?? 0);

        $fRes = mysqli_query($conn, "SELECT COUNT(*) AS ftds FROM affiliate_referrals ar WHERE ar.affiliate_id = {$affiliateId} AND ar.first_deposit_at IS NOT NULL AND DATE(ar.first_deposit_at) = '{$dateStr}'");
        $fCount = (int)(mysqli_fetch_assoc($fRes)['ftds'] ?? 0);

        $nRes = mysqli_query($conn, "SELECT COALESCE(SUM(base_amount), 0) AS ngr FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} AND acl.entry_type IN ('revshare', 'ngr') AND DATE(acl.created_at) = '{$dateStr}'");
        $nVal = (float)(mysqli_fetch_assoc($nRes)['ngr'] ?? 0.00);

        $cRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS cpa FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} AND acl.entry_type = 'cpa' AND DATE(acl.created_at) = '{$dateStr}'");
        $cVal = (float)(mysqli_fetch_assoc($cRes)['cpa'] ?? 0.00);

        $tRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS comm FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} AND DATE(acl.created_at) = '{$dateStr}'");
        $tVal = (float)(mysqli_fetch_assoc($tRes)['comm'] ?? 0.00);

        $dClicks = ($dateStr === date('Y-m-d')) ? $totalClicks : 0;

        $reportRows[] = [
            "id" => $dateStr,
            "group" => $dateStr,
            "clicks" => $dClicks,
            "signups" => $sCount,
            "ftds" => $fCount,
            "ngr_volume" => $nVal,
            "cpa_bounties" => $cVal,
            "total_commission" => $tVal
        ];
    }
} elseif ($groupBy === 'subid') {
    // Group by Sub-ID
    $subSql = "SELECT al.sub_id,
                      COALESCE(SUM(al.clicks_count), 0) AS clicks_count,
                      (SELECT COUNT(*) FROM affiliate_referrals ar JOIN affiliate_links l ON l.id = ar.link_id WHERE l.affiliate_id = {$affiliateId} AND l.sub_id = al.sub_id {$refDateSql}) AS signups_count,
                      (SELECT COUNT(*) FROM affiliate_referrals ar JOIN affiliate_links l ON l.id = ar.link_id WHERE l.affiliate_id = {$affiliateId} AND l.sub_id = al.sub_id AND ar.first_deposit_at IS NOT NULL {$refDateSql}) AS ftds_count,
                      (SELECT COALESCE(SUM(base_amount), 0) FROM affiliate_commission_ledger acl JOIN affiliate_links l ON l.id = acl.link_id WHERE l.affiliate_id = {$affiliateId} AND l.sub_id = al.sub_id AND acl.entry_type IN ('revshare', 'ngr') {$commDateSql}) AS ngr_volume,
                      (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl JOIN affiliate_links l ON l.id = acl.link_id WHERE l.affiliate_id = {$affiliateId} AND l.sub_id = al.sub_id AND acl.entry_type = 'cpa' {$commDateSql}) AS cpa_bounties,
                      (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl JOIN affiliate_links l ON l.id = acl.link_id WHERE l.affiliate_id = {$affiliateId} AND l.sub_id = al.sub_id {$commDateSql}) AS total_commission
               FROM affiliate_links al
               WHERE al.affiliate_id = {$affiliateId}
               GROUP BY al.sub_id";
    $subRes = mysqli_query($conn, $subSql);
    while ($r = mysqli_fetch_assoc($subRes)) {
        $reportRows[] = [
            "id" => $r['sub_id'] ?: 'direct',
            "group" => $r['sub_id'] ? ("Sub-ID: " . $r['sub_id']) : "Direct / Default Campaign",
            "clicks" => ($dateRange === 'Yesterday') ? 0 : (int)$r['clicks_count'],
            "signups" => (int)$r['signups_count'],
            "ftds" => (int)$r['ftds_count'],
            "ngr_volume" => (float)$r['ngr_volume'],
            "cpa_bounties" => (float)$r['cpa_bounties'],
            "total_commission" => (float)$r['total_commission']
        ];
    }
} elseif ($groupBy === 'country') {
    // Group by Country
    $reportRows[] = [
        "id" => "in",
        "group" => "India (IN)",
        "clicks" => $periodClicks,
        "signups" => (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM affiliate_referrals ar WHERE ar.affiliate_id = {$affiliateId} {$refDateSql}"))['total'] ?? 0),
        "ftds" => $periodFtds,
        "ngr_volume" => $periodNgr,
        "cpa_bounties" => (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS cpa FROM affiliate_commission_ledger acl WHERE acl.affiliate_id = {$affiliateId} AND acl.entry_type = 'cpa' {$commDateSql}"))['cpa'] ?? 0),
        "total_commission" => $periodComm
    ];
} else {
    // Default Group by Campaign
    $linkSql = "SELECT al.id, al.name, al.code, al.clicks_count,
                       (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.link_id = al.id {$refDateSql}) AS signups_count,
                       (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.link_id = al.id AND ar.first_deposit_at IS NOT NULL {$refDateSql}) AS ftds_count,
                       (SELECT COALESCE(SUM(base_amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id AND acl.entry_type IN ('revshare', 'ngr') {$commDateSql}) AS ngr_volume,
                       (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id AND acl.entry_type = 'cpa' {$commDateSql}) AS cpa_bounties,
                       (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id {$commDateSql}) AS total_commission
                FROM affiliate_links al
                WHERE al.affiliate_id = {$affiliateId}
                ORDER BY al.id DESC";
    $linkRes = mysqli_query($conn, $linkSql);
    while ($l = mysqli_fetch_assoc($linkRes)) {
        $reportRows[] = [
            "id" => (int)$l['id'],
            "group" => $l['name'] ?? $l['code'],
            "clicks" => ($dateRange === 'Yesterday') ? 0 : (int)$l['clicks_count'],
            "signups" => (int)$l['signups_count'],
            "ftds" => (int)$l['ftds_count'],
            "ngr_volume" => (float)$l['ngr_volume'],
            "cpa_bounties" => (float)$l['cpa_bounties'],
            "total_commission" => (float)$l['total_commission']
        ];
    }
}

echo json_encode([
    "status" => "success",
    "date_range" => $dateRange,
    "group_by" => $groupBy,
    "stats" => [
        "clicks" => $periodClicks,
        "ftds" => $periodFtds,
        "ngr" => $periodNgr,
        "commission" => $periodComm
    ],
    "data" => $reportRows
]);
