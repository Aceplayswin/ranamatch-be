<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include __DIR__ . '/../security/config.php';

// Auto-heal DB tables
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tbl_agent_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` text DEFAULT NULL,
  `action_url` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'true',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tbl_affiliate_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` text DEFAULT NULL,
  `action_url` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'true',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$settings = [];
$res = mysqli_query($conn, "SELECT * FROM tblservices");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['tbl_service_name']] = $row['tbl_service_value'];
}

$agent_site_name = $settings['AGENT_SITE_NAME'] ?? 'VELPLAY Agent';
$affiliate_site_name = $settings['AFFILIATE_SITE_NAME'] ?? 'VELPLAY Affiliate';

$agent_banners = [];
$resAgent = mysqli_query($conn, "SELECT id, image_path, action_url FROM tbl_agent_banners WHERE status='true' ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($resAgent)) {
    $agent_banners[] = [
        "id" => (int)$row['id'],
        "image_path" => $row['image_path'],
        "action_url" => $row['action_url']
    ];
}

$affiliate_banners = [];
$resAff = mysqli_query($conn, "SELECT id, image_path, action_url FROM tbl_affiliate_banners WHERE status='true' ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($resAff)) {
    $affiliate_banners[] = [
        "id" => (int)$row['id'],
        "image_path" => $row['image_path'],
        "action_url" => $row['action_url']
    ];
}

echo json_encode([
    "status_code" => "true",
    "agent_site_name" => $agent_site_name,
    "affiliate_site_name" => $affiliate_site_name,
    "agent_banners" => $agent_banners,
    "affiliate_banners" => $affiliate_banners
]);
exit();
?>
