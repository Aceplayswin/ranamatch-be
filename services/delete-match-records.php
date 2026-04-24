<?php
set_time_limit(500);
define("ACCESS_SECURITY","true");
include '../security/config.php';
include '../security/constants.php';
include '../security/auth_secret.php';

$authObj = new AuthSecret("STARTER",$STARTER_TOKEN);
$auth_secret = $authObj -> validateSimpleKey();

if($auth_secret!="true"){
    echo 'starter token is wrong...';
    return;  
}

$delete_sql = "DELETE FROM matchrecords";
$delete_query = mysqli_query($conn, $delete_sql) or die('error');

if ($delete_query) {
    echo "Match Records Deleted!";
}

mysqli_close($conn);
?>