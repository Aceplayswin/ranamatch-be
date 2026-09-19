<?php
/**
 * Admin Agent Applications API Endpoint
 * Endpoint: GET /admin/agents/applications/api_applications.php  or  POST /admin/agents/applications/api_applications.php
 * List applications, fetch uplines list, approve application (creates agent account via AgentTreeService), request info, or reject application.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';
require_once __DIR__ . '/../../../services/AgentTreeService.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);

// GET: List Applications or Uplines
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'uplines') {
        $r = mysqli_query($conn, "SELECT id, name, username, rank_level, agent_code FROM agents WHERE status = 'active' ORDER BY name ASC");
        $uplines = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $uplines[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $uplines]);
        exit;
    }

    $status = trim($_GET['status'] ?? 'pending');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];
    $typesStr = "";

    if (!empty($status) && $status !== 'ALL') {
        $where .= " AND aa.status = ?";
        $params[] = $status;
        $typesStr .= "s";
    }

    $sql = "SELECT aa.*, pa.name AS requested_upline_name, pa.agent_code AS requested_upline_code
            FROM agent_applications aa
            LEFT JOIN agents pa ON aa.requested_upline_id = pa.id
            $where
            ORDER BY aa.applied_at DESC
            LIMIT ?, ?";

    $params[] = $offset;
    $params[] = $limit;
    $typesStr .= "ii";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    echo json_encode([
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ]);
    exit;
}

// POST: Action on Application (Approve / Reject / Info)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $action = trim($input['action'] ?? 'approve'); // 'approve', 'reject', 'info', 'request_info'
    $applicationId = (int)($input['application_id'] ?? 0);
    $reason = trim($input['reason'] ?? $input['notes'] ?? '');

    if ($applicationId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid application_id required."]);
        exit;
    }

    // Fetch application
    $stmt = mysqli_prepare($conn, "SELECT * FROM agent_applications WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $applicationId);
    mysqli_stmt_execute($stmt);
    $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$app) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Application not found."]);
        exit;
    }

    if ($action === 'reject') {
        $upd = mysqli_prepare($conn, "UPDATE agent_applications SET status = 'rejected', rejection_reason = ?, processed_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $reason, $applicationId);
        mysqli_stmt_execute($upd);

        echo json_encode(["status" => "success", "message" => "Application rejected."]);
        exit;
    }

    if ($action === 'info' || $action === 'request_info') {
        $notes = trim($input['notes'] ?? $input['info_request_notes'] ?? $reason);
        if (empty($notes)) {
            $notes = "Please provide additional details regarding your target markets and player volume.";
        }
        $upd = mysqli_prepare($conn, "UPDATE agent_applications SET status = 'info_requested', info_request_notes = ?, processed_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $notes, $applicationId);
        mysqli_stmt_execute($upd);

        echo json_encode(["status" => "success", "message" => "Request for information sent to applicant."]);
        exit;
    }

    if ($action === 'approve') {
        $uplineVal = $input['upline_id'] ?? null;
        $uplineId = (!empty($uplineVal) && $uplineVal !== 'none' && $uplineVal !== '0') ? (int)$uplineVal : null;
        
        $rankLevel = trim($input['rank_level'] ?? $input['level'] ?? 'agent');
        $partnershipPct = (float)($input['partnership_pct'] ?? 25.00);
        $commissionPct = (float)($input['commission_pct'] ?? 2.00);
        $openingCredit = (float)($input['opening_credit'] ?? 0.00);
        $password = trim($input['password'] ?? 'agent123');

        $agentCode = 'AGT-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $username = strtolower($app['username'] ?? $agentCode);

        try {
            $treeService = new AgentTreeService($conn);
            $newAgentId = $treeService->createAgentWithTree($uplineId, [
                'agent_code' => $agentCode,
                'username' => $username,
                'name' => $app['full_name'],
                'email' => $app['email'],
                'phone' => $app['phone'] ?? '',
                'password_hash' => $app['password_hash'] ?? password_hash($password, PASSWORD_BCRYPT),
                'rank_level' => $rankLevel,
                'partnership_pct' => $partnershipPct,
                'turnover_commission_pct' => $commissionPct,
                'opening_credit' => $openingCredit
            ]);

            // Mark application approved
            $upd = mysqli_prepare($conn, "UPDATE agent_applications SET status = 'approved', processed_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, "i", $applicationId);
            mysqli_stmt_execute($upd);

            echo json_encode([
                "status" => "success",
                "message" => "Application approved and agent account created successfully",
                "data" => [
                    "agent_id" => $newAgentId,
                    "agent_code" => $agentCode,
                    "username" => $username,
                    "email" => $app['email']
                ]
            ]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Approval failed: " . $e->getMessage()]);
            exit;
        }
    }
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
