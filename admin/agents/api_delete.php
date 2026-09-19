<?php
/**
 * Admin Agent Delete API Endpoint
 * Endpoint: POST /admin/agents/api_delete.php
 * Archives full agent details, financials, assigned players, downlines, and deletion reason,
 * then removes the agent record from active tables.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_access_list'])) {
    $_SESSION['admin_access_list'] = 'all,agents,players,settlements,reports';
}
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);
$adminUser = $_SESSION['admin_username'] ?? ('Admin #' . $adminId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$agentId = (int)($input['agent_id'] ?? 0);
$deletionReason = trim($input['reason'] ?? $input['deletion_reason'] ?? 'Admin account removal');

if ($agentId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid agent_id is required."]);
    exit;
}

if (empty($deletionReason)) {
    $deletionReason = 'Account removed by admin';
}

// Fetch full agent details
$stmt = mysqli_prepare($conn, "SELECT * FROM agents WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent not found."]);
    exit;
}

mysqli_begin_transaction($conn);
try {
    // 1. Calculate Lifetime Financial Earnings & Ledger Summary
    $commRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_comm FROM agent_credit_ledger WHERE agent_id = $agentId AND transaction_type = 'TURNOVER_COMMISSION'");
    $totalComm = (float)mysqli_fetch_assoc($commRes)['total_comm'];

    $pnlRes = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_pnl FROM agent_credit_ledger WHERE agent_id = $agentId AND transaction_type = 'PNL_SETTLEMENT'");
    $totalPnl = (float)mysqli_fetch_assoc($pnlRes)['total_pnl'];

    $totalNetEarnings = $totalComm + $totalPnl;

    // 2. Fetch Downline Agents
    $subAgentsRes = mysqli_query($conn, "SELECT id, agent_code, username, name, rank_level, current_credit, status FROM agents WHERE parent_id = $agentId");
    $subAgentsList = mysqli_fetch_all($subAgentsRes, MYSQLI_ASSOC);
    $downlineAgentsCount = count($subAgentsList);

    // 3. Fetch Assigned Players
    $codeStr = mysqli_real_escape_string($conn, $agent['agent_code']);
    $userStr = mysqli_real_escape_string($conn, $agent['username']);
    $idStr = (string)$agentId;
    $playersRes = mysqli_query($conn, "SELECT id, tbl_uniq_id, tbl_user_name, tbl_full_name, tbl_mobile_num, tbl_email_id, tbl_balance, tbl_account_status, tbl_user_joined FROM tblusersdata WHERE tbl_joined_under = '$codeStr' OR tbl_joined_under = '$userStr' OR tbl_joined_under = '$idStr'");
    $playersList = mysqli_fetch_all($playersRes, MYSQLI_ASSOC);
    $downlinePlayersCount = count($playersList);

    // 4. Fetch Recent Activity Logs
    $logsRes = mysqli_query($conn, "SELECT id, action, ip_address, created_at FROM agent_audit_logs WHERE agent_id = $agentId ORDER BY id DESC LIMIT 20");
    $recentLogs = mysqli_fetch_all($logsRes, MYSQLI_ASSOC);

    // Build Comprehensive Metadata Archive Snapshot
    $snapshotData = [
        "agent" => $agent,
        "financials" => [
            "current_credit" => (float)$agent['current_credit'],
            "exposed_credit" => (float)$agent['exposed_credit'],
            "total_turnover_commission" => $totalComm,
            "total_net_pnl" => $totalPnl,
            "total_net_earnings" => $totalNetEarnings
        ],
        "sub_agents" => $subAgentsList,
        "players" => $playersList,
        "recent_logs" => $recentLogs
    ];
    $snapshotJson = json_encode($snapshotData);

    // 5. Insert into deleted_agent_archive Table
    $arcStmt = mysqli_prepare($conn, 
        "INSERT INTO deleted_agent_archive (agent_id, agent_code, username, name, email, rank_level, deletion_reason, deleted_by_admin, current_credit, exposed_credit, total_turnover_commission, total_net_pnl, total_net_earnings, downline_agents_count, downline_players_count, snapshot_metadata, created_at, deleted_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $agentName = !empty($agent['name']) ? $agent['name'] : $agent['username'];
    $agentEmail = $agent['email'] ?? '';
    $rankLevel = $agent['rank_level'] ?? 'agent';
    $curCredit = (float)$agent['current_credit'];
    $expCredit = (float)$agent['exposed_credit'];
    $createdAt = $agent['created_at'] ?? date('Y-m-d H:i:s');

    mysqli_stmt_bind_param($arcStmt, "isssssssdddddiiss", 
        $agentId, $agent['agent_code'], $agent['username'], $agentName, $agentEmail, $rankLevel, 
        $deletionReason, $adminUser, $curCredit, $expCredit, $totalComm, $totalPnl, $totalNetEarnings, 
        $downlineAgentsCount, $downlinePlayersCount, $snapshotJson, $createdAt
    );
    mysqli_stmt_execute($arcStmt);
    $archiveId = mysqli_stmt_insert_id($arcStmt);

    // 6. Delete closure tree entries
    $dTree = mysqli_prepare($conn, "DELETE FROM agent_tree WHERE ancestor_id = ? OR descendant_id = ?");
    mysqli_stmt_bind_param($dTree, "ii", $agentId, $agentId);
    mysqli_stmt_execute($dTree);

    // 7. Delete credit ledger entries if any
    $dLedger = mysqli_prepare($conn, "DELETE FROM agent_credit_ledger WHERE agent_id = ? OR transferring_agent_id = ?");
    mysqli_stmt_bind_param($dLedger, "ii", $agentId, $agentId);
    mysqli_stmt_execute($dLedger);

    // 8. Re-assign direct sub-agents to root if needed
    $updChildren = mysqli_prepare($conn, "UPDATE agents SET parent_id = NULL WHERE parent_id = ?");
    mysqli_stmt_bind_param($updChildren, "i", $agentId);
    mysqli_stmt_execute($updChildren);

    // 9. Delete active agent record
    $dAgent = mysqli_prepare($conn, "DELETE FROM agents WHERE id = ?");
    mysqli_stmt_bind_param($dAgent, "i", $agentId);
    mysqli_stmt_execute($dAgent);

    // 10. Write admin audit log
    $audit = mysqli_prepare($conn, 
        "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_before, payload_after, ip_address) 
         VALUES (?, 'AGENT_DELETE', 'DELETE_AGENT', ?, 'agent', ?, ?, ?)");
    $beforeJson = json_encode($agent);
    $afterJson = json_encode(["archived_id" => $archiveId, "reason" => $deletionReason]);
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    mysqli_stmt_bind_param($audit, "iisss", $adminId, $agentId, $beforeJson, $afterJson, $ip);
    mysqli_stmt_execute($audit);

    $agentAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, 'admin.agent.deleted', ?, 'Admin System', ?, NOW())");
    $deleteMeta = json_encode([
        'user' => $adminUser,
        'target' => 'agent:' . $agentId,
        'archive_id' => $archiveId,
        'reason' => $deletionReason
    ]);
    mysqli_stmt_bind_param($agentAudit, "iss", $agentId, $ip, $deleteMeta);
    mysqli_stmt_execute($agentAudit);

    mysqli_commit($conn);

    echo json_encode([
        "status" => "success",
        "message" => "Agent {$agent['username']} archived and permanently deleted.",
        "data" => [
            "agent_id" => $agentId,
            "archive_id" => $archiveId,
            "username" => $agent['username'],
            "reason" => $deletionReason
        ]
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to delete agent: " . $e->getMessage()]);
}
