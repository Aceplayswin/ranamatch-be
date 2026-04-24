<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()!="true"){
    header('location:../logout-account');
    exit;
}

$action = $_POST['action_type'] ?? '';

if ($action == "update_status") {
    $ticket_id = mysqli_real_escape_string($conn, $_POST['ticket_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE tbl_support_tickets SET status = '$status' WHERE ticket_id = '$ticket_id'";
    if(mysqli_query($conn, $query)) {
        header("Location: view.php?id=$ticket_id&msg=Status updated to " . str_replace('_', ' ', $status));
    } else {
        header("Location: view.php?id=$ticket_id&err=Failed to update status");
    }
}

else if ($action == "send_reply") {
    $ticket_id = mysqli_real_escape_string($conn, $_POST['ticket_id']);
    $user_email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $message = mysqli_real_escape_string($conn, $_POST['reply_message']);
    
    // 1. Insert Reply
    $ins = "INSERT INTO tbl_ticket_replies (ticket_id, sender_type, message) VALUES ('$ticket_id', 'admin', '$message')";
    
    if(mysqli_query($conn, $ins)) {
        // 2. Auto-update status to in_progress if currently open
        mysqli_query($conn, "UPDATE tbl_support_tickets SET status = 'in_progress' WHERE ticket_id = '$ticket_id' AND status = 'open'");
        
        // 3. Send Email Notification to User
        $to = $user_email;
        $subject = "Reply to your Support Ticket #$ticket_id";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Support Team <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";

        $email_body = "
        <html>
        <head>
            <style>
                .email-container { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px; }
                .header { background: #06b6d4; color: #fff; padding: 10px 20px; font-weight: bold; border-radius: 5px; }
                .message-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-left: 4px solid #06b6d4; margin: 20px 0; }
                .footer { font-size: 11px; color: #777; margin-top: 30px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>📩 YOU HAVE A NEW REPLY</div>
                <p>Hello,</p>
                <p>An administrator has replied to your support ticket <strong>#$ticket_id</strong>.</p>
                
                <div class='message-box'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                
                <p>You can check the status of your ticket from the support section of the website.</p>
                
                <div class='footer'>
                    &copy; " . date('Y') . " " . $APP_NAME . " Support Team. Please do not reply to this email.
                </div>
            </div>
        </body>
        </html>";

        @mail($to, $subject, $email_body, $headers);
        
        header("Location: view.php?id=$ticket_id&msg=Reply sent and user notified.");
    } else {
        header("Location: view.php?id=$ticket_id&err=Failed to save reply: " . mysqli_error($conn));
    }
}
else {
    header("Location: index.php");
}
?>
