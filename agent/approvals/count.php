<?php
define("ACCESS_SECURITY", true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

$approver_id = (int)($jwt_user_id ?? $_GET['approver_id'] ?? $_SESSION['agent_id'] ?? 0);

if (!$approver_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or missing approver_id']);
    exit;
}

$service = new AgentApprovalService();
$count = $service->getPendingCount($approver_id);

echo json_encode([
    'status' => 'success',
    'approver_id' => $approver_id,
    'pending_count' => $count
]);
