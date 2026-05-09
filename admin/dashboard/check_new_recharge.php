<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';
include '../../security/headers-security.php';
$sql = "SELECT * FROM tblusersrecharge ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);

$response = ['new_recharge' => false];

if ($row = mysqli_fetch_assoc($result)) {
    $response = [
        'new_recharge' => true,
        'id' => $row['id'],
        'amount' => $row['tbl_recharge_amount'],
        'time' => $row['tbl_time_stamp']
    ];
}

echo json_encode($response);
?>
