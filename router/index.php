<?php
file_put_contents(__DIR__ . "/router_hit.log", date('Y-m-d H:i:s') . " - HIT: " . $_SERVER['REQUEST_URI'] . " - Method: " . $_SERVER['REQUEST_METHOD'] . " - Headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND);
/*
 Don't edit this file without developer permission.
 For any help please contact developer here: abcd@gmail.com
*/
header("Access-Control-Allow-Origin: *");

define("ACCESS_SECURITY", "true");
file_put_contents(__DIR__ . "/router_debug.log", date('Y-m-d H:i:s') . " - REQ: " . $_SERVER['REQUEST_URI'] . " - Headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND);
include '../security/headers-security.php';


// check for all request headers
$headerObj = new RequestHeaders();
$headerObj->checkCorsPolicy("GET,POST,OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
$headerObj->checkAllHeaders();


// including required files
include '../security/license.php';
include '../security/config.php';
include '../security/constants.php';
include 'route-paths.php';


// setting up empty array
$resArr = array();
$resArr['data'] = array();


// setting real date & time
date_default_timezone_set('Asia/Kolkata');
$curr_date = date("d-m-Y");
$curr_time = date("h:i a");
$curr_date_time = $curr_date . ' ' . $curr_time;


// validating license
// $licenseObj = new RequestLicense();
// if($licenseObj -> validateLicense()==="true"){

// Get the requested URL
$base_url = parse_url($_SERVER['REQUEST_URI'])['path'];
$route_path = $headerObj->getRoute();
$request_uri = '/' . $route_path;

// Debug all requests
$req_log = date('Y-m-d H:i:s') . " | REQ | URI: " . $_SERVER['REQUEST_URI'] . " | Route: $request_uri | User: " . ($_GET['USER_ID'] ?? 'N/A') . "\n";
file_put_contents(__DIR__ . "/play_debug.txt", $req_log, FILE_APPEND);

// Check if the requested route exists
file_put_contents(__DIR__ . "/route_mapping.log", date('Y-m-d H:i:s') . " - Requested URI: $request_uri - Available Routes: " . implode(', ', array_keys($routes)) . "\n", FILE_APPEND);
if (array_key_exists($request_uri, $routes)) {
  $request_path = $routes[$request_uri];

  if ($request_path == "default") {
    echo "invalid_route_request";
    return;
  }

  // Handle the route
  switch ($route_path) {
    case 'status':
      echo "Routing is working..";
      break;

    default:
      $inc_path = '../' . $request_path;
      file_put_contents(__DIR__ . "/router_final.log", date('Y-m-d H:i:s') . " - Including: $inc_path\n", FILE_APPEND);
      if (file_exists($inc_path)) {
          file_put_contents(__DIR__ . "/router_final.log", date('Y-m-d H:i:s') . " - File exists, including now...\n", FILE_APPEND);
          include $inc_path;
          file_put_contents(__DIR__ . "/router_final.log", date('Y-m-d H:i:s') . " - Include finished.\n", FILE_APPEND);
      } else {
          file_put_contents(__DIR__ . "/router_final.log", date('Y-m-d H:i:s') . " - FILE NOT FOUND: $inc_path\n", FILE_APPEND);
      }
      break;
  }
} else {
  // Handle other routes or show a 404 page
  echo "invalid_route_request_1";
}
// }else{
//     // Handle unauthorized access (e.g., return an error)
//     header("HTTP/1.1 401 Invalid License");
//     exit();
// }


// print_r(parse_url($_SERVER['REQUEST_URI']));
// print_r(apache_request_headers());
// return;
?>