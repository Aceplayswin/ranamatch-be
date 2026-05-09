<?php
include '../../security/config.php';
$res = mysqli_query($conn, "DESCRIBE tblusersrecharge");
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
