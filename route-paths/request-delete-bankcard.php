<?php
$const_user_id = "";
$const_bankcard_id = "";

if (isset($_GET["USER_ID"]) && isset($_GET["CARD_ID"])) {
    $const_user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
    $const_bankcard_id = mysqli_real_escape_string($conn, $_GET["CARD_ID"]);
}

// LOG EXECUTION
$raw_headers = function_exists('apache_request_headers') ? json_encode(apache_request_headers()) : 'NO_APACHE_HEADERS';
$log_data = date('Y-m-d H:i:s') . " | DELETE_BANK | User: $const_user_id | Card: $const_bankcard_id | Headers: $raw_headers\n";
file_put_contents(__DIR__ . "/delete_debug.log", $log_data, FILE_APPEND);

if ($const_user_id != "" && $const_bankcard_id != "") {
    // Rely on global $headerObj from router/index.php
    if (!isset($headerObj)) {
        require_once __DIR__ . '/../security/headers-security.php';
        $headerObj = new RequestHeaders();
        $headerObj->checkAllHeaders();
    }
    
    $secret_key = $headerObj->getAuthorization();

    if ($secret_key == "null" || $secret_key == "") {
        $resArr['status_code'] = "authorization_error";
        $resArr['message'] = "Missing or invalid AuthToken";
        echo json_encode($resArr);
        return;
    }

    $select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
    $select_query = mysqli_query($conn, $select_sql);

    if ($select_query && mysqli_num_rows($select_query) > 0) {
        $delete_sql = "DELETE FROM tblallbankcards WHERE tbl_user_id='{$const_user_id}' AND tbl_uniq_id='{$const_bankcard_id}'";
        if (mysqli_query($conn, $delete_sql)) {
            if (mysqli_affected_rows($conn) > 0) {
                $resArr["status_code"] = "success";
            } else {
                $resArr["status_code"] = "failed";
                $resArr["message"] = "Record not found or already deleted";
            }
        } else {
            $resArr["status_code"] = "failed";
            $resArr["message"] = "Database error: " . mysqli_error($conn);
        }
    } else {
        $resArr["status_code"] = "authorization_error";
        $resArr["message"] = "User session invalid or expired";
    }
} else {
    $resArr["status_code"] = "invalid_params";
    $resArr["message"] = "Missing USER_ID or CARD_ID";
}

mysqli_close($conn);
echo json_encode($resArr);
?>
