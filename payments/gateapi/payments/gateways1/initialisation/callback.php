<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define("USER_TOKEN", "8b6ffc733c97ed20c6ce2d00fb117675");
define("SALT_KEY", "2f44079550104c22ea108e9f9107f26b");

date_default_timezone_set('Asia/Kolkata');

function logMessage($file, $message) {
    file_put_contents($file, "[" . date("Y-m-d H:i:s") . "] $message" . PHP_EOL, FILE_APPEND);
}

function getConnection() {
    $conn = new mysqli("localhost", "root", "", "winco");
    if ($conn->connect_error) {
        logMessage("callback_error_log.txt", "DB Connection Error: " . $conn->connect_error);
        die("DB Error");
    }
    return $conn;
}

// Read Input
$rawData = file_get_contents("php://input");
logMessage("callback_raw_log.txt", "Raw: $rawData");

$input = json_decode($rawData, true);
logMessage("callback_log.txt", "Parsed Data: " . json_encode($input));

// Validate format
if (!isset($input['data']) || !is_array($input['data'])) {
    logMessage("callback_error_log.txt", "Invalid format or missing data object");
    http_response_code(400);
    echo "Invalid data format";
    exit;
}

// Extract nested fields
$data = $input['data'];
$status = strtolower($data['status'] ?? '');
$orderSn = $data['order_id'] ?? null;
$money = $data['amount'] ?? null;
$userId = $input['attach'] ?? null; // optional external attach field
$signature = $input['sign'] ?? $input['signature'] ?? null;

// Fallbacks
if (!$status || !$orderSn || !$money) {
    logMessage("callback_error_log.txt", "Missing Required Fields - Data: " . json_encode($input));
    http_response_code(400);
    echo "Missing required fields";
    exit;
}

// Connect DB
$conn = getConnection();

// Resolve user ID if not available
if ($userId === null) {
    $stmt = $conn->prepare("SELECT tbl_user_id FROM tblusersrecharge WHERE tbl_uniq_id = ?");
    $stmt->bind_param('s', $orderSn);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();
    if ($userId === null) {
        logMessage("callback_error_log.txt", "Order Not Found: $orderSn");
        http_response_code(404);
        echo "Order not found";
        exit;
    }
}

// Check for duplicate
$stmt = $conn->prepare("SELECT tbl_request_status FROM tblusersrecharge WHERE tbl_uniq_id = ?");
$stmt->bind_param('s', $orderSn);
$stmt->execute();
$stmt->bind_result($orderStatus);
$stmt->fetch();
$stmt->close();

if ($orderStatus === 'success') {
    logMessage("callback_log.txt", "Duplicate Callback Ignored: $orderSn");
    echo "success";
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT tbl_balance FROM tblusersdata WHERE tbl_uniq_id = ?");
$stmt->bind_param('s', $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    logMessage("callback_error_log.txt", "User Not Found: $userId");
    http_response_code(404);
    echo "User not found";
    exit;
}
$stmt->close();

// Success case
if ($status === 'completed' || $status === 'success') {
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE tbl_uniq_id = ?");
        $stmt1->bind_param("ds", $money, $userId);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("UPDATE tblusersrecharge SET tbl_request_status = 'success', tbl_time_stamp = ? WHERE tbl_uniq_id = ?");
        $now = date('d-m-Y h:i:s a');
        $stmt2->bind_param("ss", $now, $orderSn);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        logMessage("callback_success_log.txt", "Success: $orderSn | $money | $userId");
    } catch (Exception $e) {
        $conn->rollback();
        logMessage("callback_error_log.txt", "DB Transaction Error: " . $e->getMessage());
        http_response_code(500);
        echo "DB Error";
        exit;
    }
} else {
    // Failed case
    $stmt = $conn->prepare("UPDATE tblusersrecharge SET tbl_request_status = 'failed', tbl_time_stamp = ? WHERE tbl_uniq_id = ?");
    $now = date('d-m-Y h:i:s a');
    $stmt->bind_param("ss", $now, $orderSn);
    $stmt->execute();
    $stmt->close();
    logMessage("callback_error_log.txt", "Failed Payment: $orderSn - Status: $status");
}

$conn->close();
echo "success";
