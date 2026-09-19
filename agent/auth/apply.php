<?php
/**
 * Agent Application Submission Endpoint
 * Endpoint: POST /agent/auth/apply or /api/v1/agent/apply
 * Allows applicants to submit agent network applications.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? $_POST;

$fullName = trim($input['fullName'] ?? $input['full_name'] ?? $input['name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$company = trim($input['company'] ?? '');
$phone = trim($input['phone'] ?? '');
$marketRegion = trim($input['marketRegion'] ?? $input['country'] ?? $input['city_country'] ?? $input['market_region'] ?? '');
$volumeBracket = trim($input['monthlyVolume'] ?? $input['volume_bracket'] ?? $input['expected_monthly_turnover'] ?? '');
$expectedPlayers = trim($input['expectedPlayers'] ?? $input['expected_players'] ?? '');
$experience = trim($input['experience'] ?? '');
$applicationNotes = trim($input['notes'] ?? $input['application_notes'] ?? '');
$uplineText = trim($input['uplineCode'] ?? $input['claimed_upline_text'] ?? '');
$passwordHash = !empty($input['password']) ? password_hash($input['password'], PASSWORD_BCRYPT) : null;

if (empty($fullName) || empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Full Name and Email Address are required."]);
    exit;
}

if (empty($username)) {
    $username = strtolower(explode(' ', $fullName)[0]) . '_' . rand(100, 999);
}

// Check existing application
$chk = mysqli_prepare($conn, "SELECT id, status FROM agent_applications WHERE email = ? AND status = 'pending' LIMIT 1");
mysqli_stmt_bind_param($chk, "s", $email);
mysqli_stmt_execute($chk);
$res = mysqli_stmt_get_result($chk);
if ($existing = mysqli_fetch_assoc($res)) {
    echo json_encode([
        "status" => "success",
        "message" => "Application already submitted and pending review.",
        "application_id" => (int)$existing['id']
    ]);
    exit;
}

$sql = "INSERT INTO agent_applications (full_name, username, email, company, phone, password_hash, market_region, volume_bracket, expected_players, experience, application_notes, claimed_upline_text, status, applied_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssssssssss", $fullName, $username, $email, $company, $phone, $passwordHash, $marketRegion, $volumeBracket, $expectedPlayers, $experience, $applicationNotes, $uplineText);

if (mysqli_stmt_execute($stmt)) {
    $appId = mysqli_insert_id($conn);
    echo json_encode([
        "status" => "success",
        "message" => "Agent application submitted successfully.",
        "data" => [
            "application_id" => $appId,
            "email" => $email,
            "status" => "pending"
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to submit application: " . mysqli_error($conn)]);
}
