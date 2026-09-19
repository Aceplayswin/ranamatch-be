<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()!="true" || $accessObj->isAllowed("access_settings")=="false"){
    header('location:../logout-account');
    exit;
}

// Auto-heal database tables
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

$action = $_POST['action_type'] ?? $_GET['action_type'] ?? '';

function upsert_service($conn, $name, $value) {
    $name = mysqli_real_escape_string($conn, $name);
    $value = mysqli_real_escape_string($conn, $value);
    $chk = mysqli_query($conn, "SELECT * FROM tblservices WHERE tbl_service_name='$name'");
    if(mysqli_num_rows($chk) > 0) {
        mysqli_query($conn, "UPDATE tblservices SET tbl_service_value='$value' WHERE tbl_service_name='$name'");
    } else {
        mysqli_query($conn, "INSERT INTO tblservices (tbl_service_name, tbl_service_value) VALUES ('$name', '$value')");
    }
}

if ($action == "update_texts") {
    $agent_name = mysqli_real_escape_string($conn, $_POST['agent_site_name'] ?? '');
    $affiliate_name = mysqli_real_escape_string($conn, $_POST['affiliate_site_name'] ?? '');

    upsert_service($conn, 'AGENT_SITE_NAME', $agent_name);
    upsert_service($conn, 'AFFILIATE_SITE_NAME', $affiliate_name);

    header("Location: agent-control.php?msg=Agent & Affiliate Panel names updated successfully&v=" . time());
}

else if ($action == "add_banner") {
    $target = $_POST['target_panel'] ?? 'agent'; // 'agent' or 'affiliate'
    $upload_dir = "../uploads/branding/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $url = mysqli_real_escape_string($conn, $_POST['action_url'] ?? '');
    $success_count = 0;
    $table = ($target === 'affiliate') ? 'tbl_affiliate_banners' : 'tbl_agent_banners';

    if (isset($_FILES['banner_imgs'])) {
        $files = $_FILES['banner_imgs'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] == 0) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $filename = $target . "_banner_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                        $db_path = "admin/uploads/branding/" . $filename;
                        mysqli_query($conn, "INSERT INTO $table (image_path, action_url, status) VALUES ('$db_path', '$url', 'true')");
                        $success_count++;
                    }
                }
            }
        }
    }

    if ($success_count > 0) {
        header("Location: agent-control.php?msg=$success_count Banner(s) added successfully for " . ucfirst($target) . " Panel&v=" . time());
    } else {
        header("Location: agent-control.php?err=Upload failed. Please select valid images.&v=" . time());
    }
}

else if ($action == "delete_banner") {
    $target = $_GET['target'] ?? 'agent';
    $id = (int)($_GET['id'] ?? 0);
    $table = ($target === 'affiliate') ? 'tbl_affiliate_banners' : 'tbl_agent_banners';

    if ($id > 0) {
        mysqli_query($conn, "DELETE FROM $table WHERE id = $id");
        header("Location: agent-control.php?msg=Banner deleted successfully&v=" . time());
    } else {
        header("Location: agent-control.php?err=Invalid Banner ID&v=" . time());
    }
}
?>
