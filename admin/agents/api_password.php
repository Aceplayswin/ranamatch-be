<?php
/**
 * Admin Agent Password Reset / Update API Endpoint
 * Endpoint: POST /admin/agents/api_password.php
 * Allows admin to update an agent's password directly in the database.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';

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

$agentId = (int)($input['agent_id'] ?? 0);
$newPassword = trim($input['new_password'] ?? $input['password'] ?? '');

if ($agentId <= 0 || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid agent_id and non-empty new_password are required."]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
    exit;
}

// Fetch agent
$stmt = mysqli_prepare($conn, "SELECT id, username, email FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent account not found."]);
    exit;
}

$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

// Update password in agents table
$upd = mysqli_prepare($conn, "UPDATE agents SET password_hash = ?, must_change_password = 0 WHERE id = ?");
mysqli_stmt_bind_param($upd, "si", $passwordHash, $agentId);

if (mysqli_stmt_execute($upd)) {
    // Record audit log
    $adminId = (int)($_SESSION['admin_user_id'] ?? 1);
    $audit = mysqli_prepare($conn, 
        "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_after, ip_address) 
         VALUES (?, 'AGENT_PASSWORD', 'PASSWORD_RESET', ?, 'agent', ?, ?)");
    $payloadJson = json_encode(["username" => $agent['username'], "action" => "password_reset_by_admin"]);
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    mysqli_stmt_bind_param($audit, "iiss", $adminId, $agentId, $payloadJson, $ip);
    mysqli_stmt_execute($audit);

    echo json_encode([
        "status" => "success",
        "message" => "Password for agent '{$agent['username']}' updated successfully.",
        "data" => [
            "agent_id" => $agentId,
            "username" => $agent['username']
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update password: " . mysqli_error($conn)]);
}
