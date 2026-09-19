<?php
/**
 * Agent Player Credit Transfer Endpoint
 * Endpoint: POST /agent/players/credit.php
 * Transfers credit float between agent and direct player.
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

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

// Parse player id or string identifier (username/uniq_id)
$playerId = (int)($input['player_id'] ?? $input['playerId'] ?? $input['target_player_id'] ?? $input['targetPlayerId'] ?? $input['id'] ?? $input['user_id'] ?? $input['userId'] ?? $input['target_id'] ?? $input['targetId'] ?? 0);

$playerIdentifier = trim($input['username'] ?? $input['user_name'] ?? $input['uniq_id'] ?? $input['player'] ?? $input['player_username'] ?? $input['playerUsername'] ?? '');

// If numeric player_id wasn't directly in payload, search by username or uniq_id
if ($playerId <= 0 && !empty($playerIdentifier)) {
    $pFind = mysqli_prepare($conn, "SELECT id FROM tblusersdata WHERE tbl_user_name = ? OR tbl_uniq_id = ? OR CAST(id AS CHAR) = ? LIMIT 1");
    mysqli_stmt_bind_param($pFind, "sss", $playerIdentifier, $playerIdentifier, $playerIdentifier);
    mysqli_stmt_execute($pFind);
    $pRes = mysqli_fetch_assoc(mysqli_stmt_get_result($pFind));
    if ($pRes) {
        $playerId = (int)$pRes['id'];
    }
}

// Parse amount & direction
$rawAmount = (float)($input['amount'] ?? $input['credit'] ?? $input['balance'] ?? $input['value'] ?? $input['val'] ?? $input['creditAmount'] ?? $input['transferAmount'] ?? $input['change'] ?? 0.00);

$type = strtolower(trim($input['type'] ?? $input['mode'] ?? $input['action'] ?? $input['transaction_type'] ?? $input['direction'] ?? ''));
$isWithdraw = (!empty($input['isWithdraw']) || !empty($input['is_withdraw']) || $type === 'withdraw' || $type === 'withdrawal' || $type === 'out' || $type === 'clawback');

$amount = $isWithdraw ? -abs($rawAmount) : abs($rawAmount);
$remark = trim($input['remark'] ?? $input['notes'] ?? 'Player credit float transfer');

if ($playerId <= 0 || $rawAmount == 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Valid player_id and non-zero amount required.",
        "received_payload" => $input
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Fetch & Lock parent agent row
    $pStmt = mysqli_prepare($conn, "SELECT id, agent_code, username, current_credit, exposed_credit FROM agents WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($pStmt, "i", $parentAgentId);
    mysqli_stmt_execute($pStmt);
    $parent = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));

    if (!$parent) {
        mysqli_rollback($conn);
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Agent account not found."]);
        exit;
    }

    // 2. Fetch & Lock target player row
    $plrStmt = mysqli_prepare($conn, "SELECT id, tbl_user_name, tbl_balance, tbl_joined_under FROM tblusersdata WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($plrStmt, "i", $playerId);
    mysqli_stmt_execute($plrStmt);
    $player = mysqli_fetch_assoc(mysqli_stmt_get_result($plrStmt));

    if (!$player) {
        mysqli_rollback($conn);
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Player account not found."]);
        exit;
    }

    // Verify player is assigned to this agent
    $parentCode = $parent['agent_code'];
    $parentUser = $parent['username'];
    $parentIdStr = (string)$parentAgentId;
    $joinedUnder = $player['tbl_joined_under'];

    if ($joinedUnder !== $parentCode && $joinedUnder !== $parentUser && $joinedUnder !== $parentIdStr) {
        mysqli_rollback($conn);
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Permission denied. Player is not directly under your account."]);
        exit;
    }

    $parentBalance = (float)$parent['current_credit'];
    $parentExposed = (float)$parent['exposed_credit'];
    $parentAvailable = max(0, $parentBalance - $parentExposed);
    $playerBalance = (float)$player['tbl_balance'];

    // Deposit to player (amount > 0)
    if ($amount > 0) {
        if ($amount > $parentAvailable) {
            mysqli_rollback($conn);
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Insufficient credit. Available agent credit: ₹" . number_format($parentAvailable, 2)
            ]);
            exit;
        }

        $newParentCredit = $parentBalance - $amount;
        $newPlayerBalance = $playerBalance + $amount;
        $transType = 'TRANSFER_OUT';
    } 
    // Withdraw / Claw back from player (amount < 0)
    else {
        $clawAmount = abs($amount);
        if ($clawAmount > $playerBalance) {
            mysqli_rollback($conn);
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Clawback amount exceeds player's current balance (₹" . number_format($playerBalance, 2) . ")"
            ]);
            exit;
        }

        $newParentCredit = $parentBalance + $clawAmount;
        $newPlayerBalance = $playerBalance - $clawAmount;
        $transType = 'TRANSFER_IN';
    }

    // Update Agent credit
    $uAgent = mysqli_prepare($conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
    mysqli_stmt_bind_param($uAgent, "di", $newParentCredit, $parentAgentId);
    mysqli_stmt_execute($uAgent);

    // Update Player balance
    $uPlayer = mysqli_prepare($conn, "UPDATE tblusersdata SET tbl_balance = ? WHERE id = ?");
    mysqli_stmt_bind_param($uPlayer, "di", $newPlayerBalance, $playerId);
    mysqli_stmt_execute($uPlayer);

    // Record ledger entry
    $remarkText = $remark . " (" . ($amount > 0 ? "Allocated to player " : "Clawed back from player ") . $player['tbl_user_name'] . ")";
    $absAmount = abs($amount);
    $l1 = mysqli_prepare($conn, "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($l1, "iisddds", $parentAgentId, $playerId, $transType, $absAmount, $parentBalance, $newParentCredit, $remarkText);
    mysqli_stmt_execute($l1);

    // Record activity audit log
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Browser';
    $actionName = ($amount > 0) ? 'player.credit_allocated' : 'player.credit_clawback';
    $metaJson = json_encode(['user' => $parentUser, 'target' => 'player:' . $playerId, 'amount' => $absAmount, 'player_username' => $player['tbl_user_name']]);
    $agentAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($agentAudit, "issss", $parentAgentId, $actionName, $ip, $ua, $metaJson);
    mysqli_stmt_execute($agentAudit);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Player credit transfer completed successfully.",
        "data" => [
            "player_id" => $playerId,
            "player_username" => $player['tbl_user_name'],
            "new_player_balance" => $newPlayerBalance,
            "new_agent_credit" => $newParentCredit
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Transfer failed: " . $e->getMessage()]);
}
?>
