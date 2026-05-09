<?php
$c = mysqli_connect('127.0.0.1', 'root', '', 'winco');
$res = mysqli_query($c, 'DESCRIBE tblallbankcards');
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
