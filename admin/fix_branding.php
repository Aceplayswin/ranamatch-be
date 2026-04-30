<?php
define("ACCESS_SECURITY", "true");
include 'security/config.php';

function upsert_service($conn, $name, $value) {
    $name = mysqli_real_escape_string($conn, $name);
    $value = mysqli_real_escape_string($conn, $value);
    $chk = mysqli_query($conn, "SELECT * FROM tblservices WHERE tbl_service_name='$name'");
    if(mysqli_num_rows($chk) > 0) {
        mysqli_query($conn, "UPDATE tblservices SET tbl_service_value='$value' WHERE tbl_service_name='$name'");
    } else {
        mysqli_query($conn, "INSERT INTO tblservices (tbl_service_name, tbl_service_value) VALUES ('$name', '$value')");
    }
}

upsert_service($conn, 'SITE_FAVICON_URL', 'favicon.ico');
echo "Branding fixed. Favicon set to favicon.ico";
?>
