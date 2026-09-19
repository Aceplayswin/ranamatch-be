<?php
/**
 * List Agent Players Endpoint
 * Endpoint: GET /agent/players
 * Protected by JWT Auth Middleware. Returns list of players directly joined under logged-in agent.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $parsedInput = json_decode($rawInput, true) ?? $_POST;
    
    $action = strtolower(trim($parsedInput['action'] ?? ''));
    if (
        $action === 'credit' || 
        $action === 'transfer' || 
        $action === 'deposit' || 
        !empty($parsedInput['player_id']) || 
        !empty($parsedInput['target_player_id']) || 
        (isset($parsedInput['amount']) && empty($parsedInput['username']) && empty($parsedInput['password']))
    ) {
        require __DIR__ . '/credit.php';
        exit;
    }

    require __DIR__ . '/create.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use GET or POST."]);
    exit;
}

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$parentAgentId = (int)$jwt_user_id;

// Fetch parent agent details
$parentStmt = mysqli_prepare($conn, "SELECT id, agent_code, username FROM agents WHERE id = ?");
mysqli_stmt_bind_param($parentStmt, "i", $parentAgentId);
mysqli_stmt_execute($parentStmt);
$parent = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

if (!$parent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent account not found."]);
    exit;
}

$joinedUnderCode = $parent['agent_code'];
$joinedUnderUsername = $parent['username'];
$joinedUnderIdStr = (string)$parentAgentId;

$sql = "SELECT id, tbl_uniq_id AS uniq_id, tbl_user_name AS username, tbl_full_name AS full_name, 
               tbl_mobile_num AS phone, tbl_email_id AS email, tbl_balance AS balance, 
               tbl_account_status AS status, tbl_user_joined AS joined_date
        FROM tblusersdata 
        WHERE tbl_joined_under = ? OR tbl_joined_under = ? OR tbl_joined_under = ?
        ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sss", $joinedUnderCode, $joinedUnderUsername, $joinedUnderIdStr);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$players = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['balance'] = (float)$row['balance'];
    $rawStatus = strtolower(trim($row['status'] ?? ''));
    $row['raw_status'] = $rawStatus;
    $row['status'] = ($rawStatus === 'true' || $rawStatus === 'active' || $rawStatus === '1') ? 'active' : 'blocked';
    $players[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $players,
    "count" => count($players)
]);
