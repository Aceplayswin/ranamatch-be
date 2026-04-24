<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    echo "Unauthorized";
    exit;
}

if ($accessObj->isAllowed("access_message") == "false") {
    echo "Access Denied";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $head = mysqli_real_escape_string($conn, $_POST['m_heading'] ?? '');
    $to = $_POST['m_to'] ?? '';
    $uid = mysqli_real_escape_string($conn, $_POST['target_id'] ?? '');
    $msg = mysqli_real_escape_string($conn, $_POST['m_content'] ?? '');
    
    if (empty($head) || empty($msg)) {
        echo "Missing required fields";
        exit;
    }

    $full_msg = $head . ',' . $msg;
    $timestamp = date("d-m-Y h:i a");

    if ($to === 'all') {
        // Global notice via tblservices (IMP_ALERT)
        // This will show to everyone on their next login/refresh
        $sql = "UPDATE tblservices SET tbl_service_value = '$full_msg' WHERE tbl_service_name = 'IMP_ALERT'";
        if (mysqli_query($conn, $sql)) {
            echo "success";
        } else {
            echo "DB Error (Global): " . mysqli_error($conn);
        }
    } else if ($to === 'specific' && !empty($uid)) {
        // Individual notice via tblallnotices
        // This will show ONLY to the specific user once, then status becomes 'false'
        $sql = "INSERT INTO tblallnotices (tbl_user_id, tbl_notice_title, tbl_notice_note, tbl_notice_status, tbl_time_stamp) 
                VALUES ('$uid', '$head', '$msg', 'true', '$timestamp')";
        if (mysqli_query($conn, $sql)) {
            echo "success";
        } else {
            echo "DB Error (Specific): " . mysqli_error($conn);
        }
    } else {
        echo "Invalid targeting configuration";
    }
    mysqli_close($conn);
} else {
    echo "Method Not Allowed";
}
?>