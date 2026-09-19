<?php
/**
 * Affiliate Preferences Endpoint
 * Endpoint: GET|POST /affiliate/preferences or /api/v1/affiliate/preferences
 * Allows affiliates to fetch and save their interface & notification preferences.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    $currency = trim($data['currency'] ?? 'INR');
    $timezone = trim($data['timezone'] ?? 'Asia/Kolkata');
    $language = trim($data['language'] ?? 'en');
    $emailNotif = !empty($data['email_notifications']) ? 1 : 0;
    $smsNotif = !empty($data['sms_notifications']) ? 1 : 0;
    $mktUpdates = !empty($data['marketing_updates']) ? 1 : 0;

    $sql = "INSERT INTO affiliate_preferences 
            (affiliate_id, currency, timezone, language, email_notifications, sms_notifications, marketing_updates) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            currency = VALUES(currency), 
            timezone = VALUES(timezone), 
            language = VALUES(language), 
            email_notifications = VALUES(email_notifications), 
            sms_notifications = VALUES(sms_notifications), 
            marketing_updates = VALUES(marketing_updates)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssiii", $affiliateId, $currency, $timezone, $language, $emailNotif, $smsNotif, $mktUpdates);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Preferences saved successfully.",
            "data" => [
                "currency" => $currency,
                "timezone" => $timezone,
                "language" => $language,
                "email_notifications" => (bool)$emailNotif,
                "sms_notifications" => (bool)$smsNotif,
                "marketing_updates" => (bool)$mktUpdates
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to save preferences: " . mysqli_error($conn)]);
        exit;
    }
}

// GET Preferences
$stmt = mysqli_prepare($conn, "SELECT currency, timezone, language, email_notifications, sms_notifications, marketing_updates FROM affiliate_preferences WHERE affiliate_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $affiliateId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$pref = mysqli_fetch_assoc($res);

if (!$pref) {
    // Default preferences
    $pref = [
        "currency" => "INR",
        "timezone" => "Asia/Kolkata",
        "language" => "en",
        "email_notifications" => 1,
        "sms_notifications" => 1,
        "marketing_updates" => 0
    ];
}

echo json_encode([
    "status" => "success",
    "data" => [
        "currency" => $pref['currency'],
        "timezone" => $pref['timezone'],
        "language" => $pref['language'],
        "email_notifications" => (bool)$pref['email_notifications'],
        "sms_notifications" => (bool)$pref['sms_notifications'],
        "marketing_updates" => (bool)$pref['marketing_updates']
    ]
]);
