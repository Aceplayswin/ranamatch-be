<?php
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, "Method not allowed");
}

$data = json_decode(file_get_contents("php://input"), true);

$full_name = trim($data['full_name'] ?? '');
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$mobile = trim($data['mobile'] ?? '');
$password = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// Validation
if (empty($full_name) || empty($username) || empty($email) || empty($mobile) || empty($password)) {
    respond(false, "All fields are required");
}

if ($password !== $confirm_password) {
    respond(false, "Passwords do not match");
}

if (strlen($password) < 6) {
    respond(false, "Password must be at least 6 characters");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Invalid email address");
}

// Check if username or email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    respond(false, "Username or email already exists");
}

// Hash password and insert
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (full_name, username, email, mobile, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
$stmt->execute([$full_name, $username, $email, $mobile, $hashed_password]);

$user_id = $conn->lastInsertId();
$token = base64_encode($user_id);

respond(true, "Registration successful", [
    "token" => $token,
    "user" => [
        "id" => $user_id,
        "full_name" => $full_name,
        "username" => $username,
        "email" => $email,
        "role" => "user",
        "wallet_balance" => "0.00"
    ]
]);
?>
