<?php
ob_start();
if (!isset($conn)) {
    define('ACCESS_SECURITY', 'true');
    require_once __DIR__ . '/../security/config.php';
}
ob_end_clean();

$resArr['status'] = "success";
$resArr['bonus'] = null;

$bonus_id = isset($_GET['bonus_id']) ? (int)$_GET['bonus_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'standard';

if ($bonus_id > 0) {
    if ($type === 'cashback') {
        $sql = "SELECT id, 'cashback' as type, title, name as description, image_path, bonus_category as category, end_at as end_date, 0 as min_deposit, 0 as amount, 'cashback' as redemption_type 
                FROM tbl_cashback_bonuses 
                WHERE id = $bonus_id AND status = 'active'";
    } else {
        $sql = "SELECT b.id, b.type, b.amount, b.bonus_category as category, c.title, c.description, c.image_path, c.terms_conditions, b.end_at as end_date, b.min_deposit, b.amount, b.redemption_type, b.type as bonus_type
                FROM tbl_bonuses b 
                JOIN tbl_bonus_content c ON b.id = c.bonus_id 
                WHERE b.id = $bonus_id AND b.is_published = 1 AND b.status = 'active' AND c.lang_code = 'en'";
    }

    $result = mysqli_query($conn, $sql);

    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $row['image_path'] = !empty($row['image_path']) ? $row['image_path'] : '';
        if ($type === 'cashback') {
            $row['terms_conditions'] = "Standard platform terms apply.";
            $row['providers'] = [];
        } else {
            // Fetch providers and wagering
            $providers = [];
            $p_sql = "SELECT provider_name, wagering_multiplier FROM tbl_bonus_providers WHERE bonus_id = $bonus_id AND is_wagering_enabled = 1";
            $p_res = mysqli_query($conn, $p_sql);
            while($p_row = mysqli_fetch_assoc($p_res)) {
                $providers[] = $p_row;
            }
            $row['providers'] = $providers;
        }
        $resArr["bonus"] = $row;
    } else {
        $resArr['status'] = "error";
        $resArr['message'] = "Bonus not found or inactive";
    }
} else {
    $resArr['status'] = "error";
    $resArr['message'] = "Invalid bonus ID";
}

echo json_encode($resArr);
?>
