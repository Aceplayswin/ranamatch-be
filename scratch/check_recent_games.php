<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$res = mysqli_query($conn, "SELECT id, tbl_project_name, tbl_invested_on, tbl_match_status, tbl_notified, tbl_notify_at, tbl_time_stamp, tbl_result_time 
                            FROM tblmatchplayed 
                            ORDER BY id DESC LIMIT 10");

echo "Recent Games:\n";
echo str_pad("ID", 8) . " | " . str_pad("Project Name", 30) . " | " . str_pad("Invested On", 20) . " | " . str_pad("Status", 10) . " | " . "Notified/At\n";
echo str_repeat("-", 100) . "\n";

while($row = mysqli_fetch_assoc($res)) {
    echo str_pad($row['id'], 8) . " | " . 
         str_pad(substr($row['tbl_project_name'], 0, 30), 30) . " | " . 
         str_pad(substr($row['tbl_invested_on'], 0, 20), 20) . " | " . 
         str_pad($row['tbl_match_status'], 10) . " | " . 
         $row['tbl_notified'] . " / " . $row['tbl_notify_at'] . "\n";
}
?>
