<?php
ob_start();
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] == '1';
$edit_id = $is_edit ? (int)$_POST['edit_id'] : 0;

$name = mysqli_real_escape_string($conn, $_POST['name']);
$title = mysqli_real_escape_string($conn, $_POST['title']);
$coupon_code = mysqli_real_escape_string($conn, $_POST['coupon_code']);
$status = mysqli_real_escape_string($conn, $_POST['status']);
$min_loss = (float)$_POST['min_loss'];
$percentage = (float)$_POST['percentage'];
$max_cashback = (float)$_POST['max_cashback'];
$claim_mode = mysqli_real_escape_string($conn, $_POST['claim_mode'] ?? 'automatic');
$bonus_category = 'all';

$start_at = $_POST['start_date'] ? ($_POST['start_date'] . ' ' . ($_POST['start_time'] ?: '00:00:00')) : '0000-00-00 00:00:00';
$end_at = $_POST['end_date'] ? ($_POST['end_date'] . ' ' . ($_POST['end_time'] ?: '23:59:59')) : '0000-00-00 00:00:00';

$wagering_multiplier = (float)$_POST['wagering_multiplier'];
$max_profit = (float)$_POST['max_profit'];
$target_user_id = mysqli_real_escape_string($conn, $_POST['target_user_id'] ?? '');

// Handle Redemption Pattern (Sun-Sat)
$pattern = [];
if (isset($_POST['pattern_days']) && is_array($_POST['pattern_days'])) {
    foreach ($_POST['pattern_days'] as $day) {
        $pattern[$day] = [
            'start' => $_POST['pattern_start'][$day] ?? '00:00',
            'end' => $_POST['pattern_end'][$day] ?? '23:59'
        ];
    }
}
$redemption_pattern = mysqli_real_escape_string($conn, json_encode($pattern));

// --- IMAGE UPLOAD LOGIC ---
$image_path = $_POST['existing_image'] ?? '';

// Check for Base64 Upload (Most Reliable)
if (!empty($_POST['cb_image_base64'])) {
    $base_dir = dirname(__DIR__, 3) . '/uploads/promotions/';
    if (!is_dir($base_dir)) mkdir($base_dir, 0777, true);
    
    $data = $_POST['cb_image_base64'];
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png etc
        $data = base64_decode($data);
        
        $file_name = 'cb_' . time() . '_' . rand(1000, 9999) . '.' . $type;
        $target_file = $base_dir . $file_name;
        
        if (file_put_contents($target_file, $data)) {
            $image_path = 'uploads/promotions/' . $file_name;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save base64 image. Check folder permissions.']);
            exit;
        }
    }
} 
// Fallback to traditional upload if base64 is not provided
else if (isset($_FILES['cb_image']) && $_FILES['cb_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = dirname(__DIR__, 3) . '/uploads/promotions/';
    
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = pathinfo($_FILES['cb_image']['name'], PATHINFO_EXTENSION);
    $file_name = 'cb_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['cb_image']['tmp_name'], $target_file)) {
        chmod($target_file, 0777); 
        $image_path = 'uploads/promotions/' . $file_name;
    }
} elseif (isset($_FILES['cb_image']) && $_FILES['cb_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error code: ' . $_FILES['cb_image']['error']]);
    exit;
}

if ($is_edit) {
    $sql = "UPDATE tbl_cashback_bonuses SET 
            image_path = '$image_path',
            name = '$name', 
            title = '$title',
            coupon_code = '$coupon_code',
            status = '$status',
            min_loss = $min_loss, 
            percentage = $percentage, 
            max_cashback = $max_cashback,
            bonus_category = '$bonus_category',
            start_at = '$start_at',
            end_at = '$end_at',
            redemption_pattern = '$redemption_pattern',
            wagering_multiplier = $wagering_multiplier,
            max_win_limit = $max_profit,
            claim_mode = '$claim_mode',
            target_user_id = '$target_user_id'
            WHERE id = $edit_id";
} else {
    $sql = "INSERT INTO tbl_cashback_bonuses 
        (image_path, name, title, coupon_code, status, min_loss, percentage, max_cashback, bonus_category, start_at, end_at, redemption_pattern, wagering_multiplier, max_win_limit, claim_mode, target_user_id) 
        VALUES 
        ('$image_path', '$name', '$title', '$coupon_code', '$status', $min_loss, $percentage, $max_cashback, '$bonus_category', '$start_at', '$end_at', '$redemption_pattern', $wagering_multiplier, $max_profit, '$claim_mode', '$target_user_id')";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Cashback promotion saved successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}
?>
