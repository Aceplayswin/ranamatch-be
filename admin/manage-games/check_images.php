<?php
define("ACCESS_SECURITY", true);
require_once('../../security/config.php');

$sql = "SELECT game_name, game_image FROM tbl_games LIMIT 10";
$result = mysqli_query($conn, $sql);

echo "<table border='1'>";
echo "<tr><th>Name</th><th>Image URL</th><th>Preview</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['game_name'] . "</td>";
    echo "<td>" . $row['game_image'] . "</td>";
    echo "<td><img src='" . $row['game_image'] . "' width='50'></td>";
    echo "</tr>";
}
echo "</table>";
?>
