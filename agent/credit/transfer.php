<?php
/**
 * Agent Credit Transfer Endpoint
 * Endpoint: POST /agent/credit/transfer
 * Protected by JWT Auth Middleware. Moves credit float to direct sub-agent or claws back up.
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

$parentAgentId = (int)$jwt_user_id;

// Parse input
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$targetAgentId = (int)($input['target_agent_id'] ?? $input['sub_agent_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0.00);
$remark = trim($input['remark'] ?? 'Credit float transfer');

if ($targetAgentId <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid target_agent_id and positive amount are required."]);
    exit;
}

// 1. Verify target_agent_id is a direct downline (depth = 1 in agent_tree)
$treeStmt = mysqli_prepare($conn, "SELECT depth FROM agent_tree WHERE ancestor_id = ? AND descendant_id = ? AND depth = 1");
mysqli_stmt_bind_param($treeStmt, "ii", $parentAgentId, $targetAgentId);
mysqli_stmt_execute($treeStmt);
$treeRes = mysqli_stmt_get_result($treeStmt);

if (!mysqli_fetch_assoc($treeRes)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Permission denied. Target is not your direct sub-agent."]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 2. Fetch & Lock parent agent row
    $pStmt = mysqli_prepare($conn, "SELECT id, agent_code, username, current_credit, exposed_credit FROM agents WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($pStmt, "i", $parentAgentId);
    mysqli_stmt_execute($pStmt);
    $parent = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));

    // 3. Fetch & Lock child agent row
    $cStmt = mysqli_prepare($conn, "SELECT id, agent_code, username, current_credit, exposed_credit FROM agents WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($cStmt, "i", $targetAgentId);
    mysqli_stmt_execute($cStmt);
    $child = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt));

    $parentAvailable = max(0, (float)$parent['current_credit'] - (float)$parent['exposed_credit']);

    if ($amount > $parentAvailable) {
        mysqli_rollback($conn);
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Insufficient available credit. Available: ₹" . number_format($parentAvailable, 2)
        ]);
        exit;
    }

    // 4. Calculate new balances
    $parentBefore = (float)$parent['current_credit'];
    $parentAfter = $parentBefore - $amount;

    $childBefore = (float)$child['current_credit'];
    $childAfter = $childBefore + $amount;

    // Update parent
    $u1 = mysqli_prepare($conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
    mysqli_stmt_bind_param($u1, "di", $parentAfter, $parentAgentId);
    mysqli_stmt_execute($u1);

    // Update child
    $u2 = mysqli_prepare($conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
    mysqli_stmt_bind_param($u2, "di", $childAfter, $targetAgentId);
    mysqli_stmt_execute($u2);

    // Write Parent TRANSFER_OUT ledger
    $pRemark = $remark . " (Transferred to " . $child['agent_code'] . ")";
    $l1 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($l1, "iiddds", $parentAgentId, $targetAgentId, $amount, $parentBefore, $parentAfter, $pRemark);
    mysqli_stmt_execute($l1);

    // Write Child TRANSFER_IN ledger
    $cRemark = $remark . " (Received from " . $parent['agent_code'] . ")";
    $l2 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, 'TRANSFER_IN', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($l2, "iiddds", $targetAgentId, $parentAgentId, $amount, $childBefore, $childAfter, $cRemark);
    mysqli_stmt_execute($l2);

    // Record Activity Audit Logs for both Parent and Target Sub-Agent
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Agent Console';
    $parentUser = $parent['username'] ?? 'master_agent';

    // 1. Parent audit log (Credit Transferred Out)
    $pAction = 'agent.credit_transferred';
    $pMeta = json_encode([
        'user' => $parentUser,
        'target' => 'agent:' . $targetAgentId,
        'amount' => $amount,
        'target_code' => $child['agent_code'],
        'remark' => $remark
    ]);
    $pAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($pAudit, "issss", $parentAgentId, $pAction, $ip, $ua, $pMeta);
    mysqli_stmt_execute($pAudit);

    // 2. Child audit log (Credit Received In)
    $cAction = 'agent.credit_received';
    $cMeta = json_encode([
        'user' => $parentUser,
        'target' => 'agent:' . $targetAgentId,
        'amount' => $amount,
        'from_code' => $parent['agent_code'],
        'remark' => $remark
    ]);
    $cAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($cAudit, "issss", $targetAgentId, $cAction, $ip, $ua, $cMeta);
    mysqli_stmt_execute($cAudit);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Credit float transferred successfully",
        "data" => [
            "transferred_amount" => $amount,
            "parent_new_credit" => $parentAfter,
            "target_new_credit" => $childAfter
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Transfer failed: " . $e->getMessage()]);
}
