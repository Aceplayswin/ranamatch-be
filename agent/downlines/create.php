<?php
/**
 * Create Sub-Agent Endpoint
 * Endpoint: POST /agent/downlines/create
 * Protected by JWT Auth Middleware. Creates a sub-agent under logged-in agent.
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../services/AgentTreeService.php';

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

// Fetch parent agent details
$parentStmt = mysqli_prepare($conn, "SELECT id, rank_level, partnership, partnership_pct, turnover_commission_pct, current_credit, exposed_credit FROM agents WHERE id = ?");
mysqli_stmt_bind_param($parentStmt, "i", $parentAgentId);
mysqli_stmt_execute($parentStmt);
$parent = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

if (!$parent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Parent agent not found."]);
    exit;
}

// Check parent rank level: lowest level 'agent' cannot create sub-agents
if (($parent['rank_level'] ?? 'agent') === 'agent') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Direct Agent accounts (Agent rank) manage Players directly and cannot create downline agent accounts. Only Master Agents, Super Agents, and Senior Super Agents can create sub-agents."
    ]);
    exit;
}

// Parse request body
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$name = trim($input['name'] ?? $input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$partnershipPct = (float)($input['partnership_pct'] ?? $input['partnership'] ?? $input['commission_rate'] ?? 40.00);
$commissionPct = (float)($input['commission_pct'] ?? $input['turnover_commission_pct'] ?? 2.50);
$phone = trim($input['phone'] ?? '');
$openingCredit = (float)($input['opening_credit'] ?? $input['creditRef'] ?? 0.00);

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Agent Username is required."]);
    exit;
}

if (empty($name)) {
    $name = $username;
}

if (empty($email)) {
    $email = strtolower($username) . '@agent.local';
}

if (empty($password)) {
    $password = 'agent123';
}

// Validate unique username and email in agents
$chk = mysqli_prepare($conn, "SELECT id FROM agents WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($chk, "ss", $username, $email);
mysqli_stmt_execute($chk);
if (mysqli_stmt_get_result($chk)->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Agent with this username or email already exists."]);
    exit;
}

// Validate partnership % does not exceed parent
$parentPartnership = (float)($parent['partnership_pct'] > 0 ? $parent['partnership_pct'] : ($parent['partnership'] ?? 0));
if ($parentPartnership > 0 && $partnershipPct > $parentPartnership) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Partnership % ({$partnershipPct}%) cannot exceed parent partnership % ({$parentPartnership}%)."
    ]);
    exit;
}

// Validate opening credit float check
$parentAvailableCredit = max(0, (float)$parent['current_credit'] - (float)$parent['exposed_credit']);
if ($openingCredit > 0 && $openingCredit > $parentAvailableCredit) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Insufficient available credit float to inject opening credit. Available: ₹" . number_format($parentAvailableCredit, 2)
    ]);
    exit;
}

// Parse requested rank or calculate 1 rank below parent
$reqLevel = strtolower(trim($input['level'] ?? $input['rank_level'] ?? ''));
$levelMap = [
    'senior super agent' => 'senior_super_agent',
    'super agent' => 'super_agent',
    'master agent' => 'master_agent',
    'agent' => 'agent',
    'senior_super_agent' => 'senior_super_agent',
    'super_agent' => 'super_agent',
    'master_agent' => 'master_agent'
];

$rankHierarchy = [
    'senior_super_agent' => 4,
    'super_agent' => 3,
    'master_agent' => 2,
    'agent' => 1
];

$defaultChildMap = [
    'senior_super_agent' => 'super_agent',
    'super_agent' => 'master_agent',
    'master_agent' => 'agent',
    'agent' => 'agent'
];

$parentRank = $parent['rank_level'] ?? 'agent';
$parentWeight = $rankHierarchy[$parentRank] ?? 1;

$subRank = $levelMap[$reqLevel] ?? '';
if (empty($subRank)) {
    $subRank = $defaultChildMap[$parentRank] ?? 'agent';
}

$subWeight = $rankHierarchy[$subRank] ?? 1;

// STRICT ENFORCEMENT: A parent can ONLY create lower child tiers (subWeight < parentWeight)
if ($subWeight >= $parentWeight) {
    http_response_code(400);
    $parentName = ucwords(str_replace('_', ' ', $parentRank));
    $childName = ucwords(str_replace('_', ' ', $subRank));
    echo json_encode([
        "status" => "error",
        "message" => "Hierarchy restriction: A {$parentName} cannot create a {$childName}. Parents can only create downline child accounts."
    ]);
    exit;
}

// Auto-generate code/username if needed
$agentCode = 'AGT-' . strtoupper(substr(md5(uniqid()), 0, 6));
if (empty($username)) {
    $username = strtolower($agentCode);
}

try {
    $treeService = new AgentTreeService($conn);
    $subAgentId = $treeService->createAgentWithTree($parentAgentId, [
        'agent_code' => $agentCode,
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
        'level' => $subRank,
        'rank_level' => $subRank,
        'partnership_pct' => $partnershipPct,
        'turnover_commission_pct' => $commissionPct,
        'opening_credit' => $openingCredit
    ]);

    // Handle opening credit deduction from parent if specified
    if ($openingCredit > 0) {
        mysqli_begin_transaction($conn);
        
        // Deduct from parent
        $deductSql = "UPDATE agents SET current_credit = current_credit - ? WHERE id = ?";
        $dStmt = mysqli_prepare($conn, $deductSql);
        mysqli_stmt_bind_param($dStmt, "di", $openingCredit, $parentAgentId);
        mysqli_stmt_execute($dStmt);

        // Record parent transfer out ledger
        $pRemark = "Opening credit float transfer to sub-agent $agentCode";
        $l1 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, ?)");
        $parentBefore = (float)$parent['current_credit'];
        $parentAfter = $parentBefore - $openingCredit;
        mysqli_stmt_bind_param($l1, "iiddds", $parentAgentId, $subAgentId, $openingCredit, $parentBefore, $parentAfter, $pRemark);
        mysqli_stmt_execute($l1);

        // Record child transfer in ledger
        $cRemark = "Opening credit float received from parent agent #$parentAgentId";
        $l2 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, 'TRANSFER_IN', ?, 0.00, ?, ?)");
        mysqli_stmt_bind_param($l2, "iidds", $subAgentId, $parentAgentId, $openingCredit, $openingCredit, $cRemark);
        mysqli_stmt_execute($l2);

        mysqli_commit($conn);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Sub-agent created successfully",
        "data" => [
            "id" => $subAgentId,
            "agent_code" => $agentCode,
            "username" => $username,
            "name" => $name,
            "email" => $email,
            "rank_level" => $subRank,
            "partnership_pct" => $partnershipPct,
            "turnover_commission_pct" => $commissionPct,
            "current_credit" => $openingCredit
        ]
    ]);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $statusCode = 500;
    $userMessage = "Could not create agent: " . $errorMessage;

    if (stripos($errorMessage, "Duplicate entry") !== false) {
        $statusCode = 409;
        if (stripos($errorMessage, "for key 'username'") !== false) {
            $userMessage = "This username is already in use. Please choose a different username.";
        } elseif (stripos($errorMessage, "for key 'agent_code'") !== false) {
            $userMessage = "This agent code is already in use. Please choose a different agent code.";
        } elseif (stripos($errorMessage, "for key 'email'") !== false) {
            $userMessage = "This email address is already in use. Please use a different email address.";
        } else {
            $userMessage = "Some of these details are already in use. Please choose different details and try again.";
        }
    }

    http_response_code($statusCode);
    echo json_encode(["status" => "error", "message" => $userMessage]);
}
