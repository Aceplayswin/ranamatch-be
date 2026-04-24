<?php
ob_start();
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
ob_end_clean();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] == '1';
$edit_id = $is_edit ? (int)$_POST['edit_id'] : 0;

function toBool($val) {
    if (!isset($val) || $val === null) return 0;
    return (strtolower($val) === 'yes') ? 1 : 0;
}

function getPost($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Step 1 fields
$name = mysqli_real_escape_string($conn, getPost('name'));
$type = mysqli_real_escape_string($conn, getPost('type'));
$coupon_code = mysqli_real_escape_string($conn, getPost('coupon_code'));
$target_user_id = mysqli_real_escape_string($conn, getPost('target_user_id'));
$is_published = toBool(getPost('is_published', 'yes'));
$is_public = toBool(getPost('is_public', 'yes'));
$comment = mysqli_real_escape_string($conn, getPost('comment'));

// Step 2 fields
$redemption_type = mysqli_real_escape_string($conn, getPost('redemption_type'));
$amount = (float)getPost('redemption_amount', 0);
$max_redeem_value = (float)getPost('max_redeem_value', 0);
$min_deposit = (float)getPost('min_deposit', 0);
$payment_methods = mysqli_real_escape_string($conn, getPost('payment_methods', 'all'));
$bonus_category = mysqli_real_escape_string($conn, getPost('bonus_category'));

$is_first_deposit = toBool(getPost('is_first_deposit', 'no'));
$is_second_deposit = toBool(getPost('is_second_deposit', 'no'));
$is_third_deposit = toBool(getPost('is_third_deposit', 'no'));
$is_new_player_only = toBool(getPost('is_new_player_only', 'no'));
$is_auto_redeem = toBool(getPost('is_auto_redeem', 'no'));
$allow_download = toBool(getPost('allow_download', 'yes'));
$allow_instant = toBool(getPost('allow_instant', 'yes'));
$allow_mobile = toBool(getPost('allow_mobile', 'yes'));

$start_date = getPost('start_date', date('Y-m-d'));
$start_time = getPost('start_time', '00:00');
$end_date = getPost('end_date', date('Y-m-d', strtotime('+30 days')));
$end_time = getPost('end_time', '23:59');
$start_at = $start_date . ' ' . $start_time;
$end_at = $end_date . ' ' . $end_time;

$player_limit_type = mysqli_real_escape_string($conn, getPost('player_limit_type', 'daily'));
$limit_daily = (int)getPost('limit_daily', 0);
$limit_weekly = (int)getPost('limit_weekly', 0);
$limit_monthly = (int)getPost('limit_monthly', 0);
$limit_total = (int)getPost('limit_total', 0);

// Server-side enforcement of type constraints
if ($type === 'single_account') {
    $limit_total = 1;
}
if ($type === 'redeposit_bonus') {
    $is_first_deposit = 0;
}

// Redemption pattern JSON
$pattern = [];
if (isset($_POST['pattern_days']) && is_array($_POST['pattern_days'])) {
    foreach ($_POST['pattern_days'] as $day) {
        $pattern[$day] = [
            'start' => isset($_POST['pattern_start'][$day]) ? $_POST['pattern_start'][$day] : '',
            'end' => isset($_POST['pattern_end'][$day]) ? $_POST['pattern_end'][$day] : ''
        ];
    }
}
$redemption_pattern = mysqli_real_escape_string($conn, json_encode($pattern));

mysqli_begin_transaction($conn);

try {
    if ($is_edit) {
        $sql = "UPDATE tbl_bonuses SET 
                name='$name', type='$type', coupon_code='$coupon_code', status='active', is_published=$is_published, 
                target_user_id='$target_user_id',
                is_public=$is_public, comment='$comment', redemption_type='$redemption_type', amount=$amount, 
                bonus_category='$bonus_category', payment_methods='$payment_methods', min_deposit=$min_deposit, 
                max_redeem_value=$max_redeem_value, is_first_deposit=$is_first_deposit, is_second_deposit=$is_second_deposit, 
                is_third_deposit=$is_third_deposit, is_new_player_only=$is_new_player_only, is_auto_redeem=$is_auto_redeem, 
                allow_download=$allow_download, allow_instant=$allow_instant, allow_mobile=$allow_mobile, 
                start_at='$start_at', end_at='$end_at', player_limit_type='$player_limit_type', 
                limit_daily=$limit_daily, limit_weekly=$limit_weekly, limit_monthly=$limit_monthly, 
                limit_total=$limit_total, redemption_pattern='$redemption_pattern'
                WHERE id = $edit_id";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
        $bonus_id = $edit_id;
    } else {
        $sql = "INSERT INTO tbl_bonuses (
                name, type, coupon_code, status, is_published, is_public, target_user_id, comment, redemption_type, amount, 
                bonus_category, payment_methods, min_deposit, max_redeem_value, is_first_deposit, is_second_deposit, 
                is_third_deposit, is_new_player_only, is_auto_redeem, allow_download, allow_instant, allow_mobile, 
                start_at, end_at, player_limit_type, limit_daily, limit_weekly, limit_monthly, limit_total, 
                redemption_pattern
                ) VALUES (
                '$name', '$type', '$coupon_code', 'active', $is_published, $is_public, '$target_user_id', '$comment', '$redemption_type', 
                $amount, '$bonus_category', '$payment_methods', $min_deposit, $max_redeem_value, $is_first_deposit, 
                $is_second_deposit, $is_third_deposit, $is_new_player_only, $is_auto_redeem, $allow_download, 
                $allow_instant, $allow_mobile, '$start_at', '$end_at', '$player_limit_type', $limit_daily, 
                $limit_weekly, $limit_monthly, $limit_total, '$redemption_pattern')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
        $bonus_id = mysqli_insert_id($conn);
    }

    // Step 3: Content
    $lang_code = mysqli_real_escape_string($conn, getPost('lang_code', 'en'));
    $title = mysqli_real_escape_string($conn, getPost('title'));
    $description = mysqli_real_escape_string($conn, getPost('description'));
    $terms = mysqli_real_escape_string($conn, getPost('terms'));
    
    $image_url = getPost('existing_image', '');
    if (isset($_FILES['bonus_image']) && $_FILES['bonus_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/bonuses/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_ext = pathinfo($_FILES['bonus_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['bonus_image']['tmp_name'], $upload_dir . $file_name)) {
            $image_url = 'uploads/bonuses/' . $file_name;
        }
    }

    mysqli_query($conn, "DELETE FROM tbl_bonus_content WHERE bonus_id = $bonus_id");
    $sql_content = "INSERT INTO tbl_bonus_content (bonus_id, lang_code, title, description, image_path, terms_conditions) 
                    VALUES ($bonus_id, '$lang_code', '$title', '$description', '$image_url', '$terms')";
    $result = mysqli_query($conn, $sql_content);
    if (!$result) {
        throw new Exception('Content: ' . mysqli_error($conn));
    }

    // Step 4: Abuse Control
    $similarity_percent = (int)getPost('similarity_percent', 30);
    $exclude_deposited_played_days = (int)getPost('exclude_deposited_played_days', 3);
    $exclude_played_days = (int)getPost('exclude_played_days', 0);

    mysqli_query($conn, "DELETE FROM tbl_bonus_abuse WHERE bonus_id = $bonus_id");
    $sql_abuse = "INSERT INTO tbl_bonus_abuse (bonus_id, similarity_threshold_percent, exclude_days_deposited_played, exclude_days_played) 
                  VALUES ($bonus_id, $similarity_percent, $exclude_deposited_played_days, $exclude_played_days)";
    $result = mysqli_query($conn, $sql_abuse);
    if (!$result) {
        throw new Exception('Abuse: ' . mysqli_error($conn));
    }

    // Step 5: Providers / Wagering
    mysqli_query($conn, "DELETE FROM tbl_bonus_providers WHERE bonus_id = $bonus_id");
    if (isset($_POST['wagering_enabled']) && is_array($_POST['wagering_enabled'])) {
        foreach ($_POST['wagering_enabled'] as $p_name => $enabled) {
            $p_name_esc = mysqli_real_escape_string($conn, $p_name);
            $is_enabled = ($enabled === 'yes') ? 1 : 0;
            $multiplier = isset($_POST['multiplier'][$p_name]) ? (float)$_POST['multiplier'][$p_name] : 0;
            $sql_provider = "INSERT INTO tbl_bonus_providers (bonus_id, provider_name, eligible_percent, wagering_multiplier, is_wagering_enabled) 
                            VALUES ($bonus_id, '$p_name_esc', 100, $multiplier, $is_enabled)";
            $result = mysqli_query($conn, $sql_provider);
            if (!$result) {
                throw new Exception('Provider: ' . mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Bonus saved successfully']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
