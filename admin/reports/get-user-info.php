<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if ($id != "") {
    $sql = "SELECT tbl_user_name, tbl_uniq_id, tbl_mobile_num FROM tblusersdata 
            WHERE tbl_uniq_id = '$id' 
            OR tbl_mobile_num = '$id' 
            OR tbl_user_name LIKE '%$id%' 
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($row = mysqli_fetch_assoc($res)) {
        echo json_encode([
            'status' => 'success',
            'username' => $row['tbl_user_name'] ?: 'N/A',
            'userid' => $row['tbl_uniq_id'],
            'mobile' => $row['tbl_mobile_num']
        ]);
        exit;
    }
}

echo json_encode(['status' => 'not_found']);
?>
