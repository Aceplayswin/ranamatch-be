<?php
// Unified Promotion Logic for Root Environment
// included by the router, so $conn is already available.

$resArr['status'] = "success";
$resArr['promotions'] = [];

$user_id = isset($_GET['USER_ID']) ? mysqli_real_escape_string($conn, $_GET['USER_ID']) : "";

// 1. Fetch standard Bonuses
$sql = "SELECT b.id, b.type, b.bonus_category as category, 
               COALESCE(c.title, b.name) as title, 
               COALESCE(c.description, '') as description, 
               COALESCE(c.image_path, '') as image_path, 
               b.end_at as end_date 
        FROM tbl_bonuses b 
        LEFT JOIN tbl_bonus_content c ON b.id = c.bonus_id AND c.lang_code = 'en'
        WHERE b.status = 'active'
        AND (b.is_published = 1 OR b.is_published IS NULL)
        AND (b.target_user_id = '' OR b.target_user_id IS NULL OR FIND_IN_SET('$user_id', REPLACE(b.target_user_id, ' ', '')))";

$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $img = $row['image_path'];
        array_push($resArr["promotions"], array(
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "category" => $row['category'],
            "end_date" => $row['end_date'],
            "image_path" => $img,
            "promo_type" => 'standard',
            "action" => "/bonus-details/" . $row['id']
        ));
    }
}

// 2. Fetch Cashback Promotions
$sql_cb = "SELECT id, bonus_category as category, title, description, image_path, end_at as end_date 
           FROM tbl_cashback_bonuses 
           WHERE status = 'active'
           AND (target_user_id = '' OR target_user_id IS NULL OR FIND_IN_SET('$user_id', REPLACE(target_user_id, ' ', '')))";

$result_cb = mysqli_query($conn, $sql_cb);
if($result_cb) {
    while($row = mysqli_fetch_assoc($result_cb)) {
        $img = $row['image_path'];
        array_push($resArr["promotions"], array(
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "category" => $row['category'],
            "end_date" => $row['end_date'],
            "image_path" => $img,
            "promo_type" => 'cashback',
            "action" => "/bonus-details/" . $row['id'] . "?type=cashback"
        ));
    }
}

// 3. Fetch General Offers from tbl_offer_promotions
$sql_offers = "SELECT id, title, description, category, end_date, image_path 
               FROM tbl_offer_promotions 
               WHERE status = 'active'";

$result_offers = mysqli_query($conn, $sql_offers);
if($result_offers) {
    while($row = mysqli_fetch_assoc($result_offers)) {
        $img = $row['image_path'];
        array_push($resArr["promotions"], array(
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "category" => $row['category'],
            "end_date" => $row['end_date'],
            "image_path" => $img,
            "promo_type" => 'offer',
            "action" => "#"
        ));
    }
}

echo json_encode($resArr);
return;
