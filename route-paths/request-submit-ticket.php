<?php
// Suppress all accidental output or warnings to ensure clean JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

define("ACCESS_SECURITY","true");
include_once '../../security/config.php';

header('Content-Type: application/json');

$resArr = ["status_code" => "error"];

// Using $_POST instead of JSON input to handle multipart/form-data (files)
$name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
$subject = mysqli_real_escape_string($conn, $_POST['subject'] ?? '');
$message = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
$user_id = mysqli_real_escape_string($conn, $_POST['USER_ID'] ?? 'guest');
$priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'Medium');
$profile_id = mysqli_real_escape_string($conn, $_POST['profile_id'] ?? '');

if(empty($name) || empty($email) || empty($subject) || empty($message)) {
    $resArr['msg'] = 'All fields are required';
    echo json_encode($resArr);
    exit;
}

// Create/Update tickets table
$create_table = "CREATE TABLE IF NOT EXISTS tbl_support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    profile_id VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    status ENUM('open','in_progress','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table);

// Check if columns exist (for migration)
$check_priority = mysqli_query($conn, "SHOW COLUMNS FROM tbl_support_tickets LIKE 'priority'");
if(mysqli_num_rows($check_priority) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_support_tickets ADD COLUMN priority VARCHAR(20) DEFAULT 'Medium' AFTER message");
}
$check_profile = mysqli_query($conn, "SHOW COLUMNS FROM tbl_support_tickets LIKE 'profile_id'");
if(mysqli_num_rows($check_profile) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_support_tickets ADD COLUMN profile_id VARCHAR(100) AFTER user_id");
}

// Create attachments table
$create_attach_table = "CREATE TABLE IF NOT EXISTS tbl_ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_attach_table);

$ticket_id = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));

$sql = "INSERT INTO tbl_support_tickets (ticket_id, user_id, profile_id, name, email, subject, message, priority) 
        VALUES ('$ticket_id', '$user_id', '$profile_id', '$name', '$email', '$subject', '$message', '$priority')";

if(mysqli_query($conn, $sql)) {
    // Handle File Uploads
    if(isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../storage/tickets/' . $ticket_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach($_FILES['attachments']['name'] as $key => $val) {
            $file_name = $_FILES['attachments']['name'][$key];
            $tmp_name = $_FILES['attachments']['tmp_name'][$key];
            $file_type = $_FILES['attachments']['type'][$key];
            
            // Clean file name
            $clean_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $file_name);
            $target_path = $upload_dir . $clean_name;

            if(move_uploaded_file($tmp_name, $target_path)) {
                $db_path = 'storage/tickets/' . $ticket_id . '/' . $clean_name;
                $esc_name = mysqli_real_escape_string($conn, $file_name);
                $esc_path = mysqli_real_escape_string($conn, $db_path);
                mysqli_query($conn, "INSERT INTO tbl_ticket_attachments (ticket_id, file_path, file_name) VALUES ('$ticket_id', '$esc_path', '$esc_name')");
            }
        }
    }

    // Create/Update replies table
    $create_replies_table = "CREATE TABLE IF NOT EXISTS tbl_ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id VARCHAR(50) NOT NULL,
        sender_type ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $create_replies_table);

    // Send Email Notification to Developer
    $to = "developer.velnix@gmail.com";
    $email_subject = "New Support Ticket: $subject [$ticket_id]";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Support Ticket System <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";

    $email_body = "
    <html>
    <head>
        <title>New Support Ticket Received</title>
        <style>
            .email-container { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px; }
            .header { background: #E6A000; color: #000; padding: 10px 20px; font-weight: bold; border-radius: 5px; }
            .details { background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .label { font-weight: bold; color: #E6A000; display: inline-block; width: 100px; }
            .message-box { background: #fff; border: 1px solid #eee; padding: 15px; border-left: 4px solid #E6A000; font-style: italic; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>🎫 NEW SUPPORT TICKET RECEIVED</div>
            <div class='details'>
                <p><span class='label'>Ticket ID:</span> $ticket_id</p>
                <p><span class='label'>Name:</span> $name</p>
                <p><span class='label'>Email:</span> $email</p>
                <p><span class='label'>User ID:</span> $user_id</p>
                <p><span class='label'>Priority:</span> $priority</p>
                <p><span class='label'>Subject:</span> $subject</p>
            </div>
            <p><strong>Message:</strong></p>
            <div class='message-box'>$message</div>
            <p style='font-size: 12px; color: #777; margin-top: 30px;'>
                This is an automated notification from your Support Ticket System.
            </p>
        </div>
    </body>
    </html>";

    $mail_sent = @mail($to, $email_subject, $email_body, $headers);

    $resArr['status_code'] = 'success';
    $resArr['msg'] = 'Ticket submitted successfully';
    if(!$mail_sent) {
        $resArr['msg'] .= ' (Note: Email notification failed to send.)';
        $resArr['mail_error'] = true;
    }
    $resArr['ticket_id'] = $ticket_id;
} else {
    $resArr['msg'] = 'Failed to submit ticket: ' . mysqli_error($conn);
}

mysqli_close($conn);

// Clear any accidental output from the buffer before sending the clean JSON
if (ob_get_length()) ob_end_clean();

echo json_encode($resArr);
