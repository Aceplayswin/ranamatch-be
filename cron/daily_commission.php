<?php
/**
 * Daily Automated Commission Cron Worker
 * Script: cron/daily_commission.php
 * Executed every night at 01:00 UTC by system scheduler.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../services/AffiliateCommissionWorker.php';

header('Content-Type: application/json; charset=utf-8');

// Require security token for HTTP invocation
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expectedToken = defined('CRON_SECURITY_TOKEN') ? CRON_SECURITY_TOKEN : 'VELPLAY_CRON_SECURE_TOKEN_2026';

if (php_sapi_name() !== 'cli' && $token !== $expectedToken) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized cron token."]);
    exit;
}

$periodDate = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d', strtotime('-1 day'));

try {
    $worker = new AffiliateCommissionWorker($conn);
    $result = $worker->runDailyCommissionJob($periodDate);

    // Optional log to file
    $logMsg = "[" . date('Y-m-d H:i:s') . "] CRON RUN ($periodDate): Processed {$result['records_processed']} records, Generated ₹{$result['total_commission_generated']} commission.\n";
    file_put_contents(__DIR__ . '/cron_execution.log', $logMsg, FILE_APPEND);

    echo json_encode([
        "status" => "success",
        "message" => "Daily commission cron batch completed successfully",
        "data" => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Cron execution error: " . $e->getMessage()]);
}
