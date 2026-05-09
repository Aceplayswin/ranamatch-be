<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$res = mysqli_query($conn, "SELECT id, tbl_project_name, tbl_invested_on, tbl_match_status, tbl_match_details 
                            FROM tblmatchplayed 
                            ORDER BY id DESC LIMIT 20");

while($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " | Project: " . $row['tbl_project_name'] . " | Invested: " . $row['tbl_invested_on'] . " | Status: " . $row['tbl_match_status'] . " | Details: " . substr($row['tbl_match_details'], 0, 50) . "\n";
}
?>
