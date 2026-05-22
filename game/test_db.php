<?php
define("ACCESS_SECURITY", "true");
include "../security/config.php";

$res = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($res)) {
    $tables[] = $row[0];
}
echo "TABLES:\n" . json_encode($tables, JSON_PRETTY_PRINT) . "\n";
?>
