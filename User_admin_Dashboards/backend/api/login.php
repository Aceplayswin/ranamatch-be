<?php
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, "Method not allowed");
}

$data = json_decode(file_get_contents("php://input"), true);

$login = trim($data['login'] ?? ''); // can be username or email
$password = $data['password'] ?? '';

if (empty($login) || empty($password)) {
    respond(false, "All fields are required");
}

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$login, $login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    respond(false, "Invalid credentials");
}

if (!password_verify($password, $user['password'])) {
    respond(false, "Invalid credentials");
}

if ($user['status'] === 'blocked') {
    respond(false, "Your account has been blocked. Contact admin.");
}

$token = base64_encode($user['id']);

respond(true, "Login successful", [
    "token" => $token,
    "user" => [
        "id" => $user['id'],
        "full_name" => $user['full_name'],
        "username" => $user['username'],
        "email" => $user['email'],
        "role" => $user['role'],
        "wallet_balance" => $user['wallet_balance']
    ]
]);
?>
