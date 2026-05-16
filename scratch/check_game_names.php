<?php
define("ACCESS_SECURITY", "true");
include "security/config.php";

$query = "SELECT * FROM tbl_game_names WHERE tbl_game_name LIKE '%Sports%' LIMIT 10";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
}
?>
