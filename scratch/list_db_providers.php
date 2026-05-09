<?php
define("ACCESS_SECURITY", true);
include 'security/config.php';
$sql = "SELECT DISTINCT game_provider FROM tbl_games WHERE game_status = 1";
$result = mysqli_query($conn, $sql);
$providers = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $providers[] = $row['game_provider'];
    }
}
echo json_encode($providers);
?>
