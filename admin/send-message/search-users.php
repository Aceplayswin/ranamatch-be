<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true" || $accessObj->isAllowed("access_message") == "false") {
    echo json_encode([]);
    exit;
}

$query = mysqli_real_escape_string($conn, $_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT tbl_uniq_id, tbl_user_name, tbl_full_name, tbl_mobile_num 
        FROM tblusersdata 
        WHERE tbl_uniq_id LIKE '%$query%' 
        OR tbl_user_name LIKE '%$query%' 
        OR tbl_full_name LIKE '%$query%' 
        OR tbl_mobile_num LIKE '%$query%'
        LIMIT 10";

$result = mysqli_query($conn, $sql);
$users = [];

while ($row = mysqli_fetch_assoc($result)) {
    $users[] = [
        'id' => $row['tbl_uniq_id'],
        'username' => $row['tbl_user_name'] ?? $row['tbl_full_name'],
        'fullname' => $row['tbl_full_name'],
        'mobile' => $row['tbl_mobile_num']
    ];
}

echo json_encode($users);
mysqli_close($conn);
?>
