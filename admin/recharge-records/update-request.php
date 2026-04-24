<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_recharge") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
    exit;
}

if (!isset($_GET['uniq-id']) || !isset($_GET['type'])) {
    echo "invalid request";
    return;
}

$uniq_id = mysqli_real_escape_string($conn, $_GET['uniq-id']);
$order_type = mysqli_real_escape_string($conn, $_GET['type']);
$remark = mysqli_real_escape_string($conn, $_REQUEST['remark'] ?? '');

$select_sql = "SELECT tbl_user_id, tbl_recharge_mode, tbl_recharge_amount 
               FROM tblusersrecharge 
               WHERE tbl_uniq_id = '$uniq_id' AND tbl_request_status = 'pending'";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if (mysqli_num_rows($select_result) > 0) {
    $select_res_data = mysqli_fetch_assoc($select_result);
    $user_id = $select_res_data['tbl_user_id'];
    $recharge_amount = $select_res_data['tbl_recharge_amount'];
    $recharge_mode = $select_res_data['tbl_recharge_mode'];

    $update_sql = null;
    $update_account_sql = null;
    $deposit_bonus = 0;

    if ($order_type == "success") {
        $bonus_sql = "SELECT tbl_service_value 
                      FROM tblservices 
                      WHERE tbl_service_name = 'DEPOSIT_BONUS'";
        $bonus_query = mysqli_query($conn, $bonus_sql);
        if (mysqli_num_rows($bonus_query) > 0) {
            $bonus_data = mysqli_fetch_assoc($bonus_query);
            if ($bonus_data['tbl_service_value'] == "true") {
                $bonus_options_sql = "SELECT tbl_service_value 
                                      FROM tblservices 
                                      WHERE tbl_service_name = 'DEPOSIT_BONUS_OPTIONS'";
                $bonus_options_query = mysqli_query($conn, $bonus_options_sql);
                if (mysqli_num_rows($bonus_options_query) > 0) {
                    $bonus_options_data = mysqli_fetch_assoc($bonus_options_query);
                    $bonus_options_string = $bonus_options_data['tbl_service_value'];
                    $bonus_options_array = explode(",", $bonus_options_string);

                    $recharge_count_sql = "SELECT COUNT(*) AS total 
                                           FROM tblusersrecharge 
                                           WHERE tbl_user_id = '$user_id'
                                           AND tbl_request_status = 'success'";
                    $recharge_count_result = mysqli_query($conn, $recharge_count_sql);
                    $recharge_count_data = mysqli_fetch_assoc($recharge_count_result);
                    $recharge_count = (int) $recharge_count_data['total'];

                    $bonus_mapping = [];
                    foreach ($bonus_options_array as $option) {
                        list($index, $bonus) = explode("/", $option);
                        $bonus_mapping[(int) $index] = (float) $bonus;
                    }

                    if (isset($bonus_mapping[$recharge_count])) {
                        $deposit_bonus = $bonus_mapping[$recharge_count];
                    } else {
                        end($bonus_mapping);
                        $deposit_bonus = current($bonus_mapping);
                    }
                }
            }
        }

        if ($IS_REQUIREDPLAY_BALANCE_MODE == true) {
            $update_account_sql = "UPDATE tblusersdata 
                                   SET tbl_balance = tbl_balance + $recharge_amount + $deposit_bonus, 
                                       tbl_account_level = '2',
                                       tbl_recharge_count = tbl_recharge_count + 1,
                                       tbl_total_recharge_amount = tbl_total_recharge_amount + $recharge_amount
                                   WHERE tbl_uniq_id = '$user_id' 
                                   AND tbl_account_status = 'true'";
        } else {
            $update_account_sql = "UPDATE tblusersdata 
                                   SET tbl_balance = tbl_balance + $recharge_amount + $deposit_bonus, 
                                       tbl_account_level = '2',
                                       tbl_recharge_count = tbl_recharge_count + 1,
                                       tbl_total_recharge_amount = tbl_total_recharge_amount + $recharge_amount
                                   WHERE tbl_uniq_id = '$user_id' 
                                   AND tbl_account_status = 'true'";
        }

        $update_sql = "UPDATE tblusersrecharge 
                       SET tbl_request_status = 'success', tbl_remark = '$remark' 
                       WHERE tbl_uniq_id = '$uniq_id'";
    } else {
        $update_sql = "UPDATE tblusersrecharge 
                       SET tbl_request_status = 'rejected', tbl_remark = '$remark' 
                       WHERE tbl_uniq_id = '$uniq_id'";
    }

    $update_result = mysqli_query($conn, $update_sql) or die('error');

    if ($update_result) {
        if ($update_account_sql != null) {
            mysqli_query($conn, $update_account_sql) or die('error');
        }
        if ($deposit_bonus > 0) {
            $insert_transaction_sql = "INSERT INTO tblotherstransactions 
                                       (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
                                       VALUES ('$user_id', 'app', 'deposit bonus', '$deposit_bonus', '$recharge_mode', NOW())";
            mysqli_query($conn, $insert_transaction_sql) or die('error');
        }
        
        if(isset($_REQUEST['ajax'])) {
            echo json_encode([
                "status" => "success",
                "message" => "Recharge request processed successfully!",
                "data" => [
                    "user_id" => $user_id,
                    "amount" => $recharge_amount,
                    "bonus" => $deposit_bonus,
                    "mode" => $recharge_mode,
                    "type" => $order_type
                ]
            ]);
        } else {
            echo "<script>alert('Data updated!!');window.close();</script>";
        }
    } else {
        if(isset($_REQUEST['ajax'])) {
            echo json_encode(["status" => "error", "message" => "Failed to update record."]);
        } else {
            echo "<script>alert('Failed to update!!');window.close();</script>";
        }
    }
} else {
    if(isset($_REQUEST['ajax'])) {
        echo json_encode(["status" => "error", "message" => "Invalid request or request already processed."]);
    } else {
        echo "<script>alert('Sorry! Something went wrong!');window.close();</script>";
    }
}
?>