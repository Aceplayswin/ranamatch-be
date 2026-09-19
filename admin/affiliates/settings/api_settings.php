<?php
/**
 * Admin Affiliate Program Settings API Endpoint
 * Endpoint: GET /admin/affiliates/settings/api_settings.php  or  PUT /admin/affiliates/settings/api_settings.php
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

$accessObj = new AccessValidate();
$isAuthorized = ($accessObj->validate() === "true");

if (!$isAuthorized && (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session expired. Please re-login to the Admin Panel."]);
    exit;
}

// GET: Fetch Program Settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = mysqli_query($conn, "SELECT * FROM program_settings WHERE id = 1");
    $settings = mysqli_fetch_assoc($res);

    echo json_encode([
        "status" => "success",
        "data" => [
            "affiliate_default_tier" => $settings['affiliate_default_tier'] ?? 'bronze',
            "affiliate_default_revshare_pct" => (float)($settings['affiliate_default_revshare_pct'] ?? 35.00),
            "affiliate_default_cpa_amount" => (float)($settings['affiliate_default_cpa_amount'] ?? 50.00),
            "affiliate_cpa_min_deposit_threshold" => (float)($settings['affiliate_cpa_min_deposit_threshold'] ?? 100.00),
            "affiliate_cookie_duration_days" => (int)($settings['affiliate_cookie_duration_days'] ?? 30),
            "affiliate_minimum_payout" => (float)($settings['affiliate_minimum_payout'] ?? 1000.00),
            "affiliate_payout_hold_days" => (int)($settings['affiliate_payout_hold_days'] ?? 7)
        ]
    ]);
    exit;
}

// PUT / POST: Update Program Settings
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $tier = trim($input['affiliate_default_tier'] ?? 'bronze');
    $revsharePct = (float)($input['affiliate_default_revshare_pct'] ?? 35.00);
    $cpaAmount = (float)($input['affiliate_default_cpa_amount'] ?? 50.00);
    $cpaThreshold = (float)($input['affiliate_cpa_min_deposit_threshold'] ?? 100.00);
    $cookieDays = (int)($input['affiliate_cookie_duration_days'] ?? 30);
    $minPayout = (float)($input['affiliate_minimum_payout'] ?? 1000.00);
    $holdDays = (int)($input['affiliate_payout_hold_days'] ?? 7);

    $sql = "UPDATE program_settings 
            SET affiliate_default_tier = ?, affiliate_default_revshare_pct = ?, affiliate_default_cpa_amount = ?, affiliate_cpa_min_deposit_threshold = ?, affiliate_cookie_duration_days = ?, affiliate_minimum_payout = ?, affiliate_payout_hold_days = ? 
            WHERE id = 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdddidi", $tier, $revsharePct, $cpaAmount, $cpaThreshold, $cookieDays, $minPayout, $holdDays);
    mysqli_stmt_execute($stmt);

    echo json_encode([
        "status" => "success",
        "message" => "Affiliate program settings updated successfully"
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
