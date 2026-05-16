<?php
require_once __DIR__ . '/../config/database.php';

// Get current user and verify admin role
$admin = getAuthUser($conn);

if (!$admin || $admin['role'] !== 'admin') {
    respond(false, "Unauthorized: Admin access required");
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$amount = $input['amount'] ?? null;
$type = $input['type'] ?? 'deposit'; // 'deposit' or 'withdrawal'

if (!$user_id || !$amount || !is_numeric($amount) || $amount <= 0) {
    respond(false, "Invalid input data");
}

if (!in_array($type, ['deposit', 'withdrawal'])) {
    respond(false, "Invalid transaction type");
}

try {
    $conn->beginTransaction();

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        throw new Exception("Target user not found");
    }

    $amount = (float)$amount;
    $new_balance = (float)$targetUser['wallet_balance'];

    if ($type === 'deposit') {
        $new_balance += $amount;
    } else {
        if ($new_balance < $amount) {
            throw new Exception("Insufficient balance for withdrawal adjustment");
        }
        $new_balance -= $amount;
    }

    // Update User Balance
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);

    // Log the transaction (Manually approved)
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, remarks) VALUES (?, ?, ?, 'approved', ?)");
    $remarks = "Admin Manual Adjustment: " . ($type === 'deposit' ? 'Credit' : 'Debit');
    $stmt->execute([$user_id, $type, $amount, $remarks]);

    $conn->commit();
    respond(true, "Balance adjusted successfully", ["new_balance" => $new_balance]);

} catch (Exception $e) {
    $conn->rollBack();
    respond(false, "Error: " . $e->getMessage());
}
?>
