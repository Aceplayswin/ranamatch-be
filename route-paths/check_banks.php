<?php
$server_db = "localhost";
$username_db = "root";
$password_db = "";
$hostname_db = "winco";
$c = mysqli_connect($server_db, $username_db, $password_db, $hostname_db);
$res = mysqli_query($c, 'DESCRIBE tblallbankcards');
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
