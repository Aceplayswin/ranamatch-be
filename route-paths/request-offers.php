<?php
// API to fetch general promotions from tbl_offer_promotions
// Included by the router, so $conn is already available.

$resArr['status'] = "success";
$resArr['promotions'] = [];

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
// Detect base path from script location (e.g. /api/router/index.php → base is /api/)
$_script_dir = dirname(dirname($_SERVER['SCRIPT_NAME']));
$_base_path = ($_script_dir === '/' || $_script_dir === '') ? '/' : rtrim($_script_dir, '/') . '/';
$current_host = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_base_path;

// Fetch ONLY from tbl_promotions (Site Branding > Promotional Banners)
$sql = "SELECT id, image_path, action_url FROM tbl_promotions WHERE status = 'true' ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $img = $row['image_path'];

        array_push($resArr["promotions"], array(
            "id" => (int)$row['id'],
            "title" => "Promotion",
            "description" => "",
            "category" => "all",
            "end_date" => "",
            "image_path" => $img,
            "promo_type" => 'standard',
            "action" => $row['action_url'] ?: "#"
        ));
    }
}

echo json_encode($resArr);
return;
