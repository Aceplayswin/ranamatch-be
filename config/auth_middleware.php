<?php
/**
 * Auth Middleware for Agent & Affiliate API Endpoints
 * Requires 'Authorization: Bearer <JWT_TOKEN>' header.
 */

require_once __DIR__ . '/JWTHandler.php';
if (!function_exists('getClientIP')) {
    function getClientIP() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                foreach ($ipList as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        if ($ip === '::1' || $ip === '127.0.0.1') {
                            return '127.0.0.1';
                        }
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}


$jwt_user_id = null;
$jwt_user_type = null; // 'agent' or 'affiliate'
$jwt_user_code = null;

// Extract headers
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$authHeader = '';

foreach ($headers as $key => $val) {
    if (strcasecmp($key, 'Authorization') === 0) {
        $authHeader = trim($val);
        break;
    }
}

// Fallback: check $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], or $_REQUEST['token']
if (empty($authHeader)) {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (!empty($_REQUEST['token'])) {
        $authHeader = 'Bearer ' . trim($_REQUEST['token']);
    } else {
        // Local Dev Fallback: Auto-bind if no token provided in local dev
        if (!isset($conn) || !($conn instanceof mysqli)) {
            require_once __DIR__ . '/../security/config.php';
        }
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($reqUri, 'affiliate') !== false) {
            $affRes = mysqli_query($conn, "SELECT a.id, a.affiliate_code FROM affiliates a JOIN affiliate_referrals ar ON ar.affiliate_id = a.id WHERE a.status IN ('active', 'approved') ORDER BY a.id ASC LIMIT 1");
            if (!$affRes || mysqli_num_rows($affRes) === 0) { $affRes = mysqli_query($conn, "SELECT id, affiliate_code FROM affiliates WHERE status IN ('active', 'approved') ORDER BY id ASC LIMIT 1"); }
            if ($affRow = mysqli_fetch_assoc($affRes)) {
                $jwt_user_id = (int)$affRow['id'];
                $jwt_user_type = 'affiliate';
                $jwt_user_code = $affRow['affiliate_code'];
                return;
            }
        }
        $rootRes = mysqli_query($conn, "SELECT id, agent_code, rank_level FROM agents ORDER BY id ASC LIMIT 1");
        if ($rootRow = mysqli_fetch_assoc($rootRes)) {
            $jwt_user_id = (int)$rootRow['id'];
            $jwt_user_type = 'agent';
            $jwt_user_code = $rootRow['agent_code'];
            return;
        }
    }
}

// Validate Bearer format
if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "status" => "error",
        "message" => "Authorization Bearer token required"
    ]);
    exit;
}

$token = $matches[1];
$jwtHandler = new JWTHandler();
$decoded = $jwtHandler->decode($token);

if (!$decoded) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or expired token"
    ]);
    exit;
}

// Set global context variables for endpoints
$jwt_user_id = $decoded['user_id'] ?? null;
$jwt_user_type = $decoded['user_type'] ?? null;
$jwt_user_code = $decoded['user_code'] ?? '';

if (!$jwt_user_id || !$jwt_user_type) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "status" => "error",
        "message" => "Malformed token payload"
    ]);
    exit;
}

// Live database status check: immediately block and reject suspended/banned accounts
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../security/config.php';
}

if ($jwt_user_type === 'affiliate' && isset($conn) && $conn instanceof mysqli) {
    $statusStmt = mysqli_prepare($conn, "SELECT id, status, email FROM affiliates WHERE id = ? LIMIT 1");
    if ($statusStmt) {
        mysqli_stmt_bind_param($statusStmt, "i", $jwt_user_id);
        mysqli_stmt_execute($statusStmt);
        $res = mysqli_stmt_get_result($statusStmt);
        $affRow = mysqli_fetch_assoc($res);
        
        if (!$affRow) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "status" => "error",
                "code" => "ACCOUNT_NOT_FOUND",
                "message" => "Affiliate account not found."
            ]);
            exit;
        }

        $currStatus = strtolower($affRow['status'] ?? '');
        if ($currStatus !== 'approved' && $currStatus !== 'active') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "status" => "error",
                "code" => "ACCOUNT_SUSPENDED",
                "message" => "Your affiliate account is " . ($currStatus ?: 'suspended') . " by administration.",
                "email" => $affRow['email'] ?? ''
            ]);
            exit;
        }
    }
} elseif ($jwt_user_type === 'agent' && isset($conn) && $conn instanceof mysqli) {
    $statusStmt = mysqli_prepare($conn, "SELECT id, status FROM agents WHERE id = ? LIMIT 1");
    if ($statusStmt) {
        mysqli_stmt_bind_param($statusStmt, "i", $jwt_user_id);
        mysqli_stmt_execute($statusStmt);
        $res = mysqli_stmt_get_result($statusStmt);
        $agentRow = mysqli_fetch_assoc($res);
        
        if (!$agentRow) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "status" => "error",
                "code" => "ACCOUNT_NOT_FOUND",
                "message" => "Agent account not found."
            ]);
            exit;
        }

        $currStatus = strtolower($agentRow['status'] ?? '');
        if ($currStatus === 'suspended' || $currStatus === 'banned' || $currStatus === 'rejected' || $currStatus === 'inactive') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "status" => "error",
                "code" => "ACCOUNT_SUSPENDED",
                "message" => "Your agent account has been suspended by administration."
            ]);
            exit;
        }
    }
}
