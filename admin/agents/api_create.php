<?php
/**
 * Admin Create Agent API Endpoint
 * Endpoint: POST /admin/agents/api_create.php
 * Allows admin to directly create a root or sub-agent using AgentTreeService.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';
require_once __DIR__ . '/../../services/AgentTreeService.php';

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

$name = trim($input['name'] ?? $input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$phone = trim($input['phone'] ?? '');
$rankLevel = trim($input['rank_level'] ?? 'agent');
$partnershipPct = (float)($input['partnership_pct'] ?? $input['partnership'] ?? 50.00);
$commissionPct = (float)($input['turnover_commission_pct'] ?? $input['commission_pct'] ?? 2.50);
$openingCredit = (float)($input['opening_credit'] ?? $input['creditRef'] ?? 0.00);
$parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

if (empty($name) || empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Full Name and Username are required."]);
    exit;
}

if (empty($email)) {
    $email = strtolower($username) . '@agent.local';
}

if (empty($password)) {
    $password = 'agent123';
}

// Check username uniqueness
$chkStmt = mysqli_prepare($conn, "SELECT id FROM agents WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($chkStmt, "ss", $username, $email);
mysqli_stmt_execute($chkStmt);
if (mysqli_stmt_get_result($chkStmt)->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Agent with this username or email already exists."]);
    exit;
}

$agentCode = 'AGT-' . strtoupper(substr(md5(uniqid()), 0, 6));

try {
    $treeService = new AgentTreeService($conn);
    $newAgentId = $treeService->createAgentWithTree($parentId, [
        'agent_code' => $agentCode,
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
        'rank_level' => $rankLevel,
        'status' => 'active',
        'partnership_pct' => $partnershipPct,
        'turnover_commission_pct' => $commissionPct,
        'opening_credit' => $openingCredit
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Agent account created successfully",
        "data" => [
            "id" => $newAgentId,
            "agent_code" => $agentCode,
            "username" => $username,
            "name" => $name,
            "email" => $email,
            "rank_level" => $rankLevel,
            "status" => "active",
            "current_credit" => $openingCredit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to create agent: " . $e->getMessage()]);
}
