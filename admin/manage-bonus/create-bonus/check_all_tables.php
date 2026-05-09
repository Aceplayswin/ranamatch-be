<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

$tables = ['tbl_bonus_content', 'tbl_bonus_abuse', 'tbl_bonus_providers'];

foreach ($tables as $table) {
    echo "\n--- $table ---\n";
    $res = mysqli_query($conn, "DESCRIBE $table");
    if ($res) {
        while($row = mysqli_fetch_assoc($res)) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "Table $table does not exist or error: " . mysqli_error($conn) . "\n";
    }
}
?>
