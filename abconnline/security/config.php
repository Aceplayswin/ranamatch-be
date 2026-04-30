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
 $hostname_db = "winco";
 $username_db = "winco";
 $password_db = "winco";

 try{
    if ($conn = mysqli_connect($server_db ,$username_db, $password_db, $hostname_db ))
    {
        $is_db_connected = "true";
        mysqli_set_charset($conn, 'utf8mb4');
    }
    else
    {
        throw new Exception('Unable to connect');
    }
 }catch (Throwable $e) {
    // Handle error
    echo $e->getMessage();
    echo "Please setup extension properly.";
  }   
    
}else{
 echo "permission denied!";
 return;
}
?>