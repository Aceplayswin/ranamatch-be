<?php
define("ACCESS_SECURITY", true);
include '../security/config.php';
if (!isset($conn)) {
    die("Connection failed");
}

$res = $conn->query("DESCRIBE tbl_promotions");
$schema = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $schema[] = $row;
    }
} else {
    echo "Table not found: tbl_promotions";
}

header('Content-Type: application/json');
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
