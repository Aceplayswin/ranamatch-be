<?php
$const_user_id = "";
$const_bankcard_id = "";


if (isset($_GET["USER_ID"]) &&
    isset($_GET["CARD_ID"])) {
    $const_user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
    $const_bankcard_id = mysqli_real_escape_string($conn, $_GET["CARD_ID"]);
}

$secret_key = $headerObj -> getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {

if ($const_user_id != "" && $const_bankcard_id != "") {
    $select_sql = "SELECT tbl_bank_account FROM tblallbankcards WHERE tbl_user_id='{$const_user_id}' AND tbl_uniq_id='{$const_bankcard_id}' ";
    $select_query = mysqli_query($conn, $select_sql);

    if (mysqli_num_rows($select_query) > 0) {
            
        $update_sql = "UPDATE tblallbankcards SET tbl_bank_card_primary = 'false' WHERE tbl_user_id = '{$const_user_id}'";
        $update_query = mysqli_query($conn, $update_sql);
            
        $update_primary_sql = "UPDATE tblallbankcards SET tbl_bank_card_primary = 'true' WHERE tbl_user_id = '{$const_user_id}' AND tbl_uniq_id='{$const_bankcard_id}' ";
        $update_primary_query = mysqli_query($conn, $update_primary_sql);
            
        if($update_primary_query){
            $resArr["status_code"] = "success";
        }
    } else {
            $resArr["status_code"] = "invalid_card_id";
    }
} else {
    $resArr["status_code"] = "invalid_params";
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>
