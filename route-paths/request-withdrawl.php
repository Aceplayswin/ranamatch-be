<?php
$resArr['account_balance'] = "0";
require_once("../services/send-notification-to-admin.php");

$TAX_AMOUNT = 5;
$WITHDRAW_PERCENT_ALLOWED = 100;
$MAX_WITHDRAW_ALLOWED = 3;
$MIN_GAMEPLAY_REQUIRED = 300;

$const_user_id = "";
$const_withdraw_amount = "";
$actual_name = "";
$bank_name = "";
$bank_account = "";
$bank_ifsc_code = "";

function returnRequest($resArr) {
    echo json_encode($resArr);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json);

if (is_object($data) && property_exists($data, 'USER_ID') && property_exists($data, 'WITHDRAW_AMOUNT')) {
    $const_user_id = mysqli_real_escape_string($conn, $data->USER_ID);
    $const_withdraw_amount = mysqli_real_escape_string($conn, $data->WITHDRAW_AMOUNT);
} else {
    $resArr['status_code'] = "invalid_params";
    returnRequest($resArr);
}

$secret_key = $headerObj -> getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
$uniqId = $headerObj->getRandomNumber(15);

$select_sql = "SELECT * FROM tblservices";
$select_query = mysqli_query($conn, $select_sql);

$service_min_withdraw = null;

while ($row = mysqli_fetch_assoc($select_query)) {
    if ($row['tbl_service_name'] == "MIN_WITHDRAW") {
        $service_min_withdraw = $row['tbl_service_value'];
    } elseif ($row['tbl_service_name'] == "WITHDRAW_TAX") {
        $TAX_AMOUNT = (float) $row['tbl_service_value'] / 100;
    } elseif ($row['tbl_service_name'] == "MAX_WITHDRAWL_PERDAY") {
        $MAX_WITHDRAW_ALLOWED = $row['tbl_service_value'];
    }
}

$select_bank_sql = "SELECT * FROM tblallbankcards WHERE tbl_user_id='{$const_user_id}' AND tbl_bank_card_primary='true'";
$select_bank_query = mysqli_query($conn, $select_bank_sql);

if (mysqli_num_rows($select_bank_query) <= 0) {
    $resArr['status_code'] = "primary_bankcard_error";
    returnRequest($resArr);
}

$res_data = mysqli_fetch_assoc($select_bank_query);
$actual_name = $res_data['tbl_beneficiary_name'];
$bank_name = $res_data['tbl_bank_name'] ?: "null";
$bank_account = $res_data['tbl_bank_account'];
$bank_ifsc_code = $res_data['tbl_bank_ifsc_code'] ?: "null";

$withdraw_details = "$actual_name,$bank_account,$bank_ifsc_code,$bank_name";

$select_sql = "SELECT tbl_password,tbl_withdrawl_balance,tbl_commission_balance,tbl_balance,tbl_requiredplay_balance,tbl_account_level,tbl_account_status FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}'";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
    $res_data = mysqli_fetch_assoc($select_query);
    $account_level = $res_data['tbl_account_level'];
    $account_main_balance = $res_data['tbl_balance'];
    $available_balance = $res_data['tbl_balance'];
    $user_withdrawl_balance = $res_data['tbl_withdrawl_balance'];
    $user_commission_balance = $res_data['tbl_commission_balance'];
    $required_play_balance = $res_data['tbl_requiredplay_balance'];

    $allowed_balance = ($WITHDRAW_PERCENT_ALLOWED / 100) * $available_balance;

    $withdraw_count_sql = "SELECT tbl_withdraw_request,tbl_time_stamp FROM tbluserswithdraw WHERE tbl_user_id='{$const_user_id}' AND tbl_request_status!='rejected'";
    $withdraw_count_query = mysqli_query($conn, $withdraw_count_sql);

    $total_num_withdraw = 0;

    while ($withdrawRow = mysqli_fetch_array($withdraw_count_query)) {
        $withdraw_date = substr($withdrawRow['tbl_time_stamp'], 0, strpos($withdrawRow['tbl_time_stamp'], ' '));
        if ($curr_date == $withdraw_date) {
            $total_num_withdraw++;
        }
    }

    if ($total_num_withdraw >= $MAX_WITHDRAW_ALLOWED) {
        $resArr['status_code'] = "maximum_withdraw_limit";
    } elseif ($const_withdraw_amount > $available_balance) {
        $resArr['status_code'] = "insufficient_balance";
    } elseif ($const_withdraw_amount > $allowed_balance) {
        $resArr['status_code'] = "maximum_withdraw_error";
        $resArr['maximum_withdraw'] = $allowed_balance;
    } elseif ($const_withdraw_amount < $service_min_withdraw) {
        $resArr['status_code'] = "minimum_withdraw_error";
        $resArr['minimum_withdraw'] = $service_min_withdraw;
    } elseif ($account_level < 2) {
        $resArr['status_code'] = "no_premium";
    } else {
        $decoded_password = 1;

        if ($decoded_password == 1) {
            if ($res_data['tbl_account_status'] == "true") {
                $extra_msg = "";
                $request_status = "pending";
                $withdraw_request_amount = $const_withdraw_amount - ($const_withdraw_amount * $TAX_AMOUNT);
                $withdraw_request_amount = number_format($withdraw_request_amount, 2, '.', '');

                $insert_sql = $conn->prepare("INSERT INTO tbluserswithdraw(tbl_uniq_id,tbl_user_id,tbl_withdraw_request,tbl_withdraw_amount,tbl_withdraw_details,tbl_request_status,tbl_extra_details,tbl_time_stamp) VALUES(?,?,?,?,?,?,?,?)");
                $insert_sql->bind_param("ssssssss", $uniqId, $const_user_id, $const_withdraw_amount, $withdraw_request_amount, $withdraw_details, $request_status, $extra_msg, $curr_date_time);
                $insert_sql->execute();

                if ($insert_sql->error == "") {
                    $resArr['status_code'] = "success";
                    sendNotification('Request Withdraw!', 'Someone requested a new withdrawal of Rs.' . $withdraw_request_amount, $MESSAGE_TOKEN);
                } else {
                    $resArr['status_code'] = "sql_failed";
                }
            } else {
                $resArr['status_code'] = "failed";
            }
        } else {
            $resArr['status_code'] = "password_error";
        }
    }
} else {
    $resArr['status_code'] = "failed";
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>
