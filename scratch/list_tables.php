<?php
define("ACCESS_SECURITY", true);
require_once __DIR__ . '/../security/config.php';

echo "=== TABLES IN DATABASE ===\n";
$res = mysqli_query($conn, "SHOW TABLES");
while ($r = mysqli_fetch_row($res)) {
    if (strpos($r[0], 'affiliate') !== false || strpos($r[0], 'agent') !== false || strpos($r[0], 'user') !== false) {
        echo "- " . $r[0] . "\n";
    }
}
