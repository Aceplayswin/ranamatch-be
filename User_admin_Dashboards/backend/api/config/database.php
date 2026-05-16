<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

$host = "localhost";
$db_name = "user&admin";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        "success" => false, 
        "message" => "Database connection failed. Please check if database 'user&admin' exists.",
        "debug" => $e->getMessage()
    ]);
    exit();
}

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function respond($success, $message, $data = null) {
    if (ob_get_length()) ob_end_clean();
    $response = ["success" => $success, "message" => $message];
    if ($data !== null) $response["data"] = $data;
    echo json_encode($response);
    exit();
}

function getAuthUser($conn) {
    $headers = getallheaders();
    
    // Case-insensitive header check
    $authHeader = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
    
    // Fallback for Apache/CGI
    if (empty($authHeader)) {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    }
    
    $token = !empty($authHeader) ? str_replace('Bearer ', '', $authHeader) : '';
    
    if (empty($token)) {
        respond(false, "Unauthorized: No token provided");
    }
    
    // Token is base64 encoded user id
    $user_id = base64_decode($token);
    
    if (!$user_id || !is_numeric($user_id)) {
        respond(false, "Unauthorized: Invalid token format");
    }
    
    $stmt = $conn->prepare("SELECT id, full_name, username, email, mobile, role, wallet_balance, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        respond(false, "Unauthorized: User not found");
    }
    
    if ($user['status'] === 'blocked') {
        respond(false, "Unauthorized: Account blocked");
    }
    
    return $user;
}
?>
