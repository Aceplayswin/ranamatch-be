<?php
/**
 * Affiliate Support Tickets API Endpoint
 * Endpoint: GET|POST /affiliate/support
 * Handles viewing tickets, submitting inquiries, and messaging between affiliate and admin.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// 1. POST Request: Create Ticket or Send Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = $input['action'] ?? 'create';

    // Reply to existing ticket
    if ($action === 'reply') {
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = trim($input['message'] ?? '');

        if (!$ticketId || empty($message)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Ticket ID and message are required."]);
            exit;
        }

        // Verify ticket ownership
        $tChk = mysqli_prepare($conn, "SELECT id, status FROM affiliate_support_tickets WHERE id = ? AND affiliate_id = ?");
        mysqli_stmt_bind_param($tChk, "ii", $ticketId, $affiliateId);
        mysqli_stmt_execute($tChk);
        $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($tChk));

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Support ticket not found."]);
            exit;
        }

        // Insert message
        $mStmt = mysqli_prepare($conn, "INSERT INTO affiliate_ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'affiliate', ?, ?)");
        mysqli_stmt_bind_param($mStmt, "iis", $ticketId, $affiliateId, $message);

        if (mysqli_stmt_execute($mStmt)) {
            $msgId = mysqli_insert_id($conn);
            // Re-open ticket if closed or resolved
            mysqli_query($conn, "UPDATE affiliate_support_tickets SET status = 'open', updated_at = NOW() WHERE id = $ticketId");

            echo json_encode([
                "status" => "success",
                "message" => "Reply sent successfully.",
                "data" => [
                    "id" => $msgId,
                    "ticket_id" => $ticketId,
                    "sender_type" => "affiliate",
                    "sender_id" => $affiliateId,
                    "message" => $message,
                    "status" => "open",
                    "ticket_status" => "open",
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to save reply: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Default: Create new ticket
    $subject = trim($input['subject'] ?? '');
    $category = trim($input['category'] ?? 'Commercial');
    $priority = trim($input['priority'] ?? 'Normal');
    $message = trim($input['message'] ?? '');

    if (empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Subject and message are required."]);
        exit;
    }

    // Insert ticket record
    $tStmt = mysqli_prepare($conn, "INSERT INTO affiliate_support_tickets (affiliate_id, subject, category, priority, status) VALUES (?, ?, ?, ?, 'open')");
    mysqli_stmt_bind_param($tStmt, "isss", $affiliateId, $subject, $category, $priority);

    if (mysqli_stmt_execute($tStmt)) {
        $ticketId = mysqli_insert_id($conn);

        // Insert first message
        $mStmt = mysqli_prepare($conn, "INSERT INTO affiliate_ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'affiliate', ?, ?)");
        mysqli_stmt_bind_param($mStmt, "iis", $ticketId, $affiliateId, $message);
        mysqli_stmt_execute($mStmt);

        echo json_encode([
            "status" => "success",
            "message" => "Support ticket submitted successfully.",
            "data" => [
                "id" => $ticketId,
                "affiliate_id" => $affiliateId,
                "subject" => $subject,
                "category" => $category,
                "priority" => $priority,
                "status" => "open",
                "date" => "Just now",
                "created_at" => date('Y-m-d H:i:s'),
                "replies" => 0,
                "initial_message" => $message
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create support ticket: " . mysqli_error($conn)]);
        exit;
    }
}

// 2. GET Request: List Tickets or Single Ticket Detail
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if ($ticketId > 0) {
    // Single ticket detail with conversation messages
    $tStmt = mysqli_prepare($conn, "SELECT * FROM affiliate_support_tickets WHERE id = ? AND affiliate_id = ?");
    mysqli_stmt_bind_param($tStmt, "ii", $ticketId, $affiliateId);
    mysqli_stmt_execute($tStmt);
    $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($tStmt));

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Support ticket not found."]);
        exit;
    }

    $mStmt = mysqli_prepare($conn, "SELECT id, ticket_id, sender_type, sender_id, message, attachment_path, created_at FROM affiliate_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
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

// List all tickets for this affiliate
$listSql = "SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at, t.updated_at,
                   COUNT(m.id) AS total_messages,
                   MAX(m.created_at) AS last_message_at,
                   (SELECT message FROM affiliate_ticket_messages WHERE ticket_id = t.id ORDER BY id ASC LIMIT 1) AS initial_message
            FROM affiliate_support_tickets t
            LEFT JOIN affiliate_ticket_messages m ON t.id = m.ticket_id
            WHERE t.affiliate_id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC";

$lStmt = mysqli_prepare($conn, $listSql);
mysqli_stmt_bind_param($lStmt, "i", $affiliateId);
mysqli_stmt_execute($lStmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($lStmt), MYSQLI_ASSOC);

$tickets = array_map(function($r) {
    $created = strtotime($r['created_at']);
    $diff = time() - $created;
    if ($diff < 60) $dateStr = 'Just now';
    elseif ($diff < 3600) $dateStr = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $dateStr = floor($diff / 3600) . 'h ago';
    else $dateStr = date('M d, Y', $created);

    return [
        "id" => (int)$r['id'],
        "subject" => $r['subject'],
        "category" => $r['category'],
        "priority" => ucfirst($r['priority'] ?? 'Normal'),
        "status" => strtolower($r['status'] ?? 'open'),
        "date" => $dateStr,
        "created_at" => $r['created_at'],
        "replies" => max(0, (int)$r['total_messages'] - 1),
        "initial_message" => $r['initial_message'] ?? ''
    ];
}, $rows);

echo json_encode([
    "status" => "success",
    "data" => [
        "tickets" => $tickets,
        "total" => count($tickets),
        "open_count" => count(array_filter($tickets, function($t) { return $t['status'] === 'open' || $t['status'] === 'in_progress'; }))
    ]
]);
