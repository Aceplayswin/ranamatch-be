<?php
/**
 * Agent Application Status Check Endpoint
 * Endpoint: GET /agent/auth/apply_status?email=xxx
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

header('Content-Type: application/json; charset=utf-8');

$email = trim($_GET['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email address is required."]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, status, applied_at, processed_at, rejection_reason, info_request_notes FROM agent_applications WHERE email = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$app = mysqli_fetch_assoc($res);

if (!$app) {
    echo json_encode([
        "status" => "success",
        "found" => false,
        "message" => "No application record found for this email."
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "found" => true,
    "data" => [
        "id" => (int)$app['id'],
        "full_name" => $app['full_name'],
        "email" => $app['email'],
        "application_status" => $app['status'],
        "applied_at" => $app['applied_at'],
        "processed_at" => $app['processed_at'],
        "rejection_reason" => $app['rejection_reason'],
        "info_request_notes" => $app['info_request_notes']
    ]
]);
