<?php
/**
 * Admin Agent Program Settings API Endpoint
 * Endpoint: GET /admin/agents/settings/api_settings.php  or  PUT /admin/agents/settings/api_settings.php
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

// GET: Fetch Program Settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = mysqli_query($conn, "SELECT * FROM program_settings WHERE id = 1");
    $settings = mysqli_fetch_assoc($res);

    echo json_encode([
        "status" => "success",
        "data" => [
            "agent_default_level" => $settings['agent_default_level'] ?? 'agent',
            "agent_review_time_hours" => (int)($settings['agent_review_time_hours'] ?? 24),
            "agent_default_partnership_pct" => (float)($settings['agent_default_partnership_pct'] ?? 50.00),
            "agent_default_commission_rate_pct" => (float)($settings['agent_default_commission_rate_pct'] ?? 2.50),
            "agent_default_opening_credit" => (float)($settings['agent_default_opening_credit'] ?? 0.00),
            "agent_settlement_cycle" => $settings['agent_settlement_cycle'] ?? 'weekly_monday',
            "agent_minimum_payout" => (float)($settings['agent_minimum_payout'] ?? 1000.00),
            "agent_negative_carryover" => (int)($settings['agent_negative_carryover'] ?? 1)
        ]
    ]);
    exit;
}

// PUT / POST: Update Program Settings
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $level = trim($input['agent_default_level'] ?? 'agent');
    $reviewHours = (int)($input['agent_review_time_hours'] ?? 24);
    $partnershipPct = (float)($input['agent_default_partnership_pct'] ?? 50.00);
    $commissionPct = (float)($input['agent_default_commission_rate_pct'] ?? 2.50);
    $openingCredit = (float)($input['agent_default_opening_credit'] ?? 0.00);
    
    $settlementCycle = trim($input['agent_settlement_cycle'] ?? 'weekly_monday');
    if (!in_array($settlementCycle, ['daily', 'weekly_monday', 'bi_weekly', 'monthly'], true)) {
        $settlementCycle = 'weekly_monday';
    }
    $minPayout = (float)($input['agent_minimum_payout'] ?? 1000.00);
    $negativeCarryover = isset($input['agent_negative_carryover']) ? (int)$input['agent_negative_carryover'] : 1;

    $sql = "UPDATE program_settings 
            SET agent_default_level = ?, 
                agent_review_time_hours = ?, 
                agent_default_partnership_pct = ?, 
                agent_default_commission_rate_pct = ?, 
                agent_default_opening_credit = ?,
                agent_settlement_cycle = ?,
                agent_minimum_payout = ?,
                agent_negative_carryover = ?
            WHERE id = 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sidddsdi", $level, $reviewHours, $partnershipPct, $commissionPct, $openingCredit, $settlementCycle, $minPayout, $negativeCarryover);
    mysqli_stmt_execute($stmt);

    echo json_encode([
        "status" => "success",
        "message" => "Agent program settings updated successfully",
        "data" => [
            "agent_settlement_cycle" => $settlementCycle,
            "agent_minimum_payout" => $minPayout,
            "agent_negative_carryover" => $negativeCarryover
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
