<?php
/**
 * Admin Affiliate Payout Management API Endpoint
 * Endpoint: GET /admin/affiliates/payouts/api_payouts.php  or  POST /admin/affiliates/payouts/api_payouts.php
 * List payouts, approve payout, mark paid with bank UTR, reject payout, or bulk process.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';
require_once __DIR__ . '/../../../services/PayoutService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

$accessObj = new AccessValidate();
$isAuthorized = ($accessObj->validate() === "true");

if (!$isAuthorized && (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session expired. Please re-login to the Admin Panel."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);
$payoutService = new PayoutService($conn);

// GET: List Payout Requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];
    $typesStr = "";

    if (!empty($status) && $status !== 'ALL') {
        $where .= " AND p.status = ?";
        $params[] = $status;
        $typesStr .= "s";
    }

    // Count summary
    $sumSql = "SELECT 
        COUNT(CASE WHEN status = 'requested' THEN 1 END) AS requested_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
        COALESCE(SUM(CASE WHEN status = 'requested' THEN amount ELSE 0 END), 0) AS requested_amount_total,
        COALESCE(SUM(CASE WHEN status = 'paid' AND MONTH(paid_at) = MONTH(CURRENT_DATE()) AND YEAR(paid_at) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END), 0) AS paid_month_total
    FROM affiliate_payouts";
    $sumRes = mysqli_query($conn, $sumSql);
    $summary = mysqli_fetch_assoc($sumRes);

    // Fetch paginated payouts
    $sql = "SELECT p.*, a.full_name AS affiliate_name, a.affiliate_code, a.email AS affiliate_email
            FROM affiliate_payouts p
            JOIN affiliates a ON a.id = p.affiliate_id
            $where
            ORDER BY p.requested_at DESC, p.id DESC
            LIMIT ?, ?";

    $params[] = $offset;
    $params[] = $limit;
    $typesStr .= "ii";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    $formattedRows = array_map(function($r) {
        return [
            "id" => (int)$r['id'],
            "affiliate_id" => (int)$r['affiliate_id'],
            "affiliate_name" => $r['affiliate_name'],
            "affiliate_code" => $r['affiliate_code'],
            "affiliate_email" => $r['affiliate_email'],
            "amount" => (float)$r['amount'],
            "status" => $r['status'],
            "payout_method_type" => $r['payout_method_type'],
            "payout_method_details" => json_decode($r['payout_method_details'] ?? '{}', true),
            "transaction_reference" => $r['transaction_reference'],
            "rejection_reason" => $r['rejection_reason'],
            "requested_at" => $r['requested_at'],
            "paid_at" => $r['paid_at']
        ];
    }, $rows);

    echo json_encode([
        "status" => "success",
        "summary" => [
            "requested_count" => (int)$summary['requested_count'],
            "approved_count" => (int)$summary['approved_count'],
            "requested_amount_total" => (float)$summary['requested_amount_total'],
            "paid_month_total" => (float)($summary['paid_month_total'] ?? 0)
        ],
        "pagination" => [
            "page" => $page,
            "limit" => $limit
        ],
        "data" => $formattedRows
    ]);
    exit;
}

// POST: Payout Actions (approve / pay / reject / bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? $_POST;

    $action = trim($input['action'] ?? '');
    $payoutId = (int)($input['payout_id'] ?? $input['id'] ?? 0);
    $payoutIds = $input['payout_ids'] ?? [];
    $txnRef = trim($input['transaction_reference'] ?? $input['utr'] ?? '');
    $reason = trim($input['rejection_reason'] ?? $input['reason'] ?? '');

    // Single Approve
    if ($action === 'approve') {
        if ($payoutId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "payout_id required."]);
            exit;
        }
        $ok = $payoutService->approvePayout($payoutId, $adminId);
        echo json_encode(["status" => $ok ? "success" : "error", "message" => $ok ? "Payout request approved." : "Failed to approve payout request."]);
        exit;
    }

    // Single Mark Paid
    if ($action === 'pay' || $action === 'mark_paid') {
        if ($payoutId <= 0 || empty($txnRef)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "payout_id and transaction_reference (UTR) required."]);
            exit;
        }
        $ok = $payoutService->markPaid($payoutId, $txnRef, $adminId);
        echo json_encode(["status" => $ok ? "success" : "error", "message" => $ok ? "Payout marked as paid successfully." : "Failed to mark payout paid."]);
        exit;
    }

    // Single Reject
    if ($action === 'reject') {
        if ($payoutId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "payout_id required."]);
            exit;
        }
        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Rejection reason is required."]);
            exit;
        }
        $ok = $payoutService->rejectPayout($payoutId, $reason, $adminId);
        echo json_encode(["status" => $ok ? "success" : "error", "message" => $ok ? "Payout request rejected and balance refunded." : "Failed to reject payout."]);
        exit;
    }

    // Bulk Action
    if ($action === 'bulk') {
        $bulkAction = trim($input['bulk_action'] ?? 'approve');
        if (empty($payoutIds) || !is_array($payoutIds)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Array of payout_ids required."]);
            exit;
        }

        $processed = 0;
        foreach ($payoutIds as $pid) {
            $pidInt = (int)$pid;
            if ($bulkAction === 'approve') {
                if ($payoutService->approvePayout($pidInt, $adminId)) $processed++;
            } elseif ($bulkAction === 'reject') {
                if ($payoutService->rejectPayout($pidInt, $reason, $adminId)) $processed++;
            }
        }

        echo json_encode(["status" => "success", "message" => "Bulk $bulkAction completed for $processed payouts."]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
