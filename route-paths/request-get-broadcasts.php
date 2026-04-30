<?php
header("Content-Type: application/json");
define("ACCESS_SECURITY", "true");
include '../security/config.php';
include '../security/constants.php';

session_start();
$resArr = ["status_code" => "error", "data" => []];

if (isset($_SESSION["tbl_user_id"])) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION["tbl_user_id"]);
    $now = date('Y-m-d H:i:s');

    // Fetch active broadcasts:
    // 1. Time must be between start and end
    // 2. Must be for 'all' or specifically for this user
    // 3. If 'show_once' is enabled, user must not have seen it before
    $sql = "
        SELECT b.* 
        FROM tbl_broadcasts b
        WHERE b.start_time <= '$now' AND b.end_time >= '$now'
        AND (b.target_type = 'all' OR b.target_uid = '$user_id')
        AND (
            b.show_once = 0 
            OR NOT EXISTS (
                SELECT 1 FROM tbl_broadcast_views v 
                WHERE v.broadcast_id = b.id AND v.user_id = '$user_id'
            )
        )
        ORDER BY b.created_at DESC
    ";

    $result = mysqli_query($conn, $sql);
    if ($result) {
        $broadcasts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $broadcasts[] = [
                "id" => $row['id'],
                "title" => $row['title'],
                "message" => $row['message'],
                "show_once" => (int)$row['show_once']
            ];
        }
        $resArr["status_code"] = "success";
        $resArr["data"] = $broadcasts;
    } else {
        $resArr["message"] = mysqli_error($conn);
    }
} else {
    $resArr["status_code"] = "unauthorized";
}

mysqli_close($conn);
echo json_encode($resArr);
?>
