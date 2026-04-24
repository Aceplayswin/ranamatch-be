<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_settings") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
    exit;
}

// [SELF-HEALING] Database Migrations
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tbl_promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` text DEFAULT NULL,
  `action_url` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'true',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tblsliders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tbl_slider_img` text DEFAULT NULL,
  `tbl_slider_action` text DEFAULT NULL,
  `tbl_slider_status` varchar(20) DEFAULT 'true',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Fetch current values
$settings = [];
$res = mysqli_query($conn, "SELECT * FROM tblservices");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['tbl_service_name']] = $row['tbl_service_value'];
}

$logo_url = $settings['SITE_LOGO_URL'] ?? 'wincologo.png';
$telegram_url = $settings['TELEGRAM_URL'] ?? '';
$whatsapp_num = $settings['CONTACT_WHATSAPP'] ?? '';
$support_url = $settings['CONTACT_SUPPORT_URL'] ?? '';

$site_address = $settings['SITE_ADDRESS'] ?? '';
$site_tagline = $settings['SITE_TAGLINE'] ?? '';
$site_marquee = $settings['SITE_MARQUEE'] ?? '';
$site_name = $settings['SITE_NAME'] ?? '';
$social_links_json = $settings['SITE_SOCIAL_LINKS'] ?? '[]';
$social_links = json_decode($social_links_json, true) ?: [];

// Theme Overrides
$site_brand_color = $settings['SITE_BRAND_COLOR'] ?? '#E6A000';
$site_brand_gradient_end = $settings['SITE_BRAND_GRADIENT_END'] ?? '#CC5A00';
$site_bg_color = $settings['SITE_BG_COLOR'] ?? '#0D0D0D';
$site_text_color = $settings['SITE_TEXT_COLOR'] ?? '#FFFFFF';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title>Site Branding & Assets | <?php echo $APP_NAME; ?></title>

    <!-- Custom Brand Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

    <style>
        :root {
            --brand: #E6A000;
            --brand-light: #F2C200;
            --brand-gradient: linear-gradient(135deg, #E6A000 0%, #CC5A00 100%);
            --app-bg: #0D0D0D;
            --panel-bg: #141414;
            --input-bg: #1A1A1A;
            --border-dim: rgba(255, 255, 255, 0.05);
            --text-main: #FFFFFF;
            --text-muted: #7A7A7A;
            --font-display: 'DM Sans', sans-serif;
            --font-ui: 'DM Sans', sans-serif;
            --font-head: 'DM Sans', sans-serif;
        }

        body {
            background-color: var(--app-bg) !important;
            font-family: var(--font-ui) !important;
            color: var(--text-main) !important;
            margin: 0;
            padding: 0;
        }

        .branding-container {
            max-width: 1200px;
            margin: 10px auto;
            padding: 0 12px;
        }

        .section-header {
            margin-bottom: 12px;
            border-left: 2px solid var(--brand);
            padding-left: 10px;
        }

        .section-header h2 {
            font-family: var(--font-display);
            text-transform: uppercase;
            font-size: 16px;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .section-header p {
            color: var(--text-muted);
            font-family: var(--font-head);
            font-weight: 500;
            font-size: 10px;
            margin-top: 1px;
        }

        .asset-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .card-title {
            font-family: var(--font-head);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            color: var(--brand);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .logo-preview-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .logo-box {
            width: 120px;
            height: 80px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px dashed var(--border-dim);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .upload-controls {
            flex: 1;
            min-width: 240px;
        }

        .custom-file-input {
            width: 100%;
            height: 34px;
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            border-radius: 8px;
            color: var(--text-main);
            font-family: var(--font-head);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0 12px;
            position: relative;
            font-size: 12px;
        }

        .custom-file-input input {
            display: none;
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
            font-family: var(--font-head);
        }

        .btn-brand-save {
            background: var(--brand-gradient);
            border: none;
            border-radius: 8px;
            color: #000;
            font-family: var(--font-display);
            font-size: 11px;
            font-weight: 700;
            padding: 6px 16px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-brand-save:hover {
            transform: translateY(-1px);
        }

        .grid-asset {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .banner-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            height: 70px;
            border: 1px solid var(--border-dim);
            background: #000;
        }

        .banner-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .banner-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            opacity: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 5;
        }

        .banner-item:hover .banner-overlay {
            opacity: 1;
        }

        .btn-circle-action {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-delete {
            background: #FF2D2D;
            color: #fff;
        }

        .btn-delete:hover {
            transform: scale(1.1);
        }

        @media (max-width: 600px) {
            .logo-preview-wrapper {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .logo-box {
                width: 100%;
                margin-bottom: 10px;
                min-height: 100px;
            }

            .btn-brand-save {
                width: 100%;
                height: 36px;
                margin-top: 10px;
            }

            .social-row {
                gap: 10px;
            }
        }

        .image-preview-float {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid var(--border-dim);
            overflow: hidden;
            pointer-events: none;
        }

        .image-preview-float img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Unified Input Styling */
        .brand-input {
            width: 100%;
            height: 40px;
            background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important;
            border-radius: 8px !important;
            padding: 0 12px !important;
            color: #fff !important;
            font-size: 14px !important;
            font-family: var(--font-ui) !important;
            transition: all 0.3s ease;
        }

        .brand-input:focus {
            border-color: var(--brand) !important;
            outline: none;
            background: rgba(0, 0, 0, 0.3) !important;
        }

        #cropperImage {
            display: block;
            max-width: 100%;
        }
    </style>
</head>

<body>

    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>

        <div class="admin-main-content hide-native-scrollbar">
            <div class="branding-container">

                <div class="section-header">
                    <h2>Asset Management</h2>
                    <p>Modify site identity, promotional banners, and contact information</p>
                </div>

                <!-- SITE LOGO -->
                <form action="manager-branding.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action_type" value="update_logo">
                    <div class="asset-card">
                        <div class="card-title"><i class='bx bx-landscape'></i> Site Identity (Logo)</div>
                        <div class="logo-preview-wrapper">
                            <div class="logo-box">
                                <img src="../../<?php echo $logo_url; ?>?v=<?php echo time(); ?>" alt="Site Logo">
                            </div>
                            <div class="upload-controls">
                                <label class="form-label">Upload Brand Logo (PNG Transparent Recommended)</label>
                                <label class="custom-file-input">
                                    <span>Choose Image...</span>
                                    <input type="file" name="site_logo" id="logo_input" accept="image/*"
                                        onchange="initCropper(this, 'logo', null)">
                                </label>
                                <input type="hidden" name="cropped_data" id="logo_cropped_data">
                                <p style="color: var(--text-muted); font-size: 11px; margin-top: 10px;">Recommended
                                    size: 240x80px. Format: PNG, JPG.</p>
                            </div>
                            <button type="submit" class="btn-brand-save">Update Logo</button>
                        </div>
                    </div>
                </form>

                <!-- THEME CUSTOMIZATION -->
                <form action="manager-branding.php" method="POST">
                    <input type="hidden" name="action_type" value="update_theme">
                    <div class="asset-card">
                        <div class="card-title"><i class='bx bx-palette'></i> Theme Customization</div>
                        <div class="row">
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Primary Brand Color</label>
                                    <div class="d-flex gap-2">
                                        <input type="color" name="site_brand_color" class="brand-input"
                                            value="<?php echo $site_brand_color; ?>" style="width: 50px; padding: 2px;">
                                        <input type="text" class="brand-input" value="<?php echo $site_brand_color; ?>"
                                            readonly style="flex: 1;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Gradient End Color</label>
                                    <div class="d-flex gap-2">
                                        <input type="color" name="site_brand_gradient_end" class="brand-input"
                                            value="<?php echo $site_brand_gradient_end; ?>"
                                            style="width: 50px; padding: 2px;">
                                        <input type="text" class="brand-input"
                                            value="<?php echo $site_brand_gradient_end; ?>" readonly style="flex: 1;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Background Color</label>
                                    <div class="d-flex gap-2">
                                        <input type="color" name="site_bg_color" class="brand-input"
                                            value="<?php echo $site_bg_color; ?>" style="width: 50px; padding: 2px;">
                                        <input type="text" class="brand-input" value="<?php echo $site_bg_color; ?>"
                                            readonly style="flex: 1;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Text Color</label>
                                    <div class="d-flex gap-2">
                                        <input type="color" name="site_text_color" class="brand-input"
                                            value="<?php echo $site_text_color; ?>" style="width: 50px; padding: 2px;">
                                        <input type="text" class="brand-input" value="<?php echo $site_text_color; ?>"
                                            readonly style="flex: 1;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-brand-save">Apply Theme</button>
                            <button type="button" class="btn-brand-save"
                                style="background: var(--input-bg); color: #fff;" onclick="ResetTheme()">Reset to
                                Defaults</button>
                        </div>
                        <p style="color: var(--text-muted); font-size: 10px; margin-top: 10px;">Note: Significant
                            changes may require a page refresh on the frontend to reflect everywhere.</p>
                    </div>
                </form>

                <!-- CONTACT DETAILS -->
                <form action="manager-branding.php" method="POST">
                    <input type="hidden" name="action_type" value="update_contacts">
                    <div class="asset-card">
                        <div class="card-title"><i class='bx bx-support'></i> Support & Social Channels</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Telegram URL</label>
                                    <input type="text" name="telegram_url" class="brand-input"
                                        value="<?php echo $telegram_url; ?>" placeholder="https://t.me/...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_num" class="brand-input"
                                        value="<?php echo $whatsapp_num; ?>" placeholder="+91 0000 000000">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Direct Support Link</label>
                                    <input type="text" name="support_url" class="brand-input"
                                        value="<?php echo $support_url; ?>" placeholder="https://...">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn-brand-save">Save Contact Info</button>
                    </div>
                </form>

                <!-- SITE TEXTS -->
                <form action="manager-branding.php" method="POST">
                    <input type="hidden" name="action_type" value="update_site_texts">
                    <div class="asset-card">
                        <div class="card-title"><i class='bx bx-text'></i> Site Identity Texts</div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Site Name (Company Name)</label>
                                    <input type="text" name="site_name" class="brand-input"
                                        value="<?php echo htmlspecialchars($site_name); ?>" placeholder="e.g. Winco">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Tagline (Under Logo)</label>
                                    <input type="text" name="site_tagline" class="brand-input"
                                        value="<?php echo htmlspecialchars($site_tagline); ?>"
                                        placeholder="e.g. Play Win Repeat">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Marquee Text (News Ticker)</label>
                                    <input type="text" name="site_marquee" class="brand-input"
                                        value="<?php echo htmlspecialchars($site_marquee); ?>"
                                        placeholder="Latest news here...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Site Address (Footer)</label>
                                    <input type="text" name="site_address" class="brand-input"
                                        value="<?php echo htmlspecialchars($site_address); ?>"
                                        placeholder="123 Example St...">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn-brand-save">Save Site Texts</button>
                    </div>
                </form>

                <!-- DYNAMIC SOCIAL LINKS -->
                <form action="manager-branding.php" method="POST">
                    <input type="hidden" name="action_type" value="update_social_links">
                    <div class="asset-card">
                        <div class="card-title" style="justify-content: space-between;">
                            <span><i class='bx bx-share-alt'></i> Dynamic Social Links</span>
                            <button type="button" class="btn-brand-save" style="padding: 8px 20px; font-size: 12px;"
                                onclick="addSocialLink()">+ Add Link</button>
                        </div>
                        <div id="social-links-container">
                            <?php
                            if (empty($social_links)) {
                                // Default empty row
                                echo '
                            <div class="row social-row mb-3" style="align-items: center;">
                                <div class="col-md-3">
                                    <select name="platforms[]" class="brand-input">
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="Telegram">Telegram</option>
                                        <option value="Instagram">Instagram</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Twitter">Twitter/X</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <input type="text" name="urls[]" class="brand-input" placeholder="Number or URL...">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn-circle-action btn-delete" onclick="this.closest(\'.social-row\').remove()"><i class=\'bx bx-x\'></i></button>
                                </div>
                            </div>';
                            } else {
                                foreach ($social_links as $link) {
                                    $plat = htmlspecialchars($link['platform']);
                                    $val = htmlspecialchars($link['value']);
                                    $selectOpts = ['WhatsApp', 'Telegram', 'Instagram', 'Facebook', 'Twitter'];
                                    $optsHtml = '';
                                    foreach ($selectOpts as $opt) {
                                        $sel = ($opt === $plat) ? 'selected' : '';
                                        $optsHtml .= "<option value='$opt' $sel>$opt</option>";
                                    }
                                    echo '
                                <div class="row social-row mb-3" style="align-items: center;">
                                    <div class="col-md-3">
                                        <select name="platforms[]" class="brand-input">' . $optsHtml . '</select>
                                    </div>
                                    <div class="col-md-7">
                                        <input type="text" name="urls[]" class="brand-input" value="' . $val . '" placeholder="Number or URL...">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn-circle-action btn-delete" onclick="this.closest(\'.social-row\').remove()"><i class=\'bx bx-x\'></i></button>
                                    </div>
                                </div>';
                                }
                            }
                            ?>
                        </div>
                        <button type="submit" class="btn-brand-save mt-3">Save Social Links</button>
                    </div>
                </form>

                <!-- HOME SLIDERS -->
                <div class="asset-card">
                    <div class="card-title" style="justify-content: space-between;">
                        <span><i class='bx bx-images'></i> Homepage Banners (Sliders)</span>
                        <button class="btn-brand-save" style="padding: 8px 20px; font-size: 12px;"
                            onclick="document.getElementById('addSliderModal').style.display='flex'">+ Add
                            Slider</button>
                    </div>
                    <div class="grid-asset">
                        <?php
                        $sliders = mysqli_query($conn, "SELECT * FROM tblsliders WHERE tbl_slider_status='true'");
                        while ($s = mysqli_fetch_assoc($sliders)) {
                            ?>
                            <div class="banner-item">
                                <img src="../../<?php echo $s['tbl_slider_img']; ?>" alt="Banner">
                                <div class="banner-overlay">
                                    <button class="btn-circle-action btn-delete"
                                        onclick="DeleteAsset('slider', <?php echo $s['id']; ?>)"><i
                                            class='bx bx-trash'></i></button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- PROMOTIONAL BANNERS -->
                <div class="asset-card">
                    <div class="card-title" style="justify-content: space-between;">
                        <span><i class='bx bx-star'></i> Promotional Banners</span>
                        <button class="btn-brand-save" style="padding: 8px 20px; font-size: 12px;"
                            onclick="document.getElementById('addPromoModal').style.display='flex'">+ Add Promo</button>
                    </div>
                    <div class="grid-asset">
                        <?php
                        $promos = mysqli_query($conn, "SELECT * FROM tbl_promotions WHERE status='true'");
                        while ($p = mysqli_fetch_assoc($promos)) {
                            ?>
                            <div class="banner-item">
                                <img src="../../<?php echo $p['image_path']; ?>" alt="Promo">
                                <div class="banner-overlay">
                                    <button class="btn-circle-action btn-delete"
                                        onclick="DeleteAsset('promo', <?php echo $p['id']; ?>)"><i
                                            class='bx bx-trash'></i></button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Simple Modal for Adding Assets -->
    <div id="addSliderModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:100; align-items:center; justify-content:center; padding:20px;">
        <div class="asset-card" style="width:100%; max-width:500px; margin:0;">
            <div class="card-title">Add Home Slider</div>
            <form action="manager-branding.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="add_slider">
                <div class="form-group">
                    <label class="form-label">Banner Image</label>
                    <input type="file" name="banner_img" id="slider_input" class="brand-input" style="padding:10px;"
                        accept="image/*" onchange="initCropper(this, 'slider', 21/9)">
                    <input type="hidden" name="cropped_data" id="slider_cropped_data">
                </div>
                <div class="form-group">
                    <label class="form-label">Action Link (Optional)</label>
                    <input type="text" name="action_url" class="brand-input" placeholder="https://...">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-brand-save">Upload Now</button>
                    <button type="button" class="btn-brand-save" style="background:var(--input-bg); color:#fff;"
                        onclick="this.closest('#addSliderModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addPromoModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:100; align-items:center; justify-content:center; padding:20px;">
        <div class="asset-card" style="width:100%; max-width:500px; margin:0;">
            <div class="card-title">Add Promotional Banner</div>
            <form action="manager-branding.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="add_promo">
                <div class="form-group">
                    <label class="form-label">Promo Image</label>
                    <input type="file" name="promo_img" id="promo_input" class="brand-input" style="padding:10px;"
                        accept="image/*" onchange="initCropper(this, 'promo', 21/9)">
                    <input type="hidden" name="cropped_data" id="promo_cropped_data">
                </div>
                <div class="form-group">
                    <label class="form-label">Action Link (Optional)</label>
                    <input type="text" name="action_url" class="brand-input" placeholder="https://...">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-brand-save">Upload now</button>
                    <button type="button" class="btn-brand-save" style="background:var(--input-bg); color:#fff;"
                        onclick="this.closest('#addPromoModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unified Cropping Modal -->
    <div id="cropperModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; padding:20px;">
        <div class="asset-card" style="width:100%; max-width:800px; margin:0; background:#111;">
            <div class="card-title">Crop Your Image</div>
            <div
                style="width:100%; height:400px; background:#000; border-radius:12px; overflow:hidden; margin-bottom:20px;">
                <img id="cropperImage" style="max-width:100%;">
            </div>
            <div class="d-flex justify-content-between align-items:center;">
                <button type="button" class="btn-brand-save" style="background:var(--input-bg); color:#fff;"
                    onclick="closeCropper()">Cancel</button>
                <button type="button" class="btn-brand-save" onclick="applyCrop()">Crop & Save</button>
            </div>
        </div>
    </div>

    <script>
        let cropper = null;
        let currentTargetType = ''; // 'logo', 'slider', 'promo'

        function initCropper(input, type, aspectRatio) {
            if (input.files && input.files[0]) {
                currentTargetType = type;
                const reader = new FileReader();
                reader.onload = function (e) {
                    const modal = document.getElementById('cropperModal');
                    const image = document.getElementById('cropperImage');
                    image.src = e.target.result;

                    modal.style.display = 'flex';

                    // Ensure image is loaded before initializing cropper
                    image.onload = function () {
                        if (cropper) cropper.destroy();
                        cropper = new Cropper(image, {
                            aspectRatio: aspectRatio,
                            viewMode: 2,
                            background: false,
                            autoCropArea: 1,
                            responsive: true,
                            restore: false,
                        });
                    };
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function closeCropper() {
            document.getElementById('cropperModal').style.display = 'none';
            if (cropper) cropper.destroy();
            // Reset file inputs if cancelled
            document.getElementById('logo_input').value = '';
            document.getElementById('slider_input').value = '';
            document.getElementById('promo_input').value = '';
        }

        function applyCrop() {
            if (!cropper) return;

            let canvasOptions = {
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            };

            if (currentTargetType === 'logo') {
                canvasOptions.maxWidth = 800;
            } else {
                canvasOptions.width = 1260;
                canvasOptions.height = 540;
            }

            const canvas = cropper.getCroppedCanvas(canvasOptions);

            const mimeType = currentTargetType === 'logo' ? 'image/png' : 'image/jpeg';
            const quality = currentTargetType === 'logo' ? 1.0 : 0.9;

            const base64 = canvas.toDataURL(mimeType, quality);
            document.getElementById(currentTargetType + '_cropped_data').value = base64;

            // Update label to show cropped
            const input = document.getElementById(currentTargetType + '_input');
            if (input.previousElementSibling) {
                input.previousElementSibling.innerText = "Image Cropped (Ready)";
            }

            document.getElementById('cropperModal').style.display = 'none';
            if (cropper) cropper.destroy();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Image cropped successfully!',
                showConfirmButton: false,
                timer: 1500
            });
        }

        function DeleteAsset(type, id) {
            if (confirm("Are you sure you want to delete this asset?")) {
                window.location.href = `manager-branding.php?action_type=delete_asset&type=${type}&id=${id}`;
            }
        }

        function addSocialLink() {
            const container = document.getElementById('social-links-container');
            const row = document.createElement('div');
            row.className = 'row social-row mb-3';
            row.style.alignItems = 'center';
            row.innerHTML = `
            <div class="col-md-3">
                <select name="platforms[]" class="brand-input">
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Telegram">Telegram</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Facebook">Facebook</option>
                    <option value="Twitter">Twitter/X</option>
                </select>
            </div>
            <div class="col-md-7">
                <input type="text" name="urls[]" class="brand-input" placeholder="Number or URL...">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn-circle-action btn-delete" onclick="this.closest('.social-row').remove()"><i class='bx bx-x'></i></button>
            </div>
        `;
            container.appendChild(row);
        }

        // Handle Messages & Alerts from URL
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const err = urlParams.get('err');

            if (msg) {
                Swal.fire({
                    title: 'Success!',
                    text: msg,
                    icon: 'success',
                    confirmButtonText: 'Great'
                });
            }
            if (err) {
                Swal.fire({
                    title: 'Error!',
                    text: err,
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        });

        function ResetTheme() {
            if (confirm("Restore factory default colors and theme?")) {
                window.location.href = 'manager-branding.php?action_type=reset_theme';
            }
        }
    </script>

</body>

</html>