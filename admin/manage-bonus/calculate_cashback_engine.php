<?php
if (!defined('ACCESS_SECURITY')) {
    die("Direct access not allowed.");
}

// 1. Identify all ACTIVE Cashback Promotions first
// Support optional filter by ID ($promo_id_filter)
$where_id = (isset($promo_id_filter) && $promo_id_filter > 0) ? " AND id = $promo_id_filter" : "";
$promo_sql = "SELECT * FROM tbl_cashback_bonuses WHERE status = 'active' $where_id";
$promo_res = mysqli_query($conn, $promo_sql);

// Fallback for date_format if not provided by caller
if (!isset($date_format)) {
    $date_format = '%d-%m-%Y %h:%i:%s %p';
}

$processed_count = 0;
$total_distributed = 0;

if (mysqli_num_rows($promo_res) > 0) {
    while ($promo = mysqli_fetch_assoc($promo_res)) {
        
        $promo_id = $promo['id'];
        $min_loss = (float)$promo['min_loss'];
        $percentage = (float)$promo['percentage'];
        $max_cap = (float)$promo['max_cashback'];
        $is_auto = ($promo['claim_mode'] == 'automatic');
        $target_user = $promo['target_user_id'];

        // 2. Loop through eligible USERS for this specific promotion
        // If target_user_id is set, we only check that user. Otherwise, we check ALL active users.
        $user_where = !empty($target_user) ? "WHERE tbl_uniq_id = '$target_user'" : "";
        $user_sql = "SELECT tbl_uniq_id as user_id, tbl_balance FROM tblusersdata $user_where";
        $user_res = mysqli_query($conn, $user_sql);

        while ($user = mysqli_fetch_assoc($user_res)) {
            $uid = $user['user_id'];
            $current_balance = (float)$user['tbl_balance'];

            // 3. Math: SUM DEPOSITS for this period
            $dep_sql = "SELECT SUM(tbl_recharge_amount) as total FROM tblusersrecharge 
                        WHERE tbl_user_id = '$uid' AND tbl_request_status = 'success'
                        AND tbl_time_stamp BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
            $dep_res = mysqli_query($conn, $dep_sql);
            $total_dep = ($dr = mysqli_fetch_assoc($dep_res)) ? (float)$dr['total'] : 0;

            // 4. Math: SUM WITHDRAWALS for this period
            $wit_sql = "SELECT SUM(tbl_withdraw_amount) as total FROM tbluserswithdraw 
                        WHERE tbl_user_id = '$uid' AND tbl_request_status = 'success'
                        AND tbl_time_stamp BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
            $wit_res = mysqli_query($conn, $wit_sql);
            $total_wit = ($wr = mysqli_fetch_assoc($wit_res)) ? (float)$wr['total'] : 0;

            // 5. Calculate Net Loss Logic
            $net_loss = $total_dep - $total_wit - $current_balance;

            if ($net_loss >= $min_loss) {
                $reward = $net_loss * ($percentage / 100);
                if ($reward > $max_cap) $reward = $max_cap;

                // 6. Prevent Duplicate Logging (Safety Check)
                $check_log = "SELECT id FROM tbl_cashback_logs 
                              WHERE user_id = '$uid' AND promo_id = '$promo_id'
                              AND period_start = '$date_from 00:00:00' AND period_end = '$date_to 23:59:59'";
                
                // Add promo_id to log check if column exists, otherwise use period
                $check_res = mysqli_query($conn, $check_log);
                
                if (mysqli_num_rows($check_res) == 0) {
                    $status = ($is_auto) ? 'credited' : 'pending_claim';
                    $credited_at = ($is_auto) ? 'NOW()' : 'NULL';

                    $log_sql = "INSERT INTO tbl_cashback_logs 
                               (user_id, promo_id, period_start, period_end, calculated_loss, cashback_amount, status, credited_at, created_at)
                               VALUES 
                               ('$uid', $promo_id, '$date_from 00:00:00', '$date_to 23:59:59', $net_loss, $reward, '$status', $credited_at, NOW())";
                    
                    if (mysqli_query($conn, $log_sql)) {
                        if ($is_auto) {
                            $pay_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance + $reward WHERE tbl_uniq_id = '$uid'";
                            mysqli_query($conn, $pay_sql);
                        }
                        $processed_count++;
                        $total_distributed += $reward;
                    }
                }
            }
        }
        
        // 7. Update Last Run timestamp for this promotion
        $update_last_run = "UPDATE tbl_cashback_bonuses SET last_run = NOW() WHERE id = $promo_id";
        mysqli_query($conn, $update_last_run);
    }
}

$message = "Calculation Complete! Processed $processed_count players. Total Cashback distributed: ₹" . number_format($total_distributed, 2);
?>
