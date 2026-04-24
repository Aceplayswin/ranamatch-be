<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';

$sql = "EXPLAIN SELECT * FROM tblmatchplayed WHERE tbl_uniq_id = 'test' OR tbl_period_id = 'test' OR tbl_bet_id = 'test'";
$result = mysqli_query($conn, $sql);
echo "<table border='1'><tr>";
while($field = mysqli_fetch_field($result)) echo "<th>{$field->name}</th>";
echo "</tr>";
while($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach($row as $v) echo "<td>$v</td>";
    echo "</tr>";
}
echo "</table>";
?>
