<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true" || $accessObj->isAllowed("access_message") == "false") {
    echo json_encode([]);
    exit;
}

$q = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$target = mysqli_real_escape_string($conn, $_GET['target'] ?? '');
$status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

$sql = "SELECT * FROM tbl_broadcasts WHERE 1=1";

if (!empty($q)) {
    $sql .= " AND (title LIKE '%$q%' OR message LIKE '%$q%' OR target_uid LIKE '%$q%')";
}
if (!empty($target) && $target !== 'any') {
    $sql .= " AND target_type = '$target'";
}

$now = date('Y-m-d H:i:s');
if ($status === 'active') {
    $sql .= " AND start_time <= '$now' AND end_time >= '$now'";
} else if ($status === 'expired') {
    $sql .= " AND end_time < '$now'";
} else if ($status === 'upcoming') {
    $sql .= " AND start_time > '$now'";
}

$sql .= " ORDER BY created_at DESC LIMIT 50";

$result = mysqli_query($conn, $sql);
$history = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
}

echo json_encode($history);
?>
