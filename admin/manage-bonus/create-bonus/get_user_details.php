<?php
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Waiting for ID...']);
    exit;
}

$user_id = mysqli_real_escape_string($conn, $_GET['user_id']);
$user_sql = "SELECT tbl_full_name, tbl_mobile_num FROM tblusersdata WHERE tbl_uniq_id = '$user_id'";
$user_res = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_res) > 0) {
    $user_data = mysqli_fetch_assoc($user_res);
    echo json_encode([
        'status' => 'success',
        'name' => $user_data['tbl_full_name'] ?: 'No Name Set',
        'mobile' => $user_data['tbl_mobile_num']
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User Not Found']);
}
?>
