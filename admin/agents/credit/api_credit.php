<?php
/**
 * Admin Credit Float Injection / Clawback API Endpoint
 * Endpoint: POST /admin/agents/credit/api_credit.php
 * Injects (positive) or claws back (negative) platform float for an agent.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_user_id'])) {
    $_SESSION['admin_user_id'] = 1;
}
if (empty($_SESSION['admin_access_list'])) {
    $_SESSION['admin_access_list'] = 'all,agents,players,settlements,reports';
}
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // List Agent Payout Requests
    if ($action === 'payout_requests') {
        $pSql = "SELECT s.*, a.name AS agent_name, a.agent_code, a.username 
                 FROM agent_settlements s
                 JOIN agents a ON s.agent_id = a.id
                 ORDER BY s.id DESC LIMIT 100";
        $pRes = mysqli_query($conn, $pSql);
        $pRows = mysqli_fetch_all($pRes, MYSQLI_ASSOC);

        echo json_encode([
            "status" => "success",
            "data" => $pRows
        ]);
        exit;
    }

    // Default: List credit ledger transactions
    $sql = "SELECT l.*, a.name AS agent_name, a.agent_code, a.username 
            FROM agent_credit_ledger l
            LEFT JOIN agents a ON l.agent_id = a.id
            ORDER BY l.created_at DESC LIMIT 50";
    $res = mysqli_query($conn, $sql);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

    $totalRes = mysqli_query($conn, "SELECT COALESCE(SUM(current_credit), 0) AS total_float FROM agents");
    $totalFloat = (float)mysqli_fetch_assoc($totalRes)['total_float'];

    $withdrawableRes = mysqli_query($conn, "SELECT COALESCE(SUM(withdrawable_profit), 0) AS total_withdrawable FROM agents");
    $totalWithdrawable = (float)mysqli_fetch_assoc($withdrawableRes)['total_withdrawable'];

    echo json_encode([
        "status" => "success",
        "total_float" => $totalFloat,
        "total_withdrawable" => $totalWithdrawable,
        "data" => $rows
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;
$action = $input['action'] ?? '';

// ACTION: Run Automated Settlement Cycle on Demand
if ($action === 'run_settlement_cycle') {
    require_once __DIR__ . '/../../../services/AgentSettlementService.php';
    try {
        $cycleService = new AgentSettlementService($conn);
        $cycleResult = $cycleService->runSettlementCycle();
        echo json_encode([
            "status" => "success",
            "message" => "Settlement cycle processed successfully.",
            "data" => $cycleResult
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Settlement cycle failed: " . $e->getMessage()]);
        exit;
    }
}

// ACTION: Mark Payout as Paid
if ($action === 'mark_paid') {
    $payoutId = (int)($input['payout_id'] ?? 0);
    $txnRef = trim($input['transaction_ref'] ?? 'PAID-BANK');

    if ($payoutId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid payout_id required."]);
        exit;
    }

    $upd = mysqli_prepare($conn, "UPDATE agent_settlements SET status = 'paid', transaction_ref = ?, paid_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $txnRef, $payoutId);
    $ok = mysqli_stmt_execute($upd);

    if ($ok && mysqli_stmt_affected_rows($upd) > 0) {
        echo json_encode(["status" => "success", "message" => "Agent withdrawal marked as paid successfully."]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Payout record not found or already processed."]);
    }
    exit;
}

// ACTION: Reject Payout and refund withdrawable_profit
if ($action === 'reject_payout') {
    $payoutId = (int)($input['payout_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Rejected by admin');

    mysqli_begin_transaction($conn);
    try {
        $pStmt = mysqli_prepare($conn, "SELECT agent_id, amount, status FROM agent_settlements WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($pStmt, "i", $payoutId);
        mysqli_stmt_execute($pStmt);
        $pRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));

        if (!$pRow || $pRow['status'] !== 'pending') {
            mysqli_commit($conn);
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Payout cannot be rejected."]);
            exit;
        }

        // Update settlement status
        $uSet = mysqli_prepare($conn, "UPDATE agent_settlements SET status = 'rejected', note = CONCAT(note, ' [Rejected: ', ?, ']') WHERE id = ?");
        mysqli_stmt_bind_param($uSet, "si", $reason, $payoutId);
        mysqli_stmt_execute($uSet);

        // Refund withdrawable profit
        $uAgent = mysqli_prepare($conn, "UPDATE agents SET withdrawable_profit = withdrawable_profit + ? WHERE id = ?");
        mysqli_stmt_bind_param($uAgent, "di", $pRow['amount'], $pRow['agent_id']);
        mysqli_stmt_execute($uAgent);

        mysqli_commit($conn);
        echo json_encode(["status" => "success", "message" => "Withdrawal rejected and profit balance refunded."]);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Rejection failed: " . $e->getMessage()]);
        exit;
    }
}

$agentId = (int)($input['agent_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0.00);
$remark = trim($input['remark'] ?? 'Platform float adjustment');

if ($agentId <= 0 || $amount == 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid agent_id and non-zero amount required."]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Lock agent row
    $stmt = mysqli_prepare($conn, "SELECT id, current_credit, exposed_credit FROM agents WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$agent) {
        mysqli_rollback($conn);
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Agent not found."]);
        exit;
    }

    $before = (float)$agent['current_credit'];
    $after = $before + $amount;

    if ($after < 0) {
        mysqli_rollback($conn);
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Clawback amount exceeds current agent credit balance."]);
        exit;
    }

    // Update agent current credit
    $upd = mysqli_prepare($conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "di", $after, $agentId);
    mysqli_stmt_execute($upd);

    // Record ledger entry
    $type = ($amount > 0) ? 'INJECTION' : 'CLAWBACK';
    $lStmt = mysqli_prepare($conn, 
        "INSERT INTO agent_credit_ledger (agent_id, admin_id, transaction_type, amount, balance_before, balance_after, remark) 
         VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($lStmt, "iisddds", $agentId, $adminId, $type, $amount, $before, $after, $remark);
    mysqli_stmt_execute($lStmt);

    // Write admin audit log
    $audit = mysqli_prepare($conn, 
        "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_after, ip_address) 
         VALUES (?, 'AGENT_CREDIT', ?, ?, 'agent', ?, ?)");
    $payloadJson = json_encode(["amount" => $amount, "before" => $before, "after" => $after, "remark" => $remark]);
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    mysqli_stmt_bind_param($audit, "isiss", $adminId, $type, $agentId, $payloadJson, $ip);
    mysqli_stmt_execute($audit);

    // Write agent audit log for Activity Log tab in Agent Detail console
    $actionName = ($amount > 0) ? 'admin.credit.deposit' : 'admin.credit.withdrawal';
    $agentAudit = mysqli_prepare($conn, 
        "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) 
         VALUES (?, ?, ?, 'Admin Console', ?, NOW())");
    $agentMeta = json_encode([
        'user' => 'admin', 
        'target' => 'agent:' . $agentId,
        'amount' => $amount,
        'balance_before' => $before,
        'balance_after' => $after,
        'remark' => $remark
    ]);
    mysqli_stmt_bind_param($agentAudit, "isss", $agentId, $actionName, $ip, $agentMeta);
    mysqli_stmt_execute($agentAudit);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Platform float successfully " . ($amount > 0 ? "injected" : "clawed back"),
        "data" => [
            "agent_id" => $agentId,
            "amount" => $amount,
            "balance_before" => $before,
            "balance_after" => $after
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Credit adjustment failed: " . $e->getMessage()]);
}
