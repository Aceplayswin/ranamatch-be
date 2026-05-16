<?php
require_once __DIR__ . '/../config/database.php';

$user = getAuthUser($conn);

if ($user['role'] !== 'admin') {
    respond(false, "Access denied");
}

// Get all users (excluding admins)
$stmt = $conn->prepare("SELECT id, full_name, username, email, mobile, role, wallet_balance, status, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

respond(true, "Users fetched", ["users" => $users]);
?>
