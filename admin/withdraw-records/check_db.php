<?php
define("ACCESS_SECURITY", "true");
include 'd:\xampp\htdocs\security\config.php';
$user_id = '1111111';
$res = mysqli_query($conn, "SELECT tbl_uniq_id, tbl_balance FROM tblusersdata WHERE tbl_uniq_id = '$user_id'");
print_r(mysqli_fetch_assoc($res));

$res_w = mysqli_query($conn, "SELECT * FROM tbluserswithdraw WHERE tbl_user_id = '$user_id' ORDER BY id DESC LIMIT 5");
while($row = mysqli_fetch_assoc($res_w)) {
    echo "ID: ".$row['id']." | Status: ".$row['tbl_request_status']." | Req: ".$row['tbl_withdraw_request']." | Date: ".$row['tbl_time_stamp']."\n";
}
?>
