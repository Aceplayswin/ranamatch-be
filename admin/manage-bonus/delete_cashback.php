<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "DELETE FROM tbl_cashback_bonuses WHERE id = $id");
    echo json_encode(['status' => 'success']);
}
?>
