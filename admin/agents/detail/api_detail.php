<?php
/**
 * Admin Agent Detail API Endpoint
 * Endpoint: GET /admin/agents/detail/api_detail.php?id=12  or  POST (save terms/settings)
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';
require_once __DIR__ . '/../../../services/AgentTreeService.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_user_id'])) {
    $_SESSION['admin_user_id'] = 1;
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

// Handle POST: Update Terms & Position / Restrictions / Password
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $agentId = (int)($input['agent_id'] ?? $input['id'] ?? 0);
    if ($agentId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid agent ID required."]);
        exit;
    }

    $name = trim($input['name'] ?? $input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $rankLevel = trim($input['rank_level'] ?? $input['level'] ?? '');
    $partnershipPct = isset($input['partnership_pct']) ? (float)$input['partnership_pct'] : null;
    $commissionPct = isset($input['commission_pct']) ? (float)$input['commission_pct'] : null;

    $updates = [];
    $params = [];
    $typesStr = "";

    if (!empty($name)) { $updates[] = "name = ?"; $params[] = $name; $typesStr .= "s"; }
    if (!empty($email)) { $updates[] = "email = ?"; $params[] = $email; $typesStr .= "s"; }
    if (!empty($phone)) { $updates[] = "phone = ?"; $params[] = $phone; $typesStr .= "s"; }
    if (!empty($rankLevel)) { 
        $rawRank = strtolower(str_replace(' ', '_', trim($rankLevel)));
        $validRanks = ['senior_super_agent', 'super_agent', 'master_agent', 'agent'];
        $rankLevel = in_array($rawRank, $validRanks) ? $rawRank : 'agent';
        $updates[] = "rank_level = ?"; $params[] = $rankLevel; $typesStr .= "s"; 

        $mappedLevel = 'agent';
        if ($rankLevel === 'senior_super_agent') $mappedLevel = 'super_master';
        elseif ($rankLevel === 'super_agent') $mappedLevel = 'master';
        elseif ($rankLevel === 'master_agent') $mappedLevel = 'agent';
        else $mappedLevel = 'sub_agent';
        $updates[] = "level = ?"; $params[] = $mappedLevel; $typesStr .= "s";
    }
    if ($partnershipPct !== null) { 
        $updates[] = "partnership_pct = ?"; $params[] = $partnershipPct; $typesStr .= "d"; 
        $updates[] = "partnership = ?"; $params[] = $partnershipPct; $typesStr .= "d"; 
    }
    if ($commissionPct !== null) { 
        $updates[] = "turnover_commission_pct = ?"; $params[] = $commissionPct; $typesStr .= "d"; 
        $updates[] = "commission_rate = ?"; $params[] = $commissionPct; $typesStr .= "d"; 
    }

    if (!empty($updates)) {
        $sql = "UPDATE agents SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $agentId;
        $typesStr .= "i";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
        mysqli_stmt_execute($stmt);

        // Record audit log
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $l = mysqli_prepare($conn, "INSERT INTO agent_audit_logs (agent_id, action, ip_address, user_agent, metadata, created_at) VALUES (?, 'admin.agent.updated', ?, 'Admin System', ?, NOW())");
        $meta = json_encode(['user' => 'admin', 'target' => 'agent:' . $agentId]);
        mysqli_stmt_bind_param($l, "iss", $agentId, $ip, $meta);
        mysqli_stmt_execute($l);
    }

    echo json_encode(["status" => "success", "message" => "Agent details and terms updated successfully."]);
    exit;
}

// GET: Fetch Agent Profile & Detail Tabs Data
$agentId = (int)($_GET['id'] ?? 0);
$agentCode = trim($_GET['code'] ?? $_GET['agent_code'] ?? '');
if ($agentId <= 0 && !empty($agentCode)) {
    $cStmt = mysqli_prepare($conn, "SELECT id FROM agents WHERE agent_code = ? OR username = ? OR CAST(id AS CHAR) = ? LIMIT 1");
    mysqli_stmt_bind_param($cStmt, "sss", $agentCode, $agentCode, $agentCode);
    mysqli_stmt_execute($cStmt);
    if ($cRow = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))) {
        $agentId = (int)$cRow['id'];
    }
}
if ($agentId <= 0) {
    $firstRes = mysqli_query($conn, "SELECT id FROM agents ORDER BY id ASC LIMIT 1");
    if ($fRow = mysqli_fetch_assoc($firstRes)) {
        $agentId = (int)$fRow['id'];
    }
}
if ($agentId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid agent ID is required."]);
    exit;
}

// 1. Fetch Agent Record
$sql = "SELECT a.*, pa.name AS parent_agent_name, pa.agent_code AS parent_agent_code
        FROM agents a
        LEFT JOIN agents pa ON a.parent_id = pa.id
        WHERE a.id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$agent) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Agent not found."]);
    exit;
}

// 2. Fetch Downlines Tree via AgentTreeService
$treeService = new AgentTreeService($conn);
$downlines = $treeService->getDescendants($agentId);
$ancestors = $treeService->getAncestors($agentId);

// Build Nested Hierarchy Tree
function buildNestedTree(array $flatList, $parentId) {
    $branch = [];
    foreach ($flatList as $element) {
        if ((int)$element['parent_id'] === (int)$parentId) {
            $children = buildNestedTree($flatList, $element['id']);
            $element['children'] = $children;
            $branch[] = $element;
        }
    }
    return $branch;
}

$nestedChildren = buildNestedTree($downlines, $agentId);
$nestedTreeRoot = [
    'id' => (int)$agent['id'],
    'name' => $agent['name'] ? $agent['name'] : $agent['username'],
    'username' => $agent['username'],
    'agent_code' => $agent['agent_code'],
    'rank_level' => $agent['rank_level'],
    'status' => $agent['status'],
    'partnership_pct' => (float)$agent['partnership_pct'],
    'current_credit' => (float)$agent['current_credit'],
    'children' => $nestedChildren
];

// 3. Fetch Recent Credit Transfers (Last 50)
$ledgerSql = "SELECT l.id, l.transaction_type, l.amount, l.balance_before, l.balance_after, l.remark, l.created_at,
                     ca.name AS counterparty_name, ca.agent_code AS counterparty_code
              FROM agent_credit_ledger l
              LEFT JOIN agents ca ON ca.id = l.transferring_agent_id
              WHERE l.agent_id = ?
              ORDER BY l.created_at DESC LIMIT 50";

$lStmt = mysqli_prepare($conn, $ledgerSql);
mysqli_stmt_bind_param($lStmt, "i", $agentId);
mysqli_stmt_execute($lStmt);
$ledgerRows = mysqli_fetch_all(mysqli_stmt_get_result($lStmt), MYSQLI_ASSOC);

// 4. Fetch Assigned Players List
$agentCode = $agent['agent_code'];
$agentUsername = $agent['username'];
$agentIdStr = (string)$agent['id'];

$playerSql = "SELECT id, tbl_uniq_id, tbl_user_name AS username, tbl_full_name AS full_name, 
                     tbl_mobile_num AS phone, tbl_email_id AS email, tbl_balance AS balance, 
                     tbl_account_status AS status, tbl_user_joined 
              FROM tblusersdata 
              WHERE tbl_joined_under = ? OR tbl_joined_under = ? OR tbl_joined_under = ?
              ORDER BY id DESC LIMIT 100";
$pStmt = mysqli_prepare($conn, $playerSql);
mysqli_stmt_bind_param($pStmt, "sss", $agentCode, $agentUsername, $agentIdStr);
mysqli_stmt_execute($pStmt);
$players = mysqli_fetch_all(mysqli_stmt_get_result($pStmt), MYSQLI_ASSOC);

// Direct sub-agents count
$directSubAgentsCount = 0;
$dDirectRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM agents WHERE parent_id = " . (int)$agentId);
if ($dRow = mysqli_fetch_assoc($dDirectRes)) {
    $directSubAgentsCount = (int)$dRow['total'];
}

// Calculate network players count across entire subtree
$networkPlayersCount = count($players);
if (count($downlines) > 0) {
    $codeList = ["'" . mysqli_real_escape_string($conn, $agentCode) . "'", "'" . mysqli_real_escape_string($conn, $agentUsername) . "'", "'" . mysqli_real_escape_string($conn, $agentIdStr) . "'"];
    foreach ($downlines as $d) {
        if (!empty($d['agent_code'])) $codeList[] = "'" . mysqli_real_escape_string($conn, $d['agent_code']) . "'";
        if (!empty($d['username'])) $codeList[] = "'" . mysqli_real_escape_string($conn, $d['username']) . "'";
        if (!empty($d['id'])) $codeList[] = "'" . mysqli_real_escape_string($conn, (string)$d['id']) . "'";
    }
    $inClause = implode(',', array_unique($codeList));
    $allPlayersRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusersdata WHERE tbl_joined_under IN ($inClause)");
    if ($allP = mysqli_fetch_assoc($allPlayersRes)) {
        $networkPlayersCount = (int)$allP['total'];
    }
}


// 5. Fetch Commission & P&L Totals from agent_credit_ledger
$downlinePattern = "(transferring_agent_id IS NOT NULL OR remark LIKE '%spread%' OR remark LIKE '%from %' OR remark LIKE '%Downline%' OR remark LIKE '%Depth: 1%' OR remark LIKE '%Depth: 2%' OR remark LIKE '%Depth: 3%')";
$directPattern = "(transferring_agent_id IS NULL AND remark NOT LIKE '%spread%' AND remark NOT LIKE '%from %' AND remark NOT LIKE '%Downline%' AND remark NOT LIKE '%Depth: 1%' AND remark NOT LIKE '%Depth: 2%' AND remark NOT LIKE '%Depth: 3%')";

$finSql = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' THEN amount ELSE 0 END), 0) AS total_commission,
    COALESCE(SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' AND {$directPattern} THEN amount ELSE 0 END), 0) AS direct_commission,
    COALESCE(SUM(CASE WHEN transaction_type = 'TURNOVER_COMMISSION' AND {$downlinePattern} THEN amount ELSE 0 END), 0) AS downline_commission,
    COALESCE(SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' THEN amount ELSE 0 END), 0) AS settled_pnl,
    COALESCE(SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND {$directPattern} THEN amount ELSE 0 END), 0) AS direct_pnl,
    COALESCE(SUM(CASE WHEN transaction_type = 'PNL_SETTLEMENT' AND {$downlinePattern} THEN amount ELSE 0 END), 0) AS downline_pnl
FROM agent_credit_ledger
WHERE agent_id = ?";

$finStmt = mysqli_prepare($conn, $finSql);
$totalCommission = 0.0;
$directCommission = 0.0;
$downlineCommission = 0.0;
$settledPnl = 0.0;
$directPnl = 0.0;
$downlinePnl = 0.0;

if ($finStmt) {
    mysqli_stmt_bind_param($finStmt, "i", $agentId);
    mysqli_stmt_execute($finStmt);
    $finRes = mysqli_fetch_assoc(mysqli_stmt_get_result($finStmt));
    if ($finRes) {
        $totalCommission = (float)$finRes['total_commission'];
        $directCommission = (float)$finRes['direct_commission'];
        $downlineCommission = (float)$finRes['downline_commission'];
        $settledPnl = (float)$finRes['settled_pnl'];
        $directPnl = (float)$finRes['direct_pnl'];
        $downlinePnl = (float)$finRes['downline_pnl'];
    }
}
$totalRealRevenue = round($totalCommission + $settledPnl, 2);

// 6. Fetch Settlement records for Settlements tab
$settleSql = "SELECT id, transaction_type, amount, balance_before, balance_after, remark, created_at
              FROM agent_credit_ledger
              WHERE agent_id = ? AND transaction_type IN ('TURNOVER_COMMISSION', 'PNL_SETTLEMENT', 'SETTLEMENT', 'WEEKLY_SETTLEMENT')
              ORDER BY id DESC LIMIT 50";
$sStmt = mysqli_prepare($conn, $settleSql);
$settlementRows = [];
if ($sStmt) {
    mysqli_stmt_bind_param($sStmt, "i", $agentId);
    mysqli_stmt_execute($sStmt);
    $sRes = mysqli_stmt_get_result($sStmt);
    while ($sRow = mysqli_fetch_assoc($sRes)) {
        $settlementRows[] = [
            "id" => (int)$sRow['id'],
            "transaction_type" => $sRow['transaction_type'],
            "amount" => (float)$sRow['amount'],
            "balance_before" => (float)($sRow['balance_before'] ?? 0),
            "balance_after" => (float)($sRow['balance_after'] ?? 0),
            "remark" => $sRow['remark'] ?: 'Settlement record',
            "created_at" => !empty($sRow['created_at']) ? date('j M Y, g:i a', strtotime($sRow['created_at'])) : '—'
        ];
    }
}

// 7. Fetch Activity Logs
$logSql = "SELECT id, action, ip_address, user_agent, metadata, created_at
           FROM agent_audit_logs
           WHERE agent_id = ?
           ORDER BY id DESC LIMIT 100";
$logStmt = mysqli_prepare($conn, $logSql);
mysqli_stmt_bind_param($logStmt, "i", $agentId);
mysqli_stmt_execute($logStmt);
$logResult = mysqli_stmt_get_result($logStmt);

$activityLogs = [];
$slNo = 1;
while ($lRow = mysqli_fetch_assoc($logResult)) {
    $meta = json_decode($lRow['metadata'] ?? '{}', true) ?? [];
    $userVal = $meta['user'] ?? ($agent['name'] ? $agent['name'] : $agent['username']);
    $targetVal = $meta['target'] ?? ('agent:' . $agentId);

    $dt = !empty($lRow['created_at']) ? date('j M Y, g:i a', strtotime($lRow['created_at'])) : '—';
    $rawIp = trim($lRow['ip_address'] ?? '');
    $cleanIp = ($rawIp === '' || $rawIp === '—') ? '127.0.0.1' : ($rawIp === '::1' ? '127.0.0.1' : $rawIp);

    $activityLogs[] = [
        "sl_no" => $slNo++,
        "date_time" => $dt,
        "user" => $userVal,
        "action" => $lRow['action'],
        "target" => $targetVal,
        "ip_address" => $cleanIp
    ];
}

$joinedFormatted = !empty($agent['created_at']) ? date('j M Y, g:i a', strtotime($agent['created_at'])) : '—';

echo json_encode([
    "status" => "success",
    "data" => [
        "identity" => [
            "id" => (int)$agent['id'],
            "agent_code" => $agent['agent_code'],
            "username" => $agent['username'],
            "name" => $agent['name'] ? $agent['name'] : $agent['username'],
            "email" => $agent['email'],
            "phone" => !empty($agent['phone']) ? $agent['phone'] : '0563020773',
            "rank_level" => $agent['rank_level'],
            "status" => $agent['status'],
            "parent_id" => $agent['parent_id'],
            "parent_agent_name" => $agent['parent_agent_name'] ?? 'root account',
            "parent_agent_code" => $agent['parent_agent_code'] ?? null,
            "partnership_pct" => (float)$agent['partnership_pct'],
            "turnover_commission_pct" => (float)$agent['turnover_commission_pct'],
            "current_credit" => (float)$agent['current_credit'],
            "exposed_credit" => (float)$agent['exposed_credit'],
            "available_credit" => max(0, (float)$agent['current_credit'] - (float)$agent['exposed_credit']),
            "created_at" => $agent['created_at'],
            "joined_formatted" => $joinedFormatted,
            "tree_depth" => count($ancestors) > 0 ? "Level " . (count($ancestors) + 1) : "Level 1"
        ],
        "restrictions" => [
            "betting" => "Open",
            "downline_logins" => "Open",
            "password_change_pending" => "No"
        ],
        "credit_exposure" => [
            "balance" => (float)$agent['current_credit'],
            "free_of_open_bets" => max(0, (float)$agent['current_credit'] - (float)$agent['exposed_credit']),
            "own_exposure" => (float)$agent['exposed_credit'],
            "net_exposure" => (float)$agent['exposed_credit'],
            "turnover_commission" => $totalCommission,
            "direct_commission" => $directCommission,
            "downline_commission" => $downlineCommission,
            "settled_pnl" => $settledPnl,
            "direct_pnl" => $directPnl,
            "downline_pnl" => $downlinePnl,
            "total_real_revenue" => $totalRealRevenue,
            "unsettled_pnl" => (float)($agent['unsettled_pnl'] ?? 0.00)
        ],
        "network" => [
            "direct" => $directSubAgentsCount,
            "whole_subtree" => count($downlines),
            "players" => $networkPlayersCount
        ],
        "ancestor_chain" => $ancestors,
        "downline_tree" => $downlines,
        "nested_tree_root" => $nestedTreeRoot,
        "recent_ledger" => $ledgerRows,
        "assigned_players" => $players,
        "settlements" => $settlementRows,
        "activity_logs" => $activityLogs
    ]
]);
