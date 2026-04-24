<?php
// API to fetch detailed information about the user's currently active bonus
// Included by the router

$user_id = isset($_GET['USER_ID']) ? mysqli_real_escape_string($conn, $_GET['USER_ID']) : "";
$secret_key = isset($headerObj) ? $headerObj->getAuthorization() : "";

if ($user_id == "" || $secret_key == "") {
    $resArr['status_code'] = "invalid_params";
    echo json_encode($resArr);
    return;
}

// 1. Verify User and get current play balance
$user_sql = "SELECT tbl_requiredplay_balance, tbl_active_bonus_id, tbl_bonus_balance, tbl_sports_bonus, tbl_balance 
             FROM tblusersdata 
             WHERE tbl_uniq_id='$user_id' AND tbl_auth_secret='$secret_key'";
$user_res = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_res) == 0) {
    $resArr['status_code'] = "authorization_error";
    echo json_encode($resArr);
    return;
}

$user_data = mysqli_fetch_assoc($user_res);
$active_bonus_id = (int)$user_data['tbl_active_bonus_id'];

if ($active_bonus_id <= 0) {
    if ((float)$user_data['tbl_requiredplay_balance'] > 0) {
        $recovery_sql = "SELECT bonus_id FROM tbl_bonus_redemptions WHERE user_id='$user_id' AND status='active' ORDER BY id DESC LIMIT 1";
        $recovery_res = mysqli_query($conn, $recovery_sql);
        if ($recovery_row = mysqli_fetch_assoc($recovery_res)) {
            $active_bonus_id = (int)$recovery_row['bonus_id'];
            // Auto-heal: Sync it back to the user table to fix the ghost lock permanently
            mysqli_query($conn, "UPDATE tblusersdata SET tbl_active_bonus_id = $active_bonus_id WHERE tbl_uniq_id = '$user_id'");
        } else {
            $resArr['status_code'] = "no_active_bonus";
            $resArr['message'] = "Wagering lock exists but no active bonus record found.";
            echo json_encode($resArr);
            return;
        }
    } else {
        $resArr['status_code'] = "no_active_bonus";
        $resArr['message'] = "No active bonus found for this account.";
        echo json_encode($resArr);
        return;
    }
}

// 2. Fetch Detailed Redemption Info joined with Bonus Meta
$sql = "SELECT r.bonus_amount, r.wagering_required, r.wagering_completed, r.status as redemption_status, r.created_at as claim_date,
               b.name, b.type, b.end_at, b.bonus_category,
               c.title, c.description, c.terms_conditions
        FROM tbl_bonus_redemptions r
        JOIN tbl_bonuses b ON r.bonus_id = b.id
        LEFT JOIN tbl_bonus_content c ON b.id = c.bonus_id AND c.lang_code = 'en'
        WHERE r.user_id = '$user_id' AND r.bonus_id = $active_bonus_id AND r.status = 'active'
        ORDER BY r.id DESC LIMIT 1";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $bonus_details = mysqli_fetch_assoc($result);
    
    // Supplement with current live data from user table
    $bonus_details['current_remaining_wagering'] = (float)$user_data['tbl_requiredplay_balance'];
    $bonus_details['current_bonus_balance'] = ($bonus_details['bonus_category'] == 'sports') ? (float)$user_data['tbl_sports_bonus'] : (float)$user_data['tbl_bonus_balance'];
    $bonus_details['real_balance'] = (float)$user_data['tbl_balance'];
    
    $resArr['status'] = "success";
    $resArr['status_code'] = "success";
    $resArr['data'] = $bonus_details;
} else {
    // FALLBACK: If redemption log is missing, pull basic info from tbl_bonuses
    $fallback_sql = "SELECT b.name, b.type, b.end_at, b.bonus_category, b.amount as bonus_amount, 0 as wagering_required, 0 as wagering_completed,
                         c.title, c.description, c.terms_conditions
                  FROM tbl_bonuses b
                  LEFT JOIN tbl_bonus_content c ON b.id = c.bonus_id AND c.lang_code = 'en'
                  WHERE b.id = $active_bonus_id LIMIT 1";
    $f_res = mysqli_query($conn, $fallback_sql);
    if($f_row = mysqli_fetch_assoc($f_res)) {
        $f_row['current_remaining_wagering'] = (float)$user_data['tbl_requiredplay_balance'];
        $f_row['current_bonus_balance'] = ($f_row['bonus_category'] == 'sports') ? (float)$user_data['tbl_sports_bonus'] : (float)$user_data['tbl_bonus_balance'];
        $f_row['real_balance'] = (float)$user_data['tbl_balance'];
        $f_row['redemption_status'] = 'active';
        $f_row['claim_date'] = date('Y-m-d H:i:s');
        
        $resArr['status'] = "success";
        $resArr['status_code'] = "success";
        $resArr['data'] = $f_row;
    } else {
        // FALLBACK 2: Check tbl_cashback_bonuses for cashbacks
        $cb_sql = "SELECT name, 'cashback' as type, end_at, bonus_category, 0 as bonus_amount, 0 as wagering_required, 0 as wagering_completed,
                          title, description, 'Standard platform terms apply.' as terms_conditions
                   FROM tbl_cashback_bonuses
                   WHERE id = $active_bonus_id LIMIT 1";
        $cb_res = mysqli_query($conn, $cb_sql);
        if($cb_row = mysqli_fetch_assoc($cb_res)) {
            $cb_row['current_remaining_wagering'] = 0;
            $cb_row['current_bonus_balance'] = 0;
            $cb_row['real_balance'] = (float)$user_data['tbl_balance'];
            $cb_row['redemption_status'] = 'active';
            $cb_row['claim_date'] = date('Y-m-d H:i:s');

            $resArr['status'] = "success";
            $resArr['status_code'] = "success";
            $resArr['data'] = $cb_row;
        } else {
            $resArr['status_code'] = "redemption_not_found";
            $resArr['message'] = "Active bonus record found in profile but missing in redemptions history.";
        }
    }
}

echo json_encode($resArr);
return;
?>
