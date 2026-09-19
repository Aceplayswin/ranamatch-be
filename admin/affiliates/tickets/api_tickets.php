<?php
/**
 * Admin Affiliate Support Tickets API Endpoint
 * Handles fetching affiliate tickets list, single ticket conversation, sending replies, and changing ticket status.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../../security/config.php';
require_once __DIR__ . '/../../access_validate.php';
require_once __DIR__ . '/../../../services/NotificationHelper.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

$adminId = (int)($_SESSION['admin_user_id'] ?? 1);

// 1. POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = strtolower(trim($input['action'] ?? ''));
    $ticketId = (int)($input['ticket_id'] ?? 0);

    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing ticket_id parameter."]);
        exit;
    }

    // Send Reply as Admin
    if ($action === 'reply') {
        $message = trim($input['message'] ?? '');
        $newStatus = trim($input['status'] ?? 'in_progress');

        if (empty($message)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Reply message cannot be empty."]);
            exit;
        }

        $mStmt = mysqli_prepare($conn, "INSERT INTO affiliate_ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'admin', ?, ?)");
        mysqli_stmt_bind_param($mStmt, "iis", $ticketId, $adminId, $message);

        if (mysqli_stmt_execute($mStmt)) {
            $msgId = mysqli_insert_id($conn);

            // Update ticket status
            if (in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed'])) {
                mysqli_query($conn, "UPDATE affiliate_support_tickets SET status = '$newStatus', updated_at = NOW() WHERE id = $ticketId");
            } else {
                mysqli_query($conn, "UPDATE affiliate_support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = $ticketId");
            }

            // Dispatch notification to affiliate
            $tQuery = mysqli_query($conn, "SELECT affiliate_id, subject FROM affiliate_support_tickets WHERE id = $ticketId LIMIT 1");
            if ($tRow = mysqli_fetch_assoc($tQuery)) {
                $affOwnerId = (int)$tRow['affiliate_id'];
                $shortSubject = htmlspecialchars($tRow['subject'] ?? 'Support Inquiry');
                $snippet = substr($message, 0, 100);
                sendAffiliateNotification(
                    $conn,
                    $affOwnerId,
                    'ticket',
                    'Support Ticket Reply',
                    "Admin replied to ticket #{$ticketId} ({$shortSubject}): \"{$snippet}\"",
                    '/support'
                );
            }

            echo json_encode([
                "status" => "success",
                "message" => "Reply successfully sent to partner.",
                "data" => [
                    "id" => $msgId,
                    "ticket_id" => $ticketId,
                    "sender_type" => "admin",
                    "message" => $message,
                    "status" => $newStatus,
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Update Status
    if ($action === 'status') {
        $status = strtolower(trim($input['status'] ?? ''));
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid status value."]);
            exit;
        }

        $uStmt = mysqli_prepare($conn, "UPDATE affiliate_support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($uStmt, "si", $status, $ticketId);

        if (mysqli_stmt_execute($uStmt)) {
            // Dispatch notification to affiliate
            $tQuery = mysqli_query($conn, "SELECT affiliate_id, subject FROM affiliate_support_tickets WHERE id = $ticketId LIMIT 1");
            if ($tRow = mysqli_fetch_assoc($tQuery)) {
                $affOwnerId = (int)$tRow['affiliate_id'];
                $shortSubject = htmlspecialchars($tRow['subject'] ?? 'Support Inquiry');
                $statusFormatted = ucfirst(str_replace('_', ' ', $status));
                sendAffiliateNotification(
                    $conn,
                    $affOwnerId,
                    'ticket',
                    'Support Ticket Status Updated',
                    "Ticket #{$ticketId} ({$shortSubject}) status was updated to: {$statusFormatted}",
                    '/support'
                );
            }

            echo json_encode([
                "status" => "success",
                "message" => "Ticket status updated to " . ucfirst(str_replace('_', ' ', $status)) . ".",
                "data" => [
                    "ticket_id" => $ticketId,
                    "status" => $status
                ]
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Update failed: " . mysqli_error($conn)]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Unknown POST action."]);
    exit;
}

// 2. GET Request: View specific ticket conversation or list all tickets
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if ($ticketId > 0) {
    // Ticket detail with affiliate info and conversation history
    $tSql = "SELECT t.*, a.full_name, a.company_name, a.email, a.phone, a.affiliate_code, a.tier, a.status AS affiliate_status
             FROM affiliate_support_tickets t
             JOIN affiliates a ON t.affiliate_id = a.id
             WHERE t.id = ?
             LIMIT 1";
    $stmt = mysqli_prepare($conn, $tSql);
    mysqli_stmt_bind_param($stmt, "i", $ticketId);
    mysqli_stmt_execute($stmt);
    $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Ticket not found."]);
        exit;
    }

    $mStmt = mysqli_prepare($conn, "SELECT * FROM affiliate_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    mysqli_stmt_bind_param($mStmt, "i", $ticketId);
    mysqli_stmt_execute($mStmt);
    $messages = mysqli_fetch_all(mysqli_stmt_get_result($mStmt), MYSQLI_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => [
            "ticket" => $ticket,
            "messages" => $messages
        ]
    ]);
    exit;
}

// List all affiliate tickets
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($statusFilter) && $statusFilter !== 'ALL') {
    $where .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($priorityFilter) && $priorityFilter !== 'ALL') {
    $where .= " AND t.priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if (!empty($search)) {
    $where .= " AND (t.subject LIKE ? OR a.full_name LIKE ? OR a.company_name LIKE ? OR a.affiliate_code LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)";
    $sw = "%$search%";
    $params[] = $sw; $params[] = $sw; $params[] = $sw; $params[] = $sw; $params[] = $sw; $params[] = $sw;
    $types .= "ssssss";
}

// Summary Metrics
$metricsSql = "SELECT 
    COUNT(*) AS total_tickets,
    COUNT(CASE WHEN status = 'open' THEN 1 END) AS open_tickets,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS in_progress_tickets,
    COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) AS resolved_tickets
FROM affiliate_support_tickets";
$mRes = mysqli_query($conn, $metricsSql);
$summary = mysqli_fetch_assoc($mRes) ?: [
    'total_tickets' => 0, 'open_tickets' => 0, 'in_progress_tickets' => 0, 'resolved_tickets' => 0
];

$sql = "SELECT t.id, t.affiliate_id, t.subject, t.category, t.priority, t.status, t.created_at, t.updated_at,
               a.full_name, a.company_name, a.phone, a.email, a.affiliate_code, a.tier,
               COUNT(m.id) AS total_messages,
               (SELECT message FROM affiliate_ticket_messages WHERE ticket_id = t.id ORDER BY id ASC LIMIT 1) AS initial_message
        FROM affiliate_support_tickets t
        JOIN affiliates a ON t.affiliate_id = a.id
        LEFT JOIN affiliate_ticket_messages m ON t.id = m.ticket_id
        $where
        GROUP BY t.id
        ORDER BY FIELD(t.status, 'open', 'in_progress', 'resolved', 'closed'), t.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
} else {
    $res = mysqli_query($conn, $sql);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

$tickets = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "affiliate_id" => (int)$r['affiliate_id'],
        "affiliate_name" => $r['full_name'] ?: ($r['company_name'] ?: $r['email']),
        "company_name" => $r['company_name'] ?? '',
        "phone" => $r['phone'] ?? '',
        "email" => $r['email'] ?? '',
        "affiliate_code" => $r['affiliate_code'],
        "tier" => ucfirst($r['tier'] ?? 'Bronze'),
        "subject" => $r['subject'],
        "category" => $r['category'],
        "priority" => ucfirst($r['priority'] ?? 'Normal'),
        "status" => strtolower($r['status'] ?? 'open'),
        "total_messages" => (int)$r['total_messages'],
        "initial_message" => $r['initial_message'] ?? '',
        "created_at" => $r['created_at'],
        "formatted_date" => date('M d, Y H:i', strtotime($r['created_at']))
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "summary" => [
        "total" => (int)$summary['total_tickets'],
        "open" => (int)$summary['open_tickets'],
        "in_progress" => (int)$summary['in_progress_tickets'],
        "resolved" => (int)$summary['resolved_tickets']
    ],
    "data" => $tickets
]);
