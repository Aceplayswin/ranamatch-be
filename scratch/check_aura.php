<?php
define("ACCESS_SECURITY", true);
include 'security/config.php';
$sql = "SELECT game_name, game_provider FROM tbl_games WHERE game_provider LIKE '%Aura%' OR game_name LIKE '%Aura%'";
$result = mysqli_query($conn, $sql);
$results = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }
}
echo json_encode($results);
?>
