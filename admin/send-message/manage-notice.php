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
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $once = 1; // Always show once as requested
    
    if (empty($head) || empty($msg) || empty($start) || empty($end)) {
        echo "Missing required fields (Check times)";
        exit;
    }

    // Convert local datetime-local string to SQL format
    $start_sql = date('Y-m-d H:i:s', strtotime($start));
    $end_sql = date('Y-m-d H:i:s', strtotime($end));

    // Primary: Insert into new Broadcast table
    $sql = "INSERT INTO tbl_broadcasts (title, message, target_type, target_uid, start_time, end_time, show_once) 
            VALUES ('$head', '$msg', '$to', " . ($to === 'all' ? "NULL" : "'$uid'") . ", '$start_sql', '$end_sql', $once)";
    
    if (mysqli_query($conn, $sql)) {
        // Fallback: Also update the old systems for immediate compatibility with existing parts of the site
        if ($to === 'all') {
            $full_msg = $head . ',' . $msg;
            mysqli_query($conn, "UPDATE tblservices SET tbl_service_value = '$full_msg' WHERE tbl_service_name = 'IMP_ALERT'");
        } else {
            $timestamp = date("d-m-Y h:i a");
            mysqli_query($conn, "INSERT INTO tblallnotices (tbl_user_id, tbl_notice_title, tbl_notice_note, tbl_notice_status, tbl_time_stamp) 
                                 VALUES ('$uid', '$head', '$msg', 'true', '$timestamp')");
        }
        echo "success";
    } else {
        echo "DB Error: " . mysqli_error($conn);
    }
    mysqli_close($conn);
} else {
    echo "Method Not Allowed";
}
?>