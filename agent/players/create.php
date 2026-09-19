<?php
/**
 * Create Direct Player Account Endpoint
 * Endpoint: POST /agent/players/create
 * Protected by JWT Auth Middleware. Creates a direct player under logged-in agent.
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

// Fetch parent agent details
$parentStmt = mysqli_prepare($conn, "SELECT id, agent_code, username, current_credit, exposed_credit FROM agents WHERE id = ?");
mysqli_stmt_bind_param($parentStmt, "i", $parentAgentId);
mysqli_stmt_execute($parentStmt);
$parent = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

if (!$parent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent account not found."]);
    exit;
}

// Parse request body
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$username = trim($input['username'] ?? $input['tbl_user_name'] ?? '');
$password = trim($input['password'] ?? $input['tbl_password'] ?? '');
$fullName = trim($input['full_name'] ?? $input['name'] ?? $input['tbl_full_name'] ?? '');
$phone = trim($input['phone'] ?? $input['mobile'] ?? $input['tbl_mobile_num'] ?? '');
$openingCredit = (float)($input['opening_credit'] ?? $input['credit'] ?? $input['balance'] ?? $input['initial_balance'] ?? 0.00);

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Player Username is required."]);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Player Password is required."]);
    exit;
}

if (empty($fullName)) {
    $fullName = $username;
}

if (empty($phone)) {
    $phone = '9999999999';
}

// Check if username already exists in tblusersdata
$checkStmt = mysqli_prepare($conn, "SELECT id FROM tblusersdata WHERE tbl_user_name = ?");
mysqli_stmt_bind_param($checkStmt, "s", $username);
mysqli_stmt_execute($checkStmt);
if (mysqli_num_rows(mysqli_stmt_get_result($checkStmt)) > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username '{$username}' is already taken."]);
    exit;
}

// Validate opening credit float check against parent agent balance
$parentAvailableCredit = max(0, (float)$parent['current_credit'] - (float)$parent['exposed_credit']);
if ($openingCredit > 0 && $openingCredit > $parentAvailableCredit) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Insufficient available credit float to set player opening credit. Available: ₹" . number_format($parentAvailableCredit, 2)
    ]);
    exit;
}

// Prepare player attributes
$uniqId = 'USR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$email = strtolower($username) . '@player.local';
$joinedUnder = !empty($parent['agent_code']) ? $parent['agent_code'] : $parent['username'];
$todayDate = date('d-m-Y');
$nowTime = date('h:i a');

mysqli_begin_transaction($conn);

try {
    // Insert into tblusersdata
    $insertSql = "INSERT INTO tblusersdata (
        tbl_uniq_id, tbl_user_name, tbl_auth_secret, tbl_avatar_id, tbl_mobile_num, tbl_email_id,
        tbl_full_name, tbl_password, tbl_balance, tbl_bonus_balance, tbl_sports_bonus,
        tbl_requiredplay_balance, tbl_withdrawl_balance, tbl_commission_balance, tbl_freezed_balance,
        tbl_joined_under, tbl_last_active_date, tbl_last_active_time, tbl_account_level, tbl_account_status, tbl_user_joined
    ) VALUES (
        ?, ?, 'secret', '1', ?, ?,
        ?, ?, ?, 0.00, 0.00,
        0.00, 0.00, 0.00, 0.00,
        ?, ?, ?, '1', 'true', ?
    )";

    $stmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssdssss",
        $uniqId,
        $username,
        $phone,
        $email,
        $fullName,
        $passwordHash,
        $openingCredit,
        $joinedUnder,
        $todayDate,
        $nowTime,
        $todayDate
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Database insert error: " . mysqli_error($conn));
    }

    $playerId = mysqli_insert_id($conn);

    // If opening credit was provided, deduct from parent agent and record ledger
    if ($openingCredit > 0) {
        $deductSql = "UPDATE agents SET current_credit = current_credit - ? WHERE id = ?";
        $dStmt = mysqli_prepare($conn, $deductSql);
        mysqli_stmt_bind_param($dStmt, "di", $openingCredit, $parentAgentId);
        mysqli_stmt_execute($dStmt);

        // Parent credit ledger
        $pRemark = "Opening credit transferred to player $username ($uniqId)";
        $l1 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, ?)");
        $parentBefore = (float)$parent['current_credit'];
        $parentAfter = $parentBefore - $openingCredit;
        mysqli_stmt_bind_param($l1, "iiddds", $parentAgentId, $playerId, $openingCredit, $parentBefore, $parentAfter, $pRemark);
        mysqli_stmt_execute($l1);
    }

    // Record agent activity audit log
    $actionName = 'player.created';
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Browser';
    $metaJson = json_encode(['user' => $parent['username'], 'target' => 'player:' . $playerId, 'username' => $username]);
    $agentAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($agentAudit, "issss", $parentAgentId, $actionName, $ip, $ua, $metaJson);
    mysqli_stmt_execute($agentAudit);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Player account created successfully",
        "data" => [
            "id" => $playerId,
            "uniq_id" => $uniqId,
            "username" => $username,
            "full_name" => $fullName,
            "phone" => $phone,
            "balance" => $openingCredit,
            "joined_under" => $joinedUnder,
            "status" => "active"
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to create player: " . $e->getMessage()]);
}
