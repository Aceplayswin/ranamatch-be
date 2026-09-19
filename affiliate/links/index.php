<?php
/**
 * Affiliate Links API Endpoint
 * Endpoint: GET /affiliate/links  or  POST /affiliate/links
 * Protected by JWT Auth Middleware. List or create campaign tracking links.
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

// Resolve Website Base URL for generated links (supports local testing ports like 5173)
$requestedPort = $_SERVER['HTTP_X_WEBSITE_PORT'] 
    ?? $_GET['website_port'] 
    ?? '5173';
$cleanPort = preg_replace('/[^0-9]/', '', (string)$requestedPort) ?: '5173';

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']) 
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
    || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;

if (!empty($_SERVER['HTTP_X_WEBSITE_BASE_URL'])) {
    $baseUrl = rtrim($_SERVER['HTTP_X_WEBSITE_BASE_URL'], '/');
} elseif (!empty($_GET['website_base_url'])) {
    $baseUrl = rtrim($_GET['website_base_url'], '/');
} elseif ($isLocal) {
    $baseUrl = "http://localhost:" . $cleanPort;
} else {
    $baseUrl = "https://velplay365.com";
}

$buildTrackingUrl = function($targetPath, $code, $subId = null) use ($baseUrl) {
    $path = trim($targetPath ?? '');
    if (empty($path) || $path === '/') {
        $path = '/register';
    } else {
        $path = str_starts_with($path, '/') ? $path : "/$path";
    }
    $sep = (strpos($path, '?') !== false) ? '&' : '?';
    $url = $baseUrl . $path . $sep . "ref=" . urlencode($code);
    if (!empty($subId)) {
        $url .= "&sub=" . urlencode($subId);
    }
    return $url;
};

// GET: List campaign tracking links
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT al.id, al.affiliate_id, al.name, al.code, al.sub_id, al.target_path, al.clicks_count, al.created_at,
                   (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.link_id = al.id) AS signups_count,
                   (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.link_id = al.id AND ar.first_deposit_at IS NOT NULL) AS ftds_count,
                   (SELECT COALESCE(SUM(base_amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id AND acl.entry_type IN ('revshare', 'ngr')) AS ngr_volume,
                   (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id AND acl.entry_type = 'cpa') AS cpa_bounties,
                   (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger acl WHERE acl.link_id = al.id) AS total_commission
            FROM affiliate_links al
            WHERE al.affiliate_id = ?
            ORDER BY al.created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $affiliateId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $links = mysqli_fetch_all($res, MYSQLI_ASSOC);

    $formattedLinks = array_map(function($l) use ($buildTrackingUrl) {
        return [
            "id" => (int)$l['id'],
            "name" => $l['name'] ?? $l['code'],
            "code" => $l['code'],
            "sub_id" => $l['sub_id'] ?? null,
            "target_path" => $l['target_path'] ?? '/',
            "tracking_url" => $buildTrackingUrl($l['target_path'] ?? '/', $l['code'], $l['sub_id'] ?? null),
            "clicks_count" => (int)($l['clicks_count'] ?? 0),
            "signups_count" => (int)($l['signups_count'] ?? 0),
            "ftds_count" => (int)($l['ftds_count'] ?? 0),
            "ngr_volume" => (float)($l['ngr_volume'] ?? 0),
            "cpa_bounties" => (float)($l['cpa_bounties'] ?? 0),
            "total_commission" => (float)($l['total_commission'] ?? 0),
            "created_at" => $l['created_at']
        ];
    }, $links);

    echo json_encode([
        "status" => "success",
        "website_base_url" => $baseUrl,
        "count" => count($formattedLinks),
        "data" => $formattedLinks
    ]);
    exit;
}

// POST: Create new campaign tracking link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $campaignName = trim($input['campaign_name'] ?? $input['name'] ?? '');
    $targetPath = trim($input['target_path'] ?? '/');
    $subId = trim($input['sub_id'] ?? '');

    if (!empty($input['website_port'])) {
        $cleanPort = preg_replace('/[^0-9]/', '', (string)$input['website_port']) ?: $cleanPort;
        if ($isLocal && empty($_SERVER['HTTP_X_WEBSITE_BASE_URL'])) {
            $baseUrl = "http://localhost:" . $cleanPort;
        }
    }

    if (empty($campaignName)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Campaign name is required."]);
        exit;
    }

    $linkCode = 'LNK-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $sql = "INSERT INTO affiliate_links (affiliate_id, name, code, sub_id, target_path, clicks_count) 
            VALUES (?, ?, ?, ?, ?, 0)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issss", $affiliateId, $campaignName, $linkCode, $subId, $targetPath);

    if (mysqli_stmt_execute($stmt)) {
        $newLinkId = mysqli_insert_id($conn);
        $trackingUrl = $buildTrackingUrl($targetPath, $linkCode, $subId);

        echo json_encode([
            "status" => "success",
            "message" => "Campaign tracking link created successfully",
            "data" => [
                "id" => $newLinkId,
                "name" => $campaignName,
                "code" => $linkCode,
                "target_path" => $targetPath,
                "sub_id" => $subId,
                "tracking_url" => $trackingUrl
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create tracking link: " . mysqli_error($conn)]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
