<?php

$const_user_id = "";
$const_user_actual_name = "";
$const_user_bank_name = "";
$const_user_bank_account = "";
$const_user_bank_ifsc_code = "";
$const_is_primary = "";
$card_method = "";
$max_cards_limit = 3;

$new_uniq_id = $headerObj -> getRandomString(15);

if (
    isset($_GET["USER_ID"]) &&
    isset($_GET["BENEFICIARY_NAME"]) &&
    isset($_GET["USER_BANK_NAME"]) &&
    isset($_GET["USER_BANK_ACCOUNT"]) &&
    isset($_GET["USER_BANK_IFSC_CODE"]) &&
    isset($_GET["CARD_METHOD"])
) {
    $const_user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
    $const_user_actual_name = mysqli_real_escape_string(
        $conn,
        $_GET["BENEFICIARY_NAME"]
    );
    $const_user_bank_name = mysqli_real_escape_string(
        $conn,
        $_GET["USER_BANK_NAME"]
    );
    $const_user_bank_account = mysqli_real_escape_string(
        $conn,
        $_GET["USER_BANK_ACCOUNT"]
    );
    $const_user_bank_ifsc_code = mysqli_real_escape_string(
        $conn,
        $_GET["USER_BANK_IFSC_CODE"]
    );
        
    $const_is_primary = mysqli_real_escape_string($conn, $_GET["IS_PRIMARY"]);
    $card_method = mysqli_real_escape_string($conn, $_GET["CARD_METHOD"]);
}
    
if($card_method=="upi"){
    $const_user_bank_name = "none";
    $const_user_bank_ifsc_code = "none";
}
    
if (
    $const_user_id != "" &&
    $const_user_actual_name != "" &&
    $const_user_bank_name != "" &&
    $const_user_bank_account != "" &&
    $const_user_bank_ifsc_code != "" &&
    $const_is_primary != "" &&
    $card_method != ""
) {

    $headerObj = new RequestHeaders();
    $headerObj -> checkAllHeaders();
    $secret_key = $headerObj -> getAuthorization();
   
    if($secret_key=="null" || $secret_key==""){
      $resArr['status_code'] = "authorization_error";
      echo json_encode($resArr);
      return;
    }
   
}else{
    $resArr['status_code'] = "invalid_params";
    echo json_encode($resArr);
    return;
}

$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {

$select_sql = "SELECT tbl_bank_account FROM tblallbankcards WHERE tbl_bank_account='{$const_user_bank_account}' AND tbl_user_id='{$const_user_id}' ";
$select_query = mysqli_query($conn, $select_sql);
$found_rows = mysqli_num_rows($select_query);

// DEBUG LOGGING
$log_msg = date('Y-m-d H:i:s') . " | ADD BANK | User: $const_user_id | Acc: $const_user_bank_account | Found: $found_rows\n";
file_put_contents(__DIR__ . "/bank_card_debug.log", $log_msg, FILE_APPEND);

if ($found_rows > 0) {
    $resArr["status_code"] = "already_exist";
} else {
    $select_sql = "SELECT * FROM tblallbankcards WHERE tbl_user_id='{$const_user_id}' ";
    $select_query = mysqli_query($conn, $select_sql);
    $data_count = mysqli_num_rows($select_query);
 
    if ($data_count >= $max_cards_limit) {
        $resArr["status_code"] = "limit_reached";
    } else {
        if ($const_is_primary == "true") {
            $update_sql = "UPDATE tblallbankcards SET tbl_bank_card_primary = 'false' WHERE tbl_user_id = '{$const_user_id}'";
            $update_query = mysqli_query($conn, $update_sql);
        }

        $insert_sql = $conn->prepare("INSERT INTO tblallbankcards(tbl_uniq_id,tbl_user_id,tbl_beneficiary_name,tbl_bank_name,tbl_bank_account,tbl_bank_ifsc_code,tbl_bank_card_primary,tbl_time_stamp) VALUES(?,?,?,?,?,?,?,?)");
        $insert_sql->bind_param(
            "ssssssss",
            $new_uniq_id,
            $const_user_id,
            $const_user_actual_name,
            $const_user_bank_name,
            $const_user_bank_account,
            $const_user_bank_ifsc_code,
            $const_is_primary,
            $curr_date_time
        );
        $insert_sql->execute();

        if ($insert_sql->error == "") {
            $resArr["status_code"] = "success";
        } else {
            $resArr["status_code"] = "failed";
        }
    }
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>