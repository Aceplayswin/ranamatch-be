<?php
require_once __DIR__ . '/../config/database.php';

$user = getAuthUser($conn);

if ($user['role'] !== 'admin') {
    respond(false, "Access denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'all'; // deposit, withdrawal, all
    $status = $_GET['status'] ?? 'pending'; // pending, approved, rejected, all
    
    $sql = "SELECT t.*, u.full_name, u.username, u.email, u.mobile 
            FROM transactions t 
            JOIN users u ON t.user_id = u.id 
            WHERE 1=1";
    $params = [];
    
    if ($type !== 'all') {
        $sql .= " AND t.type = ?";
        $params[] = $type;
    }
    if ($status !== 'all') {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    respond(true, "Requests fetched", ["requests" => $requests]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $transaction_id = intval($data['transaction_id'] ?? 0);
    $action = $data['action'] ?? ''; // approve, reject
    
    if (!in_array($action, ['approve', 'reject'])) {
        respond(false, "Invalid action");
    }
    
    // Get the transaction
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        respond(false, "Transaction not found or already processed");
    }
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $conn->beginTransaction();
    
    try {
        // Update transaction status
        $stmt = $conn->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $transaction_id]);
        
        // If approved, update wallet balance
        if ($action === 'approve') {
            if ($transaction['type'] === 'deposit') {
                $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            }
            $stmt->execute([$transaction['amount'], $transaction['user_id']]);
        }
        
        $conn->commit();
        respond(true, "Transaction " . $new_status . " successfully");
    } catch (Exception $e) {
        $conn->rollBack();
        respond(false, "Error processing transaction: " . $e->getMessage());
    }
}
?>
