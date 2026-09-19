<?php
/**
 * Affiliate Link Click Tracking Endpoint
 * Endpoints: 
 *   GET  /affiliate/track?ref=CODE
 *   POST /affiliate/track  (body: { "ref": "CODE" })
 *   GET  /api/v1/affiliate/track?ref=CODE
 *   POST /api/v1/affiliate/track/click
 *
 * Increments clicks_count on affiliate_links and optionally redirects.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Website-Port");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

// Get ref parameter from GET, POST JSON, or query string
$ref = trim($_GET['ref'] ?? $_GET['code'] ?? '');
if (empty($ref) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $ref = trim($input['ref'] ?? $input['code'] ?? '');
}

if (empty($ref)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Referral code parameter 'ref' or 'code' is required."
    ]);
    exit;
}

$updatedClicks = 0;
$foundType = null;
$targetPath = '/register';
$affiliateId = null;

// 1. Check if ref matches an affiliate campaign link
$stmt = mysqli_prepare($conn, "SELECT id, affiliate_id, name, code, target_path, clicks_count FROM affiliate_links WHERE code = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $ref);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$link = mysqli_fetch_assoc($res);

if ($link) {
    $foundType = 'campaign_link';
    $linkId = (int)$link['id'];
    $affiliateId = (int)$link['affiliate_id'];
    $targetPath = $link['target_path'] ?: '/register';

    // Increment clicks_count
    $upStmt = mysqli_prepare($conn, "UPDATE affiliate_links SET clicks_count = clicks_count + 1 WHERE id = ?");
    mysqli_stmt_bind_param($upStmt, "i", $linkId);
    mysqli_stmt_execute($upStmt);

    $updatedClicks = (int)$link['clicks_count'] + 1;
} else {
    // 2. Check if ref matches an affiliate's direct code
    $stmtAff = mysqli_prepare($conn, "SELECT id, affiliate_code, full_name, status FROM affiliates WHERE affiliate_code = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtAff, "s", $ref);
    mysqli_stmt_execute($stmtAff);
    $aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAff));

    if ($aff) {
        $foundType = 'affiliate_direct';
        $affiliateId = (int)$aff['id'];

        // Check if affiliate has a default link or create one to record clicks
        $defStmt = mysqli_prepare($conn, "SELECT id, clicks_count FROM affiliate_links WHERE affiliate_id = ? ORDER BY id ASC LIMIT 1");
        mysqli_stmt_bind_param($defStmt, "i", $affiliateId);
        mysqli_stmt_execute($defStmt);
        $defLink = mysqli_fetch_assoc(mysqli_stmt_get_result($defStmt));

        if ($defLink) {
            $dId = (int)$defLink['id'];
            $upStmt = mysqli_prepare($conn, "UPDATE affiliate_links SET clicks_count = clicks_count + 1 WHERE id = ?");
            mysqli_stmt_bind_param($upStmt, "i", $dId);
            mysqli_stmt_execute($upStmt);
            $updatedClicks = (int)$defLink['clicks_count'] + 1;
        } else {
            // Create default link
            $insStmt = mysqli_prepare($conn, "INSERT INTO affiliate_links (affiliate_id, name, code, clicks_count) VALUES (?, 'Default Referral', ?, 1)");
            mysqli_stmt_bind_param($insStmt, "is", $affiliateId, $ref);
            mysqli_stmt_execute($insStmt);
            $updatedClicks = 1;
        }
    }
}

if (!$foundType) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Referral code not found: " . htmlspecialchars($ref)
    ]);
    exit;
}

// Check if browser requested direct redirect
if (isset($_GET['redirect']) && $_GET['redirect'] == '1') {
    $port = preg_replace('/[^0-9]/', '', $_GET['port'] ?? '5173') ?: '5173';
    $dest = "http://localhost:{$port}" . $targetPath . (strpos($targetPath, '?') !== false ? '&' : '?') . "ref=" . urlencode($ref);
    header("Location: $dest");
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Click tracked successfully",
    "data" => [
        "code" => $ref,
        "type" => $foundType,
        "affiliate_id" => $affiliateId,
        "target_path" => $targetPath,
        "clicks_count" => $updatedClicks
    ]
]);
