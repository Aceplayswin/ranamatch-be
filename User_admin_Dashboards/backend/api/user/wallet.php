<?php
require_once __DIR__ . '/../config/database.php';

$user = getAuthUser($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get wallet info and recent transactions
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get totals
    $stmt = $conn->prepare("SELECT 
        COALESCE(SUM(CASE WHEN type='deposit' AND status='approved' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type='withdrawal' AND status='approved' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type='deposit' AND status='pending' THEN amount ELSE 0 END), 0) as pending_deposits,
        COALESCE(SUM(CASE WHEN type='withdrawal' AND status='pending' THEN amount ELSE 0 END), 0) as pending_withdrawals
    FROM transactions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    respond(true, "Wallet data fetched", [
        "balance" => $user['wallet_balance'],
        "transactions" => $transactions,
        "totals" => $totals
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    
    if (!in_array($type, ['deposit', 'withdrawal'])) {
        respond(false, "Invalid transaction type");
    }
    
    if ($amount <= 0) {
        respond(false, "Amount must be greater than 0");
    }
    
    if ($type === 'withdrawal' && $amount > $user['wallet_balance']) {
        respond(false, "Insufficient balance");
    }
    
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$user['id'], $type, $amount]);
    
    respond(true, ucfirst($type) . " request submitted successfully");
}
?>
