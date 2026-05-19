<?php
define("ACCESS_SECURITY", "true");
$protocol = "http";
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/router/route-account-info?USER_ID=8091921';
$_GET["USER_ID"] = "8091921";

// Mock header authorization
class RequestHeaders {
    public function getAuthorization() {
        return "fi20avy4rr7udvhj9rkp4d9bxwjc9g"; // secret key for 8091921
    }
    public function getRoute() {
        return "route-account-info";
    }
    public function checkCorsPolicy($methods) {}
    public function checkAllHeaders() {}
}

$headerObj = new RequestHeaders();

include 'd:/xampp/htdocs/security/license.php';
include 'd:/xampp/htdocs/security/config.php';
include 'd:/xampp/htdocs/security/constants.php';
include 'd:/xampp/htdocs/router/route-paths.php';

$resArr = array();
$resArr['data'] = array();

date_default_timezone_set('Asia/Kolkata');
$curr_date = date("d-m-Y");
$curr_time = date("h:i a");
$curr_date_time = $curr_date . ' ' . $curr_time;

// Let's run a test query right here
$user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
$secret_key = $headerObj->getAuthorization();
echo "TEST USER_ID: " . $user_id . "\n";
echo "TEST SECRET_KEY: " . $secret_key . "\n";
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);
echo "TEST ROWS: " . mysqli_num_rows($select_query) . "\n";

ob_start();
include 'd:/xampp/htdocs/route-paths/request-account-info.php';
$output = ob_get_clean();

echo "RAW OUTPUT:\n";
echo $output . "\n";
?>
