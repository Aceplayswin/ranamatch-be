<?php
define("ACCESS_SECURITY","true");
include '../../../security/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    mysqli_begin_transaction($conn);
    try {
        // Delete from all related tables
        mysqli_query($conn, "DELETE FROM tbl_bonus_providers WHERE bonus_id = $id");
        mysqli_query($conn, "DELETE FROM tbl_bonus_content WHERE bonus_id = $id");
        mysqli_query($conn, "DELETE FROM tbl_bonus_abuse WHERE bonus_id = $id");
        mysqli_query($conn, "DELETE FROM tbl_bonuses WHERE id = $id");
        
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Bonus deleted successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete bonus: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
