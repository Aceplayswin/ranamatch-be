<?php
/**
 * Affiliate Notifications API Endpoint
 * Endpoint: GET/POST /affiliate/notifications
 * Provides unread count, list of alerts, and read status management.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate session required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

// Handle POST actions (mark_read, mark_all_read, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = strtolower(trim($input['action'] ?? ''));

    if ($action === 'mark_read') {
        $notifId = (int)($input['notification_id'] ?? 0);
        if ($notifId > 0) {
            $uStmt = mysqli_prepare($conn, "UPDATE affiliate_notifications SET is_read = 1 WHERE id = ? AND affiliate_id = ?");
            mysqli_stmt_bind_param($uStmt, "ii", $notifId, $affiliateId);
            mysqli_stmt_execute($uStmt);
        }

        echo json_encode(["status" => "success", "message" => "Notification marked as read."]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $uStmt = mysqli_prepare($conn, "UPDATE affiliate_notifications SET is_read = 1 WHERE affiliate_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($uStmt, "i", $affiliateId);
        mysqli_stmt_execute($uStmt);

        echo json_encode(["status" => "success", "message" => "All notifications marked as read."]);
        exit;
    }

    if ($action === 'delete') {
        $notifId = (int)($input['notification_id'] ?? 0);
        if ($notifId > 0) {
            $dStmt = mysqli_prepare($conn, "DELETE FROM affiliate_notifications WHERE id = ? AND affiliate_id = ?");
            mysqli_stmt_bind_param($dStmt, "ii", $notifId, $affiliateId);
            mysqli_stmt_execute($dStmt);
        }

        echo json_encode(["status" => "success", "message" => "Notification deleted."]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid POST action."]);
    exit;
}

// GET: Fetch unread count & notifications list
$cStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS unread_count FROM affiliate_notifications WHERE affiliate_id = ? AND is_read = 0");
mysqli_stmt_bind_param($cStmt, "i", $affiliateId);
mysqli_stmt_execute($cStmt);
$cRes = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt));
$unreadCount = (int)($cRes['unread_count'] ?? 0);

$limit = min(50, max(1, (int)($_GET['limit'] ?? 25)));
$stmt = mysqli_prepare($conn, "SELECT id, type, title, message, link, is_read, created_at FROM affiliate_notifications WHERE affiliate_id = ? ORDER BY created_at DESC LIMIT ?");
mysqli_stmt_bind_param($stmt, "ii", $affiliateId, $limit);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($res)) {
    $notifications[] = [
        "id" => (int)$row['id'],
        "type" => $row['type'],
        "title" => $row['title'],
        "message" => $row['message'],
        "link" => $row['link'],
        "is_read" => (bool)$row['is_read'],
        "created_at" => $row['created_at']
    ];
}

echo json_encode([
    "status" => "success",
    "unread_count" => $unreadCount,
    "notifications" => $notifications
]);
