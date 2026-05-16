<?php
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../../security/config.php';

$res = mysqli_query($conn, "DESCRIBE tblmatchplayed");
$out = "";
while ($row = mysqli_fetch_assoc($res)) {
    $out .= $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . " | Default: " . $row['Default'] . "\n";
}
file_put_contents(__DIR__ . '/schema_dump.txt', $out);

$res2 = mysqli_query($conn, "DESCRIBE tblotherstransactions");
$out2 = "";
while ($row = mysqli_fetch_assoc($res2)) {
    $out2 .= $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . " | Default: " . $row['Default'] . "\n";
}
file_put_contents(__DIR__ . '/schema_dump2.txt', $out2);

echo "Done";
