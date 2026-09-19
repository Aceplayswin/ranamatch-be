<?php
/**
 * Affiliate Network / Downline Sub-Affiliates API Endpoint
 * Endpoint: GET /affiliate/network
 * Returns recruited sub-affiliates, multi-tier stats, and network downline performance.
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

// 1. Fetch current affiliate identity details
$meStmt = mysqli_prepare($conn, "SELECT id, affiliate_code, full_name, email, tier, status FROM affiliates WHERE id = ?");
mysqli_stmt_bind_param($meStmt, "i", $affiliateId);
mysqli_stmt_execute($meStmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($meStmt));

if (!$me) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Affiliate account not found."]);
    exit;
}

$affiliateCode = $me['affiliate_code'] ?? '';

// 2. Fetch direct recruited sub-affiliates (where parent_id = $affiliateId)
$subSql = "SELECT 
            a.id, 
            a.affiliate_code, 
            a.full_name, 
            a.email, 
            a.phone, 
            a.company_name, 
            a.tier, 
            a.status, 
            a.created_at,
            (SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_id = a.id) AS total_players,
            (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger WHERE affiliate_id = a.id) AS sub_earnings
          FROM affiliates a
          WHERE a.parent_id = ?
          ORDER BY a.created_at DESC, a.id DESC";

$sStmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($sStmt, "i", $affiliateId);
mysqli_stmt_execute($sStmt);
$subRows = mysqli_fetch_all(mysqli_stmt_get_result($sStmt), MYSQLI_ASSOC);

$totalSubAffiliates = count($subRows);
$activeSubAffiliates = 0;
$totalNetworkPlayers = 0;
$totalSubEarnings = 0.0;
$overrideRatePct = 5.0; // Standard 5% Tier-2 override commission

$formattedSubs = array_map(function($r) use (&$activeSubAffiliates, &$totalNetworkPlayers, &$totalSubEarnings, $overrideRatePct) {
    $isActive = ($r['status'] === 'active' || $r['status'] === 'approved');
    if ($isActive) $activeSubAffiliates++;
    
    $players = (int)($r['total_players'] ?? 0);
    $totalNetworkPlayers += $players;

    $earnings = (float)($r['sub_earnings'] ?? 0.0);
    $totalSubEarnings += $earnings;

    $overrideEarned = round($earnings * ($overrideRatePct / 100), 2);

    $created = !empty($r['created_at']) ? strtotime($r['created_at']) : time();
    $dateStr = date('M d, Y', $created);

    return [
        "id" => (int)$r['id'],
        "affiliate_code" => $r['affiliate_code'],
        "name" => !empty($r['full_name']) ? $r['full_name'] : (!empty($r['company_name']) ? $r['company_name'] : ('Sub-Affiliate #' . $r['id'])),
        "company_name" => $r['company_name'] ?? '',
        "email" => $r['email'] ?? 'N/A',
        "phone" => $r['phone'] ?? '',
        "tier" => ucfirst($r['tier'] ?? 'Bronze'),
        "status" => $r['status'] ?? 'pending',
        "is_active" => $isActive,
        "players_count" => $players,
        "override_rate" => number_format($overrideRatePct, 1) . "%",
        "sub_volume" => $earnings,
        "override_earnings" => $overrideEarned,
        "joined_date" => $dateStr,
        "created_at" => $r['created_at']
    ];
}, $subRows);

$totalOverrideEarnings = round($totalSubEarnings * ($overrideRatePct / 100), 2);

echo json_encode([
    "status" => "success",
    "data" => [
        "affiliate_code" => $affiliateCode,
        "stats" => [
            "total_sub_affiliates" => $totalSubAffiliates,
            "active_sub_affiliates" => $activeSubAffiliates,
            "total_network_players" => $totalNetworkPlayers,
            "override_rate" => number_format($overrideRatePct, 1) . "%",
            "total_override_earnings" => $totalOverrideEarnings
        ],
        "sub_affiliates" => $formattedSubs
    ]
]);
