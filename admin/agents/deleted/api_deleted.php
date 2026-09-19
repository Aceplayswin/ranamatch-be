<?php
/**
 * Admin API for Deleted Agent Accounts Archive & Restoration System
 * Endpoint: /admin/agents/deleted/api_deleted.php
 * Actions: list, detail, restore_immediate, schedule_restore, cancel_schedule
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';

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

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection not available."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);
$adminUser = $_SESSION['admin_username'] ?? ('Admin #' . $adminId);

// Read JSON input or GET/POST params
$inputRaw = file_get_contents('php://input');
$inputData = json_decode($inputRaw, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $inputData['action'] ?? 'list';

// Automatically process any due scheduled restorations
processDueRestorations($conn);

// ─── LIST all archived accounts ───────────────────────────────────────────────
if ($action === 'list') {
    $search = trim($_GET['search'] ?? $inputData['search'] ?? '');

    $sql = "SELECT id, agent_id, agent_code, username, name, email, rank_level,
                   deletion_reason, deleted_by_admin, current_credit, exposed_credit,
                   total_turnover_commission, total_net_pnl, total_net_earnings,
                   downline_agents_count, downline_players_count, created_at, deleted_at,
                   status, scheduled_restore_at, restored_at, restored_by_admin
            FROM deleted_agent_archive
            WHERE status <> 'restored'";

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (agent_code LIKE '%$s%' OR username LIKE '%$s%' OR name LIKE '%$s%'
                         OR email LIKE '%$s%' OR deletion_reason LIKE '%$s%')";
    }
    $sql .= " ORDER BY id DESC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Query error: " . mysqli_error($conn)]);
        exit;
    }

    $items = [];
    $totalTurnoverComm = 0.0;
    $totalNetPnl       = 0.0;
    $totalEarnings     = 0.0;

    while ($row = mysqli_fetch_assoc($result)) {
        $row['id']                        = (int)$row['id'];
        $row['agent_id']                  = (int)$row['agent_id'];
        $row['current_credit']            = (float)$row['current_credit'];
        $row['exposed_credit']            = (float)$row['exposed_credit'];
        $row['total_turnover_commission'] = (float)$row['total_turnover_commission'];
        $row['total_net_pnl']             = (float)$row['total_net_pnl'];
        $row['total_net_earnings']        = (float)$row['total_net_earnings'];
        $row['downline_agents_count']     = (int)$row['downline_agents_count'];
        $row['downline_players_count']    = (int)$row['downline_players_count'];
        $row['status']                    = !empty($row['status']) ? $row['status'] : 'archived';

        $totalTurnoverComm += $row['total_turnover_commission'];
        $totalNetPnl       += $row['total_net_pnl'];
        $totalEarnings     += $row['total_net_earnings'];

        $items[] = $row;
    }

    echo json_encode([
        "status"  => "success",
        "count"   => count($items),
        "summary" => [
            "total_deleted_accounts"       => count($items),
            "aggregate_turnover_commission" => round($totalTurnoverComm, 2),
            "aggregate_net_pnl"            => round($totalNetPnl, 2),
            "aggregate_net_earnings"       => round($totalEarnings, 2)
        ],
        "data"    => $items
    ]);
    exit;
}

// ─── DETAIL: full snapshot for one archive record ─────────────────────────────
if ($action === 'detail') {
    $archiveId = (int)($_GET['id'] ?? $inputData['id'] ?? 0);
    if ($archiveId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid archive ID."]);
        exit;
    }

    $res = mysqli_query($conn, "SELECT * FROM deleted_agent_archive WHERE id = $archiveId LIMIT 1");
    if (!$res) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Query error: " . mysqli_error($conn)]);
        exit;
    }

    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $row['snapshot_metadata'] = json_decode($row['snapshot_metadata'], true);
        echo json_encode(["status" => "success", "data" => $row]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Archived record not found."]);
    }
    exit;
}

// ─── RESTORE IMMEDIATE ────────────────────────────────────────────────────────
if ($action === 'restore_immediate') {
    $archiveId = (int)($inputData['archive_id'] ?? $_POST['archive_id'] ?? $_GET['id'] ?? 0);
    if ($archiveId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid archive_id required."]);
        exit;
    }

    $res = performAgentRestoration($conn, $archiveId, $adminUser);
    if ($res['status'] === 'success') {
        echo json_encode($res);
    } else {
        http_response_code(400);
        echo json_encode($res);
    }
    exit;
}

// ─── SCHEDULE RESTORE ─────────────────────────────────────────────────────────
if ($action === 'schedule_restore') {
    $archiveId = (int)($inputData['archive_id'] ?? $_POST['archive_id'] ?? 0);
    $timingType = trim($inputData['timing_type'] ?? $_POST['timing_type'] ?? 'immediate');
    $customDatetime = trim($inputData['custom_datetime'] ?? $_POST['custom_datetime'] ?? '');

    if ($archiveId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid archive_id required."]);
        exit;
    }

    if ($timingType === 'immediate' || $timingType === 'now') {
        $res = performAgentRestoration($conn, $archiveId, $adminUser);
        echo json_encode($res);
        exit;
    }

    $targetTime = null;
    if ($timingType === '1_hour') {
        $targetTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
    } else if ($timingType === '6_hours') {
        $targetTime = date('Y-m-d H:i:s', strtotime('+6 hours'));
    } else if ($timingType === '24_hours') {
        $targetTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
    } else if ($timingType === 'custom' && !empty($customDatetime)) {
        $timestamp = strtotime($customDatetime);
        if ($timestamp === false || $timestamp <= time()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Custom scheduled restoration date must be in the future."]);
            exit;
        }
        $targetTime = date('Y-m-d H:i:s', $timestamp);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid timing option or missing custom datetime."]);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE deleted_agent_archive SET status = 'scheduled', scheduled_restore_at = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $targetTime, $archiveId);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Account restoration scheduled for " . date('M j, Y h:i A', strtotime($targetTime)),
            "data" => [
                "archive_id" => $archiveId,
                "scheduled_restore_at" => $targetTime
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to schedule restoration: " . mysqli_error($conn)]);
    }
    exit;
}

// ─── CANCEL SCHEDULE ──────────────────────────────────────────────────────────
if ($action === 'cancel_schedule') {
    $archiveId = (int)($inputData['archive_id'] ?? $_POST['archive_id'] ?? 0);
    if ($archiveId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid archive_id required."]);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE deleted_agent_archive SET status = 'archived', scheduled_restore_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $archiveId);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Scheduled restoration canceled.",
            "data" => ["archive_id" => $archiveId]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to cancel schedule."]);
    }
    exit;
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid action."]);
exit;


// ─── HELPER FUNCTIONS ──────────────────────────────────────────────────────────

function performAgentRestoration($conn, $archiveId, $restoredBy) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM deleted_agent_archive WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $archiveId);
    mysqli_stmt_execute($stmt);
    $arc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$arc) {
        return ["status" => "error", "message" => "Archive record not found."];
    }

    if ($arc['status'] === 'restored') {
        return ["status" => "error", "message" => "This account has already been restored."];
    }

    $snap = json_decode($arc['snapshot_metadata'] ?? '{}', true);
    $agentData = $snap['agent'] ?? null;

    if (!$agentData || empty($agentData['agent_code'])) {
        return ["status" => "error", "message" => "Snapshot metadata invalid or corrupt."];
    }

    $agentId = (int)$agentData['id'];
    $agentCode = mysqli_real_escape_string($conn, $agentData['agent_code']);
    $username = mysqli_real_escape_string($conn, $agentData['username']);

    // Check if active agent record already exists
    $chk = mysqli_query($conn, "SELECT id FROM agents WHERE id = $agentId OR agent_code = '$agentCode' OR username = '$username'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        return ["status" => "error", "message" => "An active agent account with code '$agentCode' or username '$username' already exists in system."];
    }

    mysqli_begin_transaction($conn);
    try {
        // Re-insert Agent into `agents` table
        $cols = [];
        $vals = [];
        $types = "";
        $params = [];

        foreach ($agentData as $key => $val) {
            $cols[] = "`$key`";
            $vals[] = "?";
            if (is_int($val)) { $types .= "i"; }
            else if (is_float($val)) { $types .= "d"; }
            else { $types .= "s"; }
            $params[] = $val;
        }

        $insSql = "INSERT INTO agents (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
        $insStmt = mysqli_prepare($conn, $insSql);
        mysqli_stmt_bind_param($insStmt, $types, ...$params);
        mysqli_stmt_execute($insStmt);

        // Re-insert Closure Tree Node for self
        $treeSelf = mysqli_prepare($conn, "INSERT IGNORE INTO agent_tree (ancestor_id, descendant_id, depth) VALUES (?, ?, 0)");
        mysqli_stmt_bind_param($treeSelf, "ii", $agentId, $agentId);
        mysqli_stmt_execute($treeSelf);

        // Re-insert Closure Tree Nodes from parent if parent_id exists
        $parentId = (int)($agentData['parent_id'] ?? 0);
        if ($parentId > 0) {
            $pChk = mysqli_query($conn, "SELECT id FROM agents WHERE id = $parentId");
            if ($pChk && mysqli_num_rows($pChk) > 0) {
                $treeParent = mysqli_prepare($conn, "INSERT IGNORE INTO agent_tree (ancestor_id, descendant_id, depth) SELECT ancestor_id, ?, depth + 1 FROM agent_tree WHERE descendant_id = ?");
                mysqli_stmt_bind_param($treeParent, "ii", $agentId, $parentId);
                mysqli_stmt_execute($treeParent);
            }
        }

        // Update archive record status to restored
        $updArc = mysqli_prepare($conn, "UPDATE deleted_agent_archive SET status = 'restored', restored_at = NOW(), restored_by_admin = ?, scheduled_restore_at = NULL WHERE id = ?");
        mysqli_stmt_bind_param($updArc, "si", $restoredBy, $archiveId);
        mysqli_stmt_execute($updArc);

        $agentAudit = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, 'admin.agent.restored', ?, 'Admin System', ?, NOW())");
        $restoreMeta = json_encode([
            'user' => $restoredBy,
            'target' => 'agent:' . $agentId,
            'archive_id' => $archiveId,
            'restored_by' => $restoredBy
        ]);
        $restoreIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!$agentAudit || !mysqli_stmt_bind_param($agentAudit, "iss", $agentId, $restoreIp, $restoreMeta) || !mysqli_stmt_execute($agentAudit)) {
            throw new Exception('Failed to record restoration activity log.');
        }

        mysqli_commit($conn);
        return [
            "status" => "success",
            "message" => "Agent account '$username' ($agentCode) successfully restored to active directory!",
            "data" => [
                "agent_id" => $agentId,
                "agent_code" => $agentCode,
                "username" => $username
            ]
        ];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ["status" => "error", "message" => "Restoration transaction failed: " . $e->getMessage()];
    }
}

function processDueRestorations($conn) {
    $now = date('Y-m-d H:i:s');
    $dueRes = mysqli_query($conn, "SELECT id, deleted_by_admin FROM deleted_agent_archive WHERE status = 'scheduled' AND scheduled_restore_at <= '$now'");
    if ($dueRes && mysqli_num_rows($dueRes) > 0) {
        while ($row = mysqli_fetch_assoc($dueRes)) {
            performAgentRestoration($conn, (int)$row['id'], 'Auto Scheduled Cron');
        }
    }
}

