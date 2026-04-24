<?php

if (!$IS_PRODUCTION_MODE) {
    echo json_encode(["status_code" => "not_allowed", "message" => "You're not allowed to perform this action."]);
    return;
}

$const_user_id = "";
$const_old_password = "";
$const_new_password = "";

$json = file_get_contents('php://input');
$data = json_decode($json);

if (is_object($data) && isset($data->USER_ID, $data->NEW_PASSWORD, $data->OLD_PASSWORD)) {
    $const_user_id = $data->USER_ID;
    $const_new_password = $data->NEW_PASSWORD;
    $const_old_password = $data->OLD_PASSWORD;
} else {
    echo json_encode(["status_code" => "invalid_params"]);
    return;
}

$headerObj = new RequestHeaders();
$headerObj->checkAllHeaders();
$secret_key = $headerObj->getAuthorization();

if (empty($secret_key)) {
    echo json_encode(["status_code" => "authorization_error"]);
    return;
}

if (strlen($const_new_password) < 6) {
    echo json_encode(["status_code" => "password_error", "message" => "Password must be at least 6 characters long."]);
    return;
}

$select_sql = "SELECT tbl_uniq_id, tbl_password FROM tblusersdata WHERE tbl_uniq_id=? AND tbl_auth_secret=? AND tbl_account_status='true'";
$select_query = $conn->prepare($select_sql);
$select_query->bind_param("ss", $const_user_id, $secret_key);
$select_query->execute();
$select_result = $select_query->get_result();

if ($select_result->num_rows > 0) {
    $result = $select_result->fetch_assoc();
    $db_password = $result["tbl_password"];

    if (!password_verify($const_old_password, $db_password)) {
        echo json_encode(["status_code" => "old_password_not_match"]);
        return;
    }

    $hashed_new_password = password_hash($const_new_password, PASSWORD_BCRYPT);

    $update_sql = $conn->prepare("UPDATE tblusersdata SET tbl_password = ? WHERE tbl_uniq_id = ?");
    $update_sql->bind_param("ss", $hashed_new_password, $const_user_id);
    $update_sql->execute();

    if ($update_sql->error == "") {
        echo json_encode(["status_code" => "success"]);
    } else {
        echo json_encode(["status_code" => "sql_error"]);
    }
} else {
    echo json_encode(["status_code" => "authorization_error"]);
}

$select_query->close();
$update_sql->close();
$conn->close();
?>