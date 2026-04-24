<?php
error_reporting(0);
header('Content-Type: application/json');

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    echo json_encode(['error' => 'Unauthorized session.']);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
$type = mysqli_real_escape_string($conn, $_GET['type'] ?? '');

if (empty($id)) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

// Optimized query based on vertical type
if ($type === 'casino') {
    $where = "t1.tbl_period_id = '$id'";
} elseif ($type === 'sports') {
    $where = "t1.tbl_uniq_id = '$id'";
} else {
    // Fallback broad search
    $where = "t1.tbl_uniq_id = '$id' OR t1.tbl_period_id = '$id' OR t1.tbl_bet_id = '$id'";
}

$sql = "SELECT t1.*, t2.tbl_user_name 
        FROM tblmatchplayed t1
        LEFT JOIN tblusersdata t2 ON t1.tbl_user_id = t2.tbl_uniq_id
        WHERE $where
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['error' => 'Query Error: ' . mysqli_error($conn)]);
    exit;
}

if ($row = mysqli_fetch_assoc($result)) {
    $row['tbl_match_cost'] = $row['tbl_match_cost'] ?? 0;
    $row['tbl_match_profit'] = $row['tbl_match_profit'] ?? 0;
    $row['tbl_user_name'] = $row['tbl_user_name'] ?? 'Unknown User';
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'No record found for ' . $id]);
}
