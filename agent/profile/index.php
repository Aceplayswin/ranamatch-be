<?php
/**
 * Agent Profile Endpoint
 * Endpoint: GET /agent/profile  or  PUT /agent/profile
 * Protected by JWT Auth Middleware. View or update agent profile.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

// GET: Return Agent Profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, agent_code, username, name, email, rank_level, status, partnership_pct, turnover_commission_pct, current_credit, exposed_credit, must_change_password, created_at 
            FROM agents 
            WHERE id = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$agent) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Agent profile not found."]);
        exit;
    }

    // Fetch downlines summary count
    $dAgentRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM agents WHERE parent_id = $agentId");
    $downlineAgentsCount = $dAgentRes ? (int)mysqli_fetch_assoc($dAgentRes)['total'] : 0;

    $codeStr = mysqli_real_escape_string($conn, $agent['agent_code']);
    $userStr = mysqli_real_escape_string($conn, $agent['username']);
    $idStr = (string)$agentId;
    $dPlayerRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusersdata WHERE tbl_joined_under = '$codeStr' OR tbl_joined_under = '$userStr' OR tbl_joined_under = '$idStr'");
    $downlinePlayersCount = $dPlayerRes ? (int)mysqli_fetch_assoc($dPlayerRes)['total'] : 0;

    // Fetch last activity log time
    $lLogRes = mysqli_query($conn, "SELECT created_at FROM agent_audit_logs WHERE agent_id = $agentId ORDER BY id DESC LIMIT 1");
    $lastLoginFormatted = 'Recently active';
    if ($lRow = mysqli_fetch_assoc($lLogRes)) {
        $lastLoginFormatted = date('j M Y, g:i a', strtotime($lRow['created_at']));
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "id" => (int)$agent['id'],
            "agent_code" => $agent['agent_code'],
            "username" => $agent['username'],
            "name" => !empty($agent['name']) ? $agent['name'] : $agent['username'],
            "email" => $agent['email'],
            "rank_level" => $agent['rank_level'],
            "status" => $agent['status'],
            "currency" => "INR",
            "partnership_pct" => (float)$agent['partnership_pct'],
            "turnover_commission_pct" => (float)$agent['turnover_commission_pct'],
            "credit_reference" => (float)$agent['current_credit'],
            "current_credit" => (float)$agent['current_credit'],
            "exposed_credit" => (float)$agent['exposed_credit'],
            "available_credit" => max(0, (float)$agent['current_credit'] - (float)$agent['exposed_credit']),
            "settled_pnl" => 0.00,
            "unsettled_pnl" => (float)($agent['unsettled_pnl'] ?? 0.00),
            "downline_agents_count" => $downlineAgentsCount,
            "downline_players_count" => $downlinePlayersCount,
            "last_login" => $lastLoginFormatted,
            "must_change_password" => (bool)($agent['must_change_password'] ?? false),
            "created_at" => $agent['created_at']
        ]
    ]);
    exit;
}

// PUT / POST: Update Agent Profile
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $currentPassword = trim($input['current_password'] ?? '');
    $newPassword = trim($input['new_password'] ?? '');

    // Fetch current agent record
    $stmt = mysqli_prepare($conn, "SELECT id, email, password_hash FROM agents WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $agentId);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$current) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Agent not found."]);
        exit;
    }

    // Email unique check if changing email
    if (!empty($email) && $email !== $current['email']) {
        $chk = mysqli_prepare($conn, "SELECT id FROM agents WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($chk, "si", $email, $agentId);
        mysqli_stmt_execute($chk);
        if (mysqli_num_rows(mysqli_stmt_get_result($chk)) > 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Email address is already in use."]);
            exit;
        }
    }

    // Password update logic
    $passwordSqlUpdate = "";
    if (!empty($newPassword)) {
        if (empty($currentPassword) || !password_verify($currentPassword, $current['password_hash'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Current password is incorrect."]);
            exit;
        }
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updPass = mysqli_prepare($conn, "UPDATE agents SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($updPass, "si", $newHash, $agentId);
        mysqli_stmt_execute($updPass);
    }

    // General details update
    if (!empty($name) || !empty($email)) {
        $updName = !empty($name) ? $name : $current['name'];
        $updEmail = !empty($email) ? $email : $current['email'];

        $updStmt = mysqli_prepare($conn, "UPDATE agents SET name = ?, email = ? WHERE id = ?");
        mysqli_stmt_bind_param($updStmt, "ssi", $updName, $updEmail, $agentId);
        mysqli_stmt_execute($updStmt);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Profile updated successfully"
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
