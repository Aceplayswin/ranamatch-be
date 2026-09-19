<?php
define("ACCESS_SECURITY", true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/AgentApprovalService.php';

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;

$approval_id = (int)($body['approval_id'] ?? 0);
$approver_id = (int)($jwt_user_id ?? $body['approver_id'] ?? $_SESSION['agent_id'] ?? 0);
$decision = strtolower(trim($body['decision'] ?? ''));
$rejection_reason = trim($body['rejection_reason'] ?? '');

if (!$approval_id || !$approver_id || !$decision) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: approval_id, approver_id, decision']);
    exit;
}

$service = new AgentApprovalService();
$res = $service->processDecision($approval_id, $approver_id, $decision, $rejection_reason);

if ($res['success']) {
    echo json_encode(['status' => 'success', 'message' => $res['message']]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $res['error']]);
}
