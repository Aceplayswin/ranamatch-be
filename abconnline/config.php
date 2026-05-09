<?php
if(defined("ACCESS_SECURITY")){
    // Global Anti-Cache for Admin
    session_cache_limiter('nocache');
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    date_default_timezone_set('Asia/Kolkata');
 $is_db_connected = "false";
 // database config
$server_db = "localhost";
$hostname_db = "ranamatch";
$username_db = "ranamatch";
$password_db = "ranamatch";


 try{
    if ($conn = mysqli_connect($server_db ,$username_db, $password_db, $hostname_db ))
    {
        $is_db_connected = "true";
        mysqli_set_charset($conn, 'utf8mb4');

        // --- GLOBAL AUTO-MIGRATION ---
        // Ensure all balance-related columns exist in tblusersdata to prevent query crashes
        $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_bonus_balance'");
        if (mysqli_num_rows($check_cols) == 0) {
            mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_bonus_balance DECIMAL(20,2) DEFAULT 0.00 AFTER tbl_balance");
        }
        $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_sports_bonus'");
        if (mysqli_num_rows($check_cols) == 0) {
            mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_sports_bonus DECIMAL(20,2) DEFAULT 0.00 AFTER tbl_bonus_balance");
        }
        $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_requiredplay_balance'");
        if (mysqli_num_rows($check_cols) == 0) {
            mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_requiredplay_balance DECIMAL(20,2) DEFAULT 0.00 AFTER tbl_sports_bonus");
        }
        $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_active_bonus_id'");
        if (mysqli_num_rows($check_cols) == 0) {
            mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_active_bonus_id INT DEFAULT 0 AFTER tbl_requiredplay_balance");
        }
        $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_is_bonus_locked'");
        if (mysqli_num_rows($check_cols) == 0) {
            mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_is_bonus_locked TINYINT(1) DEFAULT 0 AFTER tbl_active_bonus_id");
        }
    }
    else
    {
        throw new Exception('Unable to connect');
    }
    }catch (Exception $e) {
        // Handle error without breaking headers
        file_put_contents(__DIR__ . "/db_error.log", date('Y-m-d H:i:s') . " - DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
        header('Content-Type: application/json');
        die(json_encode(["status_code" => "db_connection_error", "message" => "Database connection failed"]));
    }   
    
}else{
 echo "permission denied!";
 return;
}
?>