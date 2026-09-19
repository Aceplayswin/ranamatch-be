<?php
ob_start();
define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/AgentApprovalService.php';

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$service = new AgentApprovalService();

if ($method === 'GET') {
    $approver_id = (int)($jwt_user_id ?? $_GET['approver_id'] ?? $_SESSION['agent_id'] ?? 0);
    if (!$approver_id) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized or missing approver_id']);
        exit;
    }

    $type = $_GET['type'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $items = $service->getPendingApprovals($approver_id, $type, $limit, $offset);
    $count = $service->getPendingCount($approver_id);

    echo json_encode([
        'status' => 'success',
        'total_pending' => $count,
        'data' => $items
    ]);
    exit;

} elseif ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: $_POST;

    $requester_id = (int)($body['requester_id'] ?? 0);
    $requester_type = $body['requester_type'] ?? 'agent';
    $approver_id = (int)($jwt_user_id ?? $body['approver_id'] ?? $_SESSION['agent_id'] ?? 0);
    $approval_type = $body['approval_type'] ?? '';
    $amount = (float)($body['amount'] ?? 0.00);
    $details = $body['details'] ?? [];

    if (!$requester_id || !$approver_id || !$approval_type) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: requester_id, approver_id, approval_type']);
        exit;
    }

    $res = $service->createRequest($requester_id, $requester_type, $approver_id, $approval_type, $amount, $details);

    if ($res['success']) {
        echo json_encode(['status' => 'success', 'data' => $res]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $res['error']]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
