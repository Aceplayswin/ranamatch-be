<?php
if (!defined("ACCESS_SECURITY")) {
    die("Direct access not allowed.");
}

$user_id = "";
$secret_key = "";

if (isset($_GET["USER_ID"])) {
    $user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
}

if($user_id != ""){
    $secret_key = $headerObj -> getAuthorization();
}

$resArr = array();
$resArr['data'] = array();
$resArr['status_code'] = "error";

if ($user_id != "" && $secret_key != "") {
    // Validate User
    $validate_sql = "SELECT id FROM tblusersdata WHERE tbl_uniq_id='{$user_id}' AND tbl_auth_secret ='{$secret_key}'";
    $validate_query = mysqli_query($conn, $validate_sql);

    if (mysqli_num_rows($validate_query) > 0) {
        // Fetch Tickets
        $tickets_sql = "SELECT * FROM tbl_support_tickets WHERE user_id = '{$user_id}' ORDER BY created_at DESC";
        $tickets_res = mysqli_query($conn, $tickets_sql);

        while ($ticket = mysqli_fetch_assoc($tickets_res)) {
            $ticket_id = $ticket['ticket_id'];
            
            // Fetch Replies for this ticket
            $replies_sql = "SELECT * FROM tbl_ticket_replies WHERE ticket_id = '{$ticket_id}' ORDER BY created_at ASC";
            $replies_res = mysqli_query($conn, $replies_sql);
            $replies = array();
            while ($reply = mysqli_fetch_assoc($replies_res)) {
                $replies[] = array(
                    "message" => $reply['message'],
                    "sender_type" => $reply['sender_type'],
                    "created_at" => $reply['created_at']
                );
            }

            // Fetch Attachments (if any)
            $attach_sql = "SELECT * FROM tbl_ticket_attachments WHERE ticket_id = '{$ticket_id}'";
            $attach_res = mysqli_query($conn, $attach_sql);
            $attachments = array();
            while ($attach = mysqli_fetch_assoc($attach_res)) {
                $attachments[] = array(
                    "file_name" => $attach['file_name'],
                    "file_path" => $attach['file_path']
                );
            }

            $resArr['data'][] = array(
                "ticket_id" => $ticket['ticket_id'],
                "subject" => $ticket['subject'],
                "message" => $ticket['message'],
                "status" => $ticket['status'],
                "priority" => $ticket['priority'],
                "created_at" => $ticket['created_at'],
                "replies" => $replies,
                "attachments" => $attachments
            );
        }
        $resArr['status_code'] = "success";
    } else {
        $resArr['status_code'] = "unauthorized";
    }
} else {
    $resArr['status_code'] = "missing_parameters";
}

echo json_encode($resArr);
?>
