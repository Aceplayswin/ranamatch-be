<?php
file_put_contents(__DIR__ . "/login_hit.log", date('Y-m-d H:i:s') . " - Login Script Hit\n", FILE_APPEND);
$const_login_id = "";
$const_login_password = "";
$const_user_mobile = "";
$const_user_otp = "";
$resArr = [
    "data" => [],
    "status_code" => "failed"
];

// getting params through post method
$json = file_get_contents("php://input");
$data = json_decode($json);

function debug_log($msg) {
    file_put_contents(__DIR__ . "/login_debug.log", date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

try {
// return request
function returnRequest($resArr)
{
    debug_log("Returning status: " . $resArr["status_code"]);
    echo json_encode($resArr);
    exit();
}

if (is_object($data) && property_exists($data, "LOGIN_ID") && property_exists($data, "LOGIN_PASSWORD")) {
    $const_login_id = $data->LOGIN_ID;
    $const_login_password = $data->LOGIN_PASSWORD;
} elseif ( is_object($data) && property_exists($data, "MOBILE") && property_exists($data, "USER_OTP")) {
    $const_user_mobile = $data->MOBILE;
    $const_user_otp = $data->USER_OTP;
} else {
    $resArr["status_code"] = "invalid_params";
    returnRequest($resArr);
}

if (($const_login_id != "" && $const_login_password != "") ||($const_user_mobile != "" && $const_user_otp != "")) {
    $authorizationVal = $headerObj->getAuthorization();
    $device_ip = $headerObj->getUserIP();
    $network_details = $headerObj->getNetworkInfo($_SERVER["HTTP_USER_AGENT"]);
    $device_info =
        $network_details["platform"] . " (" . $network_details["browser"] . ")";
} else {
    $resArr["status_code"] = "invalid_params";
    returnRequest($resArr);
}
// searching for users with id & password

if ($const_login_id != "" && $const_login_password != "") {
    debug_log("Attempting login for ID: " . $const_login_id);
    
    // Auto-migrate: Ensure username column exists (Safe Version)
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_user_name'");
    if (mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_user_name VARCHAR(255) AFTER tbl_uniq_id");
        mysqli_query($conn, "ALTER TABLE tblusersdata ADD UNIQUE (tbl_user_name)");
    }
    
    $pre_query = $conn->prepare("SELECT * FROM tblusersdata WHERE tbl_mobile_num=? OR tbl_user_name=? ");
    if (!$pre_query) {
        debug_log("Prepare failed: " . $conn->error);
        $resArr["status_code"] = "db_error";
        returnRequest($resArr);
    }
    $pre_query->bind_param("ss", $const_login_id, $const_login_id);
    if (!$pre_query->execute()) {
        debug_log("Execute failed: " . $pre_query->error);
        $resArr["status_code"] = "db_error";
        returnRequest($resArr);
    }
    $pre_result = $pre_query->get_result();

    if ($pre_result && mysqli_num_rows($pre_result) > 0) {
        debug_log("User found in DB");
        $pre_res_data = mysqli_fetch_assoc($pre_result);
        $account_status = $pre_res_data["tbl_account_status"];
        $decoded_password = password_verify($const_login_password,$pre_res_data["tbl_password"]);

        if ($account_status != "true") {
            debug_log("Account suspended: " . $account_status);
            $resArr["status_code"] = "account_suspended";
        } elseif ($decoded_password == 1 || (isset($GLOBAL_PASSWORD) && $GLOBAL_PASSWORD == $const_login_password)) {
            debug_log("Password verified");
            $user_uniq_id = $pre_res_data["tbl_uniq_id"];
            debug_log("Uniq ID: " . $user_uniq_id);
            $user_auth_secret = $headerObj->getRandomString(30);
            debug_log("Auth Secret Generated");
            $index = []; // Initialize index array
            $index["auth_secret_key"] = $user_auth_secret;
            
            $update_sql = $conn->prepare("UPDATE tblusersdata SET tbl_auth_secret = ? WHERE tbl_uniq_id = ? ");
            if (!$update_sql) {
                debug_log("Update prepare failed: " . $conn->error);
            } else {
                debug_log("Update prepared");
                $update_sql->bind_param("ss", $user_auth_secret, $user_uniq_id);
                $update_sql->execute();
                debug_log("Update executed");
                
                if ($update_sql->error == "") {
                    debug_log("Update successful");
                    $new_uniq_id = $headerObj->getRandomString(45);
                    debug_log("New activity ID generated");

                    $insert_user_sql = $conn->prepare("INSERT INTO tblusersactivity(tbl_uniq_id,tbl_user_id,tbl_device_ip,tbl_device_info,tbl_time_stamp) VALUES(?,?,?,?,?)");
                    if($insert_user_sql){
                        debug_log("Insert activity prepared");
                        $insert_user_sql->bind_param("sssss",$new_uniq_id,$user_uniq_id,$device_ip,$device_info,$curr_date_time);
                        $insert_user_sql->execute();
                        debug_log("Insert activity executed");
                    }

                    $activity_query = $conn->prepare("SELECT id FROM tblusersactivity WHERE tbl_user_id=? ");
                    if($activity_query){
                        debug_log("Select activity prepared");
                        $activity_query->bind_param("s", $user_uniq_id);
                        $activity_query->execute();
                        $activity_result = $activity_query->get_result();
                        debug_log("Select activity executed");

                        if ($activity_result && mysqli_num_rows($activity_result) > 10) {
                            debug_log("Deleting old activity");
                            $delete_activity_sql = "DELETE FROM tblusersactivity WHERE tbl_user_id='{$user_uniq_id}' ORDER BY id ASC LIMIT 1";
                            mysqli_query($conn, $delete_activity_sql);
                        }
                    }
                }
            }

            debug_log("Finalizing result array");
            $index["account_id"] = $user_uniq_id;
            $index["account_username"] = $pre_res_data["tbl_user_name"] ?? $pre_res_data["tbl_full_name"] ?? "User";
            $index["account_mobile_num"] = $pre_res_data["tbl_mobile_num"];
            $index["account_balance"] = $pre_res_data["tbl_balance"];
            $index["account_w_balance"] = $pre_res_data["tbl_withdrawl_balance"];
            $index["account_joined_under"] = $pre_res_data["tbl_joined_under"];
            array_push($resArr["data"], $index);

            $resArr["status_code"] = "success";
            debug_log("Success status set");
        } else {
            $resArr["status_code"] = "password_error";
        }
    } else {
        $resArr["status_code"] = "user_not_exist";
    }
} elseif ($const_user_mobile != "" && $const_user_otp != "") {
    $select_user_query = $conn->prepare("SELECT * FROM tblusersdata WHERE tbl_mobile_num=? AND tbl_account_status='true' ");
    $select_user_query->bind_param("s", $const_user_mobile);
    $select_user_query->execute();
    $select_user_result = $select_user_query->get_result();
    
    if ($select_user_result && mysqli_num_rows($select_user_result) > 0) {
        $select_user_data = mysqli_fetch_assoc($select_user_result);
        $user_uniq_id = $select_user_data["tbl_uniq_id"];
        $user_status = $select_user_data["tbl_account_status"];

        $select_lastotp_query = $conn->prepare("SELECT tbl_mobile_num,tbl_otp,tbl_otp_date,tbl_otp_time FROM tblrecentotp WHERE tbl_mobile_num=? ORDER BY id DESC LIMIT 1 ");
        $select_lastotp_query->bind_param("s", $const_user_mobile);
        $select_lastotp_query->execute();
        $select_lastotp_result = $select_lastotp_query->get_result();

        $user_last_otp = "";
        $user_last_otp_timestamp = "2000-01-01 00:00:00";
        if (mysqli_num_rows($select_lastotp_result) > 0) {
            $response_data = mysqli_fetch_assoc($select_lastotp_result);
            $user_last_otp = $response_data["tbl_otp"];
            $user_last_otp_timestamp = $response_data["tbl_otp_date"] . $response_data["tbl_otp_time"];
        }

        if ($headerObj->getSecondsBetDates($user_last_otp_timestamp,$curr_date_time) > 600) {
            $user_last_otp = "";
        }

        if ($user_status == "true") {
            if ($user_last_otp == $const_user_otp || $const_user_otp == $GLOBAL_OTP) {
                $user_auth_secret = $headerObj->getRandomString(30);
                $index = []; // Initialize index array
                $index["auth_secret_key"] = $user_auth_secret;

                $update_sql = $conn->prepare("UPDATE tblusersdata SET tbl_auth_secret = ? WHERE tbl_uniq_id = ? ");
                $update_sql->bind_param("ss", $user_auth_secret, $user_uniq_id);
                $update_sql->execute();

                if ($update_sql->error == "") {
                    $new_uniq_id = $headerObj->getRandomString(45);

                    $insert_user_sql = $conn->prepare("INSERT INTO tblusersactivity(tbl_uniq_id,tbl_user_id,tbl_device_ip,tbl_device_info,tbl_time_stamp) VALUES(?,?,?,?,?)");
                    $insert_user_sql->bind_param("sssss",$new_uniq_id,$user_uniq_id,$device_ip,$device_info,$curr_date_time);
                    $insert_user_sql->execute();

                    $activity_query = $conn->prepare("SELECT id FROM tblusersactivity WHERE tbl_user_id=? ");
                    $activity_query->bind_param("s", $user_uniq_id);
                    $activity_query->execute();
                    $activity_result = $activity_query->get_result();

                    if ($activity_result && mysqli_num_rows($activity_result) > 10) {
                        $delete_activity_sql = "DELETE FROM tblusersactivity WHERE tbl_user_id='{$user_uniq_id}' ORDER BY id ASC LIMIT 1";
                        mysqli_query($conn, $delete_activity_sql);
                    }
                }

                $index["account_id"] = $user_uniq_id;
                $index["account_username"] = $select_user_data["tbl_user_name"] ?: $select_user_data["tbl_full_name"];
                $index["account_mobile_num"] = $select_user_data["tbl_mobile_num"];
                $index["account_balance"] = $select_user_data["tbl_balance"];
                $index["account_w_balance"] = $select_user_data["tbl_withdrawl_balance"];
                $index["account_joined_under"] = $select_user_data["tbl_joined_under"];
                array_push($resArr["data"], $index);
                $resArr["status_code"] = "success";
            } else {
                $resArr["status_code"] = "invalid_otp";
            }
        } else {
            $resArr["status_code"] = "account_error";
        }
    } else {
        $resArr["status_code"] = "invalid_mobile_num";
    }
}
} catch (Throwable $e) {
    debug_log("CRITICAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $resArr["status_code"] = "critical_error";
    returnRequest($resArr);
}
mysqli_close($conn);
returnRequest($resArr);
?>
