<?php
/**
 * Admin Manual Commission Batch Trigger API Endpoint
 * Endpoint: POST /admin/affiliates/commissions/run/index.php
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../../security/config.php';
require_once __DIR__ . '/../../../access_validate.php';
require_once __DIR__ . '/../../../../services/AffiliateCommissionWorker.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$periodDate = trim($input['period_date'] ?? date('Y-m-d', strtotime('-1 day')));

try {
    $worker = new AffiliateCommissionWorker($conn);
    $result = $worker->runDailyCommissionJob($periodDate);

    echo json_encode([
        "status" => "success",
        "message" => "Commission calculation batch executed successfully",
        "data" => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Commission batch failed: " . $e->getMessage()]);
}
