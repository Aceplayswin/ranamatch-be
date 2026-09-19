<?php
/**
 * Admin Agent Status & Delete API Endpoint
 * Endpoint: POST /admin/agents/status/api_status.php
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

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$agentId = (int)($input['agent_id'] ?? 0);
$status = trim($input['status'] ?? '');
$reason = trim($input['reason'] ?? 'Admin status update');

$allowedStatuses = ['active', 'suspended', 'locked', 'closed'];

if ($agentId <= 0 || !in_array($status, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid agent_id and allowed status ('active','suspended','locked','closed') required."]);
    exit;
}

// Fetch current status
$stmt = mysqli_prepare($conn, "SELECT id, name, username, status FROM agents WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent not found."]);
    exit;
}

$oldStatus = $agent['status'];

// Update status
$upd = mysqli_prepare($conn, "UPDATE agents SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($upd, "si", $status, $agentId);
mysqli_stmt_execute($upd);

// Write admin audit log
$audit = mysqli_prepare($conn, 
    "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_before, payload_after, ip_address) 
     VALUES (?, 'AGENT_STATUS', 'STATUS_CHANGE', ?, 'agent', ?, ?, ?)");
$beforeJson = json_encode(["status" => $oldStatus]);
$afterJson = json_encode(["status" => $status, "reason" => $reason]);
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
mysqli_stmt_bind_param($audit, "iisss", $adminId, $agentId, $beforeJson, $afterJson, $ip);
mysqli_stmt_execute($audit);

// Write agent activity audit log
$actionName = ($status === 'suspended') ? 'admin.agent.suspended' : (($status === 'active') ? 'admin.agent.activated' : 'admin.agent.status_change');
$agentAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, ?, ?, 'Admin System', ?, NOW())");
$metaJson = json_encode(['user' => 'admin', 'target' => 'agent:' . $agentId, 'old_status' => $oldStatus, 'new_status' => $status]);
mysqli_stmt_bind_param($agentAudit, "isss", $agentId, $actionName, $ip, $metaJson);
mysqli_stmt_execute($agentAudit);

echo json_encode([
    "status" => "success",
    "message" => "Agent status updated from '$oldStatus' to '$status'",
    "data" => [
        "agent_id" => $agentId,
        "old_status" => $oldStatus,
        "new_status" => $status
    ]
]);
