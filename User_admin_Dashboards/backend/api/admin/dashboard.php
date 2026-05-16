<?php
require_once __DIR__ . '/../config/database.php';

$user = getAuthUser($conn);

if ($user['role'] !== 'admin') {
    respond(false, "Access denied");
}

// Dashboard stats
$stats = [];

// Total users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND status = 'active'");
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending deposits
$stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'deposit' AND status = 'pending'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending_deposits'] = $row['count'];
$stats['pending_deposit_amount'] = $row['total'];

// Pending withdrawals
$stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'withdrawal' AND status = 'pending'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending_withdrawals'] = $row['count'];
$stats['pending_withdrawal_amount'] = $row['total'];

// Total approved deposits
$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'deposit' AND status = 'approved'");
$stats['total_deposits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total approved withdrawals
$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'withdrawal' AND status = 'approved'");
$stats['total_withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent transactions
$stmt = $conn->query("SELECT t.*, u.full_name, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");
$stats['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

respond(true, "Dashboard stats fetched", ["stats" => $stats]);
?>
