<?php
/**
 * Agent Payout Request Endpoint
 * Endpoint: POST /agent/payouts/request.php
 * Protected by JWT Auth Middleware. Submits a withdrawal request from withdrawable_profit.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$amount = (float)($input['amount'] ?? 0.00);
$payoutMethod = trim($input['payout_method'] ?? 'bank');
$accountDetails = trim($input['account_details'] ?? '');

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid withdrawal amount is required."]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Fetch program minimum payout threshold
    $psRes = mysqli_query($conn, "SELECT agent_minimum_payout, agent_settlement_cycle FROM program_settings WHERE id = 1");
    $ps = mysqli_fetch_assoc($psRes);
    $minPayout = (float)($ps['agent_minimum_payout'] ?? 1000.00);

    if ($amount < $minPayout) {
        mysqli_commit($conn);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Minimum withdrawal threshold is ₹" . number_format($minPayout, 2)
        ]);
        exit;
    }

    // 2. Lock & Fetch agent record
    $stmt = mysqli_prepare($conn, "SELECT id, agent_code, username, withdrawable_profit FROM agents WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$agent) {
        mysqli_commit($conn);
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Agent profile not found."]);
        exit;
    }

    $availableProfit = (float)$agent['withdrawable_profit'];
    if ($amount > $availableProfit) {
        mysqli_commit($conn);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Insufficient withdrawable profit. Available: ₹" . number_format($availableProfit, 2)
        ]);
        exit;
    }

    // 3. Deduct from withdrawable_profit
    $newWithdrawable = $availableProfit - $amount;
    $upd = mysqli_prepare($conn, "UPDATE agents SET withdrawable_profit = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "di", $newWithdrawable, $agentId);
    mysqli_stmt_execute($upd);

    // 4. Record pending settlement request
    $periodLabel = "Withdrawal Request (" . date('Y-m-d') . ")";
    $note = "Agent withdrawal request via {$payoutMethod}" . ($accountDetails ? " Details: {$accountDetails}" : "");
    $ins = mysqli_prepare($conn, 
        "INSERT INTO agent_settlements (agent_id, amount, pl_before, pl_after, period, note, status, payout_method, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
    mysqli_stmt_bind_param($ins, "idddsss", $agentId, $amount, $availableProfit, $newWithdrawable, $periodLabel, $note, $payoutMethod);
    mysqli_stmt_execute($ins);
    $payoutId = mysqli_insert_id($conn);

    // 5. Audit log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $meta = json_encode(['payout_id' => $payoutId, 'amount' => $amount, 'method' => $payoutMethod]);
    $log = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, metadata, created_at) VALUES (?, 'payout.requested', ?, ?, NOW())");
    mysqli_stmt_bind_param($log, "iss", $agentId, $ip, $meta);
    mysqli_stmt_execute($log);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Withdrawal request submitted successfully. Awaiting admin disbursement.",
        "data" => [
            "payout_id" => $payoutId,
            "requested_amount" => $amount,
            "remaining_withdrawable_profit" => $newWithdrawable
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Withdrawal request failed: " . $e->getMessage()]);
}
