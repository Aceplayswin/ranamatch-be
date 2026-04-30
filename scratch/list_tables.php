<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

echo "TABLES IN DATABASE:\n";
$res = mysqli_query($conn, "SHOW TABLES");
while($row = mysqli_fetch_array($res)) {
    echo "- " . $row[0] . "\n";
}
?>
