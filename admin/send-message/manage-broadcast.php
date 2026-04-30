<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true" || $accessObj->isAllowed("access_message") == "false") {
    echo "Unauthorized";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $sql = "DELETE FROM tbl_broadcasts WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            // Also clean up views
            mysqli_query($conn, "DELETE FROM tbl_broadcast_views WHERE broadcast_id = $id");
            echo "success";
        } else {
            echo mysqli_error($conn);
        }
    } else if ($action === 'edit') {
        $head = mysqli_real_escape_string($conn, $_POST['m_heading'] ?? '');
        $msg = mysqli_real_escape_string($conn, $_POST['m_content'] ?? '');
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $to = $_POST['m_to'] ?? 'all';
        $uid = mysqli_real_escape_string($conn, $_POST['target_id'] ?? '');

        $start_sql = date('Y-m-d H:i:s', strtotime($start));
        $end_sql = date('Y-m-d H:i:s', strtotime($end));

        $sql = "UPDATE tbl_broadcasts SET 
                title = '$head', 
                message = '$msg', 
                start_time = '$start_sql', 
                end_time = '$end_sql', 
                target_type = '$to', 
                target_uid = " . ($to === 'all' ? "NULL" : "'$uid'") . "
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            echo "success";
        } else {
            echo mysqli_error($conn);
        }
    }
}
?>
