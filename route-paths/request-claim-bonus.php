<?php
// API to claim a bonus and update the user's wallet
// Included by the router which already provides $conn and $resArr
if (!isset($conn)) {
    define('ACCESS_SECURITY', 'true');
    require_once __DIR__ . '/../security/config.php';
}

$resArr['status'] = "failed";
$resArr['status_code'] = "unknown_error";
$resArr['message'] = "unknown_error";
$resArr['data'] = [];
$resArr['noticeArr'] = [];

$user_id = isset($_GET['USER_ID']) ? mysqli_real_escape_string($conn, $_GET['USER_ID']) : "";
$bonus_id = isset($_GET['bonus_id']) ? (int)$_GET['bonus_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'standard';
$secret_key = isset($headerObj) ? $headerObj->getAuthorization() : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : "");

if ($user_id == "" || $bonus_id == 0 || $secret_key == "") {
    $resArr['status_code'] = "invalid_parameters";
    $resArr['message'] = "invalid_parameters";
    echo json_encode($resArr);
    return;
}

// 1. Verify User
$user_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='$user_id' AND tbl_auth_secret='$secret_key'";
$user_res = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_res) == 0) {
    $resArr['status_code'] = "authorization_failed";
    $resArr['message'] = "authorization_failed";
    echo json_encode($resArr);
    return;
}

$user_row = mysqli_fetch_assoc($user_res);
$active_id    = (int)($user_row['tbl_active_bonus_id'] ?? 0);
$required_play = (float)($user_row['tbl_requiredplay_balance'] ?? 0);

// Only block if there is a GENUINE active bonus (ID > 0 AND wagering > 0)
// If ID is 0 but wagering > 0, it's a ghost lock - we'll heal it automatically.
if ($active_id > 0 && $required_play > 0) {
    $resArr['status_code'] = "active_bonus_exists";
    $resArr['message'] = "Already_have_active_bonus_complete_wagering_first";
    echo json_encode($resArr);
    return;
}

// 2. Verify Bonus / Cashback
if ($type === 'cashback') {
    $bonus_sql = "SELECT id, status FROM tbl_cashback_bonuses WHERE id=$bonus_id";
    $bonus_res = mysqli_query($conn, $bonus_sql);
    
    if (mysqli_num_rows($bonus_res) == 0) {
        $resArr['status_code'] = "promotion_not_found";
        $resArr['message'] = "promotion_not_found";
        echo json_encode($resArr);
        return;
    }
    
    $bonus_data = mysqli_fetch_assoc($bonus_res);
    if ($bonus_data['status'] != 'active') {
        $resArr['status_code'] = "promotion_inactive";
        $resArr['message'] = "promotion_inactive";
        echo json_encode($resArr);
        return;
    }
    
    // Check if already claimed/enrolled
    $check_sql = "SELECT id FROM tbl_bonus_redemptions WHERE user_id='$user_id' AND bonus_id=$bonus_id AND status IN ('active', 'completed')";
    $check_res = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_res) > 0) {
        $resArr['status_code'] = "already_claimed";
        $resArr['message'] = "already_claimed";
        echo json_encode($resArr);
        return;
    }

    mysqli_begin_transaction($conn);
    try {
        $ins = "INSERT INTO tbl_bonus_redemptions (user_id, bonus_id, bonus_amount, wagering_required, wagering_completed, status, created_at, updated_at) VALUES ('$user_id', $bonus_id, 0, 0, 0, 'active', NOW(), NOW())";
        if (!mysqli_query($conn, $ins)) throw new Exception("Insert Err");

        $up = "UPDATE tblusersdata SET tbl_active_bonus_id = $bonus_id WHERE tbl_uniq_id = '$user_id'";
        if (!mysqli_query($conn, $up)) throw new Exception("Update Err");

        mysqli_commit($conn);
        $resArr['status'] = "success";
        $resArr['status_code'] = "success";
        $resArr['message'] = "success";
        $resArr['new_balance'] = 0; // Cashback is paid out via cron
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $resArr['status'] = "failed";
        $resArr['status_code'] = "transaction_failed";
        $resArr['message'] = "transaction_failed";
    }
    echo json_encode($resArr);
    return;
}

$bonus_sql = "SELECT id, amount, status, type, is_published, is_first_deposit, is_second_deposit, is_third_deposit, min_deposit, end_at, limit_total, bonus_category, target_user_id FROM tbl_bonuses WHERE id=$bonus_id";
$bonus_res = mysqli_query($conn, $bonus_sql);

if (mysqli_num_rows($bonus_res) == 0) {
    $resArr['status_code'] = "bonus_not_found";
    $resArr['message'] = "bonus_not_found";
    echo json_encode($resArr);
    return;
}

$bonus_data = mysqli_fetch_assoc($bonus_res);
if ($bonus_data['is_published'] == 0 || $bonus_data['status'] != 'active') {
    $resArr['status_code'] = "bonus_inactive";
    $resArr['message'] = "bonus_inactive";
    echo json_encode($resArr);
    exit;
}

// Check expiration
if (strtotime($bonus_data['end_at']) < time()) {
    $resArr['status_code'] = "bonus_expired";
    $resArr['message'] = "bonus_expired";
    echo json_encode($resArr);
    exit;
}

// 2.2 Verify Target User (VIP Lock)
if ($bonus_data['target_user_id'] != "" && $bonus_data['target_user_id'] != $user_id) {
    $resArr['status_code'] = "unauthorized_for_this_bonus";
    $resArr['message'] = "unauthorized_for_this_bonus";
    echo json_encode($resArr);
    return;
}

// 2.5 Verify Deposit Milestones (New Check)
$recharge_count_sql = "SELECT COUNT(id) as total FROM tblusersrecharge WHERE tbl_user_id='$user_id' AND tbl_request_status='success'";
$recharge_count_res = mysqli_query($conn, $recharge_count_sql);
$recharge_count = 0;
if ($recharge_row = mysqli_fetch_assoc($recharge_count_res)) {
    $recharge_count = (int)$recharge_row['total'];
}

// Check First Deposit Rule
if ($bonus_data['is_first_deposit'] == 'yes' && $recharge_count > 0) {
    $resArr['status_code'] = "eligible_only_for_first_deposit";
    $resArr['message'] = "eligible_only_for_first_deposit";
    echo json_encode($resArr);
    return;
}

// Check Second Deposit Rule
if ($bonus_data['is_second_deposit'] == 'yes' && $recharge_count != 1) {
    $resArr['status_code'] = "eligible_only_for_second_deposit";
    $resArr['message'] = "eligible_only_for_second_deposit";
    echo json_encode($resArr);
    return;
}

// Check Third Deposit Rule
if ($bonus_data['is_third_deposit'] == 'yes' && $recharge_count != 2) {
    $resArr['status_code'] = "eligible_only_for_third_deposit";
    $resArr['message'] = "eligible_only_for_third_deposit";
    echo json_encode($resArr);
    return;
}

// 2.7 Verify Minimum Deposit Amount (New Check)
$min_deposit_required = (float)($bonus_data['min_deposit'] ?? 0);
if ($min_deposit_required > 0) {
    $check_min_dep_sql = "SELECT id FROM tblusersrecharge WHERE tbl_user_id='$user_id' AND tbl_request_status='success' AND tbl_recharge_amount >= $min_deposit_required LIMIT 1";
    $check_min_dep_res = mysqli_query($conn, $check_min_dep_sql);
    if (mysqli_num_rows($check_min_dep_res) == 0) {
        $resArr['status_code'] = "min_deposit_not_met";
        $resArr['message'] = "You_must_have_at_least_one_deposit_of_at_least_₹" . $min_deposit_required . "_to_claim_this_bonus";
        echo json_encode($resArr);
        return;
    }
}

    // --- 1. NEW DEPOSIT AUDITOR (Ratio Logic) ---
    // --- 1. NEW DEPOSIT AUDITOR (Ratio Logic) ---
    $total_recharges = $recharge_count; // Reuse the count fetched above

    // Count already claimed bonuses (Total History)
    $redemption_count_sql = "SELECT COUNT(id) AS total_redemptions FROM tbl_bonus_redemptions 
                             WHERE user_id = '$user_id'";
    $redemption_count_res = mysqli_query($conn, $redemption_count_sql);
    $redemption_count_data = mysqli_fetch_assoc($redemption_count_res);
    $total_redemptions = (int)$redemption_count_data['total_redemptions'];

    // --- 2. REDEPOSIT LOGIC (Strict: Minimum 2 Deposits Required) ---
    if ($bonus_data['type'] == 'redeposit_bonus' && $total_recharges < 2) {
        $resArr['status_code'] = "redeposit_required_to_claim";
        $resArr['message'] = "redeposit_required_to_claim";
        echo json_encode($resArr);
        return;
    }


// --- 1. DUPLICATE CLAIM CHECK (ONLY BLOCKS ACTIVE/COMPLETED) ---
$check_sql = "SELECT id FROM tbl_bonus_redemptions WHERE user_id='$user_id' AND bonus_id=$bonus_id AND status IN ('active', 'completed')";
$check_res = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_res) > 0) {
    $resArr['status_code'] = "already_claimed";
    $resArr['message'] = "already_claimed";
    echo json_encode($resArr);
    return;
}

    // --- 3. GLOBAL ALLOWANCE CHECK (STRICT 1:1 RATIO) ---
    // No more bonuses allowed if you've already used all your recharge credits
    // Bypass this check for 'mass' and 'single_account' as per user request
    $bypass_ratio_types = ['mass', 'single_account'];
    if (!in_array($bonus_data['type'], $bypass_ratio_types)) {
        if ($total_redemptions >= $total_recharges) {
            $resArr['status_code'] = "new_deposit_required_to_claim";
            $resArr['message'] = "new_deposit_required_to_claim";
            echo json_encode($resArr);
            return;
        }
    }
    
    // Get the latest recharge ID for logging (optional but good for tracking)
    $latest_recharge_sql = "SELECT id FROM tblusersrecharge 
                            WHERE tbl_user_id = '$user_id' AND tbl_request_status = 'success' 
                            ORDER BY id DESC LIMIT 1";
    $latest_recharge_res = mysqli_query($conn, $latest_recharge_sql);
    $recharge_data = mysqli_fetch_assoc($latest_recharge_res);
    $recharge_id = $recharge_data ? $recharge_data['id'] : 0;

// 3.5. Check Global Limit (Enforce Single Account / Max Redemptions)
$limit_total = (int)$bonus_data['limit_total'];
if ($limit_total > 0) {
    $count_sql = "SELECT COUNT(id) AS total FROM tbl_bonus_redemptions WHERE bonus_id=$bonus_id";
    $count_res = mysqli_query($conn, $count_sql);
    $count_data = mysqli_fetch_assoc($count_res);
    $total_claimed = (int)$count_data['total'];
    
    if ($total_claimed >= $limit_total) {
        $resArr['status_code'] = "bonus_exhausted";
        $resArr['message'] = "bonus_exhausted";
        echo json_encode($resArr);
        return;
    }
}

// 4. Perform the Claim (Transaction)
mysqli_begin_transaction($conn);

try {
    $total_pool_amount = (float)$bonus_data['amount'];
    $limit_total = (int)$bonus_data['limit_total'];
    
    // Calculate Final Reward: Total Pool / Max Coupons (Winners)
    $final_reward_amount = ($limit_total > 0) ? ($total_pool_amount / $limit_total) : $total_pool_amount;
    
    // Fetch Wagering Multiplier from tbl_bonus_providers
    $rule_sql = "SELECT wagering_multiplier FROM tbl_bonus_providers WHERE bonus_id = $bonus_id AND is_wagering_enabled = 1 LIMIT 1";
    $rule_res = mysqli_query($conn, $rule_sql);
    $wagering_multiplier = 0;
    if ($rule_row = mysqli_fetch_assoc($rule_res)) {
        $wagering_multiplier = (float)$rule_row['wagering_multiplier'];
    }
    // Determine which balance to credit based on category
    $category = $bonus_data['bonus_category'] ?? 'casino';
    $balance_field = ($category === 'sports') ? 'tbl_sports_bonus' : 'tbl_bonus_balance';
    $is_locked = 1;

    if ($wagering_multiplier == 0) {
        $balance_field = 'tbl_balance';
        $is_locked = 0;
    }
    
    // Calculate turnover requirement based on the INDIVIDUAL reward
    $turnover_requirement = $final_reward_amount * $wagering_multiplier;
    
    // Update Wallet and turnover requirement (Assignment instead of Addition)
    $update_sql = "UPDATE tblusersdata SET 
                   $balance_field = $balance_field + $final_reward_amount, 
                   tbl_requiredplay_balance = $turnover_requirement,
                   tbl_active_bonus_id = $bonus_id,
                   tbl_is_bonus_locked = $is_locked
                   WHERE tbl_uniq_id='$user_id'";
    if (!mysqli_query($conn, $update_sql)) {
        throw new Exception("Update User Error: " . mysqli_error($conn));
    }
    
    // Log the Redemption (Log the split amount, not the total pool)
    $insert_sql = "INSERT INTO tbl_bonus_redemptions (user_id, bonus_id, bonus_amount, wagering_required, wagering_completed, status, created_at, updated_at) 
                   VALUES ('$user_id', $bonus_id, $final_reward_amount, $turnover_requirement, 0, 'active', NOW(), NOW())";
    if (!mysqli_query($conn, $insert_sql)) {
        throw new Exception("Insert Redemption Error: " . mysqli_error($conn));
    }
    
    /* 
    // --- 3. MARK RECHARGE AS USED (TICKET PUNCH) ---
    // Note: Column tbl_is_bonus_used does not exist in tblusersrecharge schema.
    if (isset($recharge_id) && $recharge_id > 0) {
        $mark_used_sql = "UPDATE tblusersrecharge SET tbl_is_bonus_used = 1 WHERE id = $recharge_id";
        mysqli_query($conn, $mark_used_sql);
    }
    */
    
    mysqli_commit($conn);
    $resArr['status'] = "success";
    $resArr['status_code'] = "success";
    $resArr['message'] = "success";
    $resArr['new_balance'] = $final_reward_amount;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $resArr['status'] = "failed";
    $resArr['status_code'] = "transaction_failed";
    $resArr['message'] = "transaction_failed";
}

echo json_encode($resArr);
?>
