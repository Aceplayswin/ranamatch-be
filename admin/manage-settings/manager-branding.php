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

if ($action == "update_logo") {
    $db_path = "";
    $upload_dir = "../uploads/branding/";
    
    // Check for cropped data (base64)
    if (!empty($_POST['cropped_data'])) {
        $data = $_POST['cropped_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, etc
            $data = base64_decode($data);
            $filename = "logo_" . time() . ".png"; // Use png for transparency
            if (file_put_contents($upload_dir . $filename, $data)) {
                if (@getimagesize($upload_dir . $filename)) {
                    $db_path = "admin/uploads/branding/" . $filename;
                } else {
                    unlink($upload_dir . $filename);
                }
            }
        }
    } 
    // Fallback to standard upload
    else if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (@getimagesize($_FILES['site_logo']['tmp_name'])) {
                $filename = "logo_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_dir . $filename)) {
                    $db_path = "admin/uploads/branding/" . $filename;
                }
            }
        }
    }

    if (!empty($db_path)) {
        upsert_service($conn, 'SITE_LOGO_URL', $db_path);
        header("Location: site-branding.php?msg=Logo updated successfully&v=" . time());
    } else {
        $error_code = $_FILES['site_logo']['error'] ?? 'No file OR empty cropped data';
        header("Location: site-branding.php?err=Upload failed (Code: $error_code). Please try again.&v=" . time());
    }
}

else if ($action == "update_contacts") {
    $tg = $_POST['telegram_url'];
    $wa = $_POST['whatsapp_num'];
    $sup = $_POST['support_url'];

    upsert_service($conn, 'TELEGRAM_URL', $tg);
    upsert_service($conn, 'CONTACT_WHATSAPP', $wa);
    upsert_service($conn, 'CONTACT_SUPPORT_URL', $sup);

    header("Location: site-branding.php?msg=Contacts updated successfully&v=" . time());
}

else if ($action == "update_site_texts") {
    $address = mysqli_real_escape_string($conn, $_POST['site_address']);
    $tagline = mysqli_real_escape_string($conn, $_POST['site_tagline']);
    $marquee = mysqli_real_escape_string($conn, $_POST['site_marquee']);
    $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);

    upsert_service($conn, 'SITE_ADDRESS', $address);
    upsert_service($conn, 'SITE_TAGLINE', $tagline);
    upsert_service($conn, 'SITE_MARQUEE', $marquee);
    upsert_service($conn, 'SITE_NAME', $site_name);
    
    header("Location: site-branding.php?msg=Site texts updated&v=" . time());
}

else if ($action == "update_social_links") {
    $platforms = $_POST['platforms'] ?? [];
    $urls = $_POST['urls'] ?? [];
    
    $links = [];
    for($i = 0; $i < count($platforms); $i++) {
        if(!empty($platforms[$i]) && !empty($urls[$i])) {
            $links[] = [
                'platform' => $platforms[$i],
                'value' => $urls[$i]
            ];
        }
    }
    
    $json_links = json_encode($links);
    upsert_service($conn, 'SITE_SOCIAL_LINKS', $json_links);
    
    header("Location: site-branding.php?msg=Social links updated&v=" . time());
}

else if ($action == "add_slider") {
    $upload_dir = "../uploads/branding/";
    $url = mysqli_real_escape_string($conn, $_POST['action_url'] ?? '');
    $success_count = 0;

    // Handle Cropped Single Upload (Priority)
    if (!empty($_POST['cropped_data'])) {
        $data = $_POST['cropped_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $data = base64_decode($data);
            $filename = "slider_" . time() . ".jpg";
            if (file_put_contents($upload_dir . $filename, $data)) {
                $db_path = "admin/uploads/branding/" . $filename;
                mysqli_query($conn, "INSERT INTO tblsliders (tbl_slider_img, tbl_slider_action, tbl_slider_status) VALUES ('$db_path', '$url', 'true')");
                $success_count++;
            }
        }
    } 
    // Handle Bulk Upload
    else if (isset($_FILES['banner_imgs'])) {
        $files = $_FILES['banner_imgs'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] == 0) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $filename = "slider_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                        $db_path = "admin/uploads/branding/" . $filename;
                        mysqli_query($conn, "INSERT INTO tblsliders (tbl_slider_img, tbl_slider_action, tbl_slider_status) VALUES ('$db_path', '$url', 'true')");
                        $success_count++;
                    }
                }
            }
        }
    }

    if ($success_count > 0) {
        header("Location: site-branding.php?msg=$success_count Slider(s) added successfully&v=" . time());
    } else {
        header("Location: site-branding.php?err=Upload failed. No valid images selected.&v=" . time());
    }
}

else if ($action == "add_promo") {
    $upload_dir = "../uploads/branding/";
    $url = mysqli_real_escape_string($conn, $_POST['action_url'] ?? '');
    $success_count = 0;

    if (!empty($_POST['cropped_data'])) {
        $data = $_POST['cropped_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $data = base64_decode($data);
            $filename = "promo_" . time() . ".jpg";
            if (file_put_contents($upload_dir . $filename, $data)) {
                $db_path = "admin/uploads/branding/" . $filename;
                mysqli_query($conn, "INSERT INTO tbl_promotions (image_path, action_url, status) VALUES ('$db_path', '$url', 'true')");
                $success_count++;
            }
        }
    } 
    else if (isset($_FILES['promo_imgs'])) {
        $files = $_FILES['promo_imgs'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] == 0) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $filename = "promo_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                        $db_path = "admin/uploads/branding/" . $filename;
                        mysqli_query($conn, "INSERT INTO tbl_promotions (image_path, action_url, status) VALUES ('$db_path', '$url', 'true')");
                        $success_count++;
                    }
                }
            }
        }
    }

    if ($success_count > 0) {
        header("Location: site-branding.php?msg=$success_count Promo(s) added successfully&v=" . time());
    } else {
        header("Location: site-branding.php?err=Upload failed. No valid images selected.&v=" . time());
    }
}

else if ($action == "delete_asset") {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    if ($type == "slider") {
        mysqli_query($conn, "DELETE FROM tblsliders WHERE id = $id");
    } else if ($type == "promo") {
        mysqli_query($conn, "DELETE FROM tbl_promotions WHERE id = $id");
    }
    header("Location: site-branding.php?msg=Asset deleted&v=" . time());
}

else if ($action == "bulk_delete_assets") {
    $type = $_POST['type'];
    $ids = mysqli_real_escape_string($conn, $_POST['ids']);
    
    if (!empty($ids)) {
        if ($type == "slider") {
            mysqli_query($conn, "DELETE FROM tblsliders WHERE id IN ($ids)");
        } else if ($type == "promo") {
            mysqli_query($conn, "DELETE FROM tbl_promotions WHERE id IN ($ids)");
        }
        header("Location: site-branding.php?msg=Selected assets deleted successfully&v=" . time());
    } else {
        header("Location: site-branding.php?err=No assets selected for deletion&v=" . time());
    }
}

else if ($action == "update_theme") {
    $brand = $_POST['site_brand_color'];
    $grad_end = $_POST['site_brand_gradient_end'];
    $bg = $_POST['site_bg_color'];
    $text = $_POST['site_text_color'];

    upsert_service($conn, 'SITE_BRAND_COLOR', $brand);
    upsert_service($conn, 'SITE_BRAND_GRADIENT_END', $grad_end);
    upsert_service($conn, 'SITE_BG_COLOR', $bg);
    upsert_service($conn, 'SITE_TEXT_COLOR', $text);

    header("Location: site-branding.php?msg=Theme updated successfully&v=" . time());
}

else if ($action == "reset_theme") {
    mysqli_query($conn, "DELETE FROM tblservices WHERE tbl_service_name IN ('SITE_BRAND_COLOR', 'SITE_BRAND_GRADIENT_END', 'SITE_BG_COLOR', 'SITE_TEXT_COLOR')");
    header("Location: site-branding.php?msg=Theme reset to defaults&v=" . time());
}
?>
