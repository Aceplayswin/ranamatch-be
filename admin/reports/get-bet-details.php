<?php
/**
 * BET DETAILS REPORT API
 * Fetches transaction details for Casino and Sports rounds
 */

// Disable all error output that could break JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header early
header('Content-Type: application/json');

// Catch ALL errors/exceptions to guarantee JSON output
set_exception_handler(function($e) {
    echo json_encode(['error' => 'Server Exception: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function($errno, $errstr) {
    // Suppress - don't let warnings break JSON
    return true;
});

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();

// 1. Validation
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    die(json_encode(['error' => 'Unauthorized session access.']));
}

if (!$conn) {
    die(json_encode(['error' => 'Critical: Database connection failed.']));
}

// 2. Input Sanitization
$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (empty($id)) {
    die(json_encode(['error' => 'Required parameter ID is missing.']));
}

// 3. Execution - Try simple queries one at a time to avoid unknown column crashes
try {
    // Disable mysqli exceptions so query returns false instead of throwing
    mysqli_report(MYSQLI_REPORT_OFF);
    
    $row = null;

    // Try tbl_uniq_id first (most common)
    $sql = "SELECT t1.*, t2.tbl_user_name 
            FROM tblmatchplayed t1
            LEFT JOIN tblusersdata t2 ON t1.tbl_user_id = t2.tbl_uniq_id
            WHERE t1.tbl_uniq_id = '$id'
            LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
    }

    // If not found, try tbl_period_id
    if (!$row) {
        $sql = "SELECT t1.*, t2.tbl_user_name 
                FROM tblmatchplayed t1
                LEFT JOIN tblusersdata t2 ON t1.tbl_user_id = t2.tbl_uniq_id
                WHERE t1.tbl_period_id = '$id'
                LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
        }
    }

    // 4. Response
    if ($row) {
        $row['tbl_match_cost'] = $row['tbl_match_cost'] ?? 0;
        $row['tbl_match_profit'] = $row['tbl_match_profit'] ?? 0;
        $row['tbl_user_name'] = $row['tbl_user_name'] ?? 'Unknown User';
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'No transaction record found for ID: ' . $id]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}

exit;
