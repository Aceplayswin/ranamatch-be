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

// Fetch settings
$settings = [];
$res = mysqli_query($conn, "SELECT * FROM tblservices");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['tbl_service_name']] = $row['tbl_service_value'];
}

$agent_site_name = $settings['AGENT_SITE_NAME'] ?? 'VELPLAY Agent';
$affiliate_site_name = $settings['AFFILIATE_SITE_NAME'] ?? 'VELPLAY Affiliate';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title>Agent & Affiliate Control | <?php echo $APP_NAME; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>

    <style>
        :root {
            --brand: #06b6d4;
            --brand-light: #22d3ee;
            --brand-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --app-bg: #0D0D0D;
            --panel-bg: #141414;
            --input-bg: #1A1A1A;
            --border-dim: rgba(255, 255, 255, 0.08);
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
            margin-bottom: 15px;
            border-left: 3px solid var(--brand);
            padding-left: 12px;
        }

        .section-header h2 {
            font-family: var(--font-display);
            text-transform: uppercase;
            font-size: 18px;
            letter-spacing: 0.5px;
            margin: 0;
            color: #fff;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 3px;
        }

        .asset-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .card-title {
            font-family: var(--font-head);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            color: var(--brand);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        .brand-input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            border-radius: 8px;
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .brand-input:focus {
            border-color: var(--brand);
        }

        .btn-brand-save {
            background: var(--brand-gradient);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 20px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-brand-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }

        .grid-asset {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
        }

        .banner-item {
            position: relative;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border-dim);
        }

        .banner-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .banner-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .banner-item:hover .banner-overlay {
            opacity: 1;
        }

        .btn-circle-action {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #fff;
            font-size: 16px;
        }
        .btn-view { background: #06b6d4; }
        .btn-delete { background: #ef4444; }
    </style>
</head>

<body>
    <?php include "../components/side-menu.php" ?>

    <div class="content-wrapper" style="margin-left: 260px; padding: 20px;">
        <div class="branding-container">

            <div class="section-header">
                <h2><i class='bx bx-user-check'></i> Agent & Affiliate Control</h2>
                <p>Manage Company Name and Promotional Banners for both Agent Console and Affiliate Portal</p>
            </div>

            <!-- COMPANY NAMES -->
            <form action="manager-agent-control.php" method="POST">
                <input type="hidden" name="action_type" value="update_texts">
                <div class="asset-card">
                    <div class="card-title"><i class='bx bx-building-house'></i> Panel Company Names</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Agent Panel Company Name</label>
                                <input type="text" name="agent_site_name" class="brand-input"
                                    value="<?php echo htmlspecialchars($agent_site_name); ?>" placeholder="e.g. VELPLAY Agent">
                                <p style="color: var(--text-muted); font-size: 10px; margin-top: 4px;">Displayed in Agent Header, Sidebar, and Landing Page.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Affiliate Panel Company Name</label>
                                <input type="text" name="affiliate_site_name" class="brand-input"
                                    value="<?php echo htmlspecialchars($affiliate_site_name); ?>" placeholder="e.g. VELPLAY Affiliate">
                                <p style="color: var(--text-muted); font-size: 10px; margin-top: 4px;">Displayed in Affiliate Header, Sidebar, and Landing Page.</p>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-brand-save">Save Company Names</button>
                </div>
            </form>

            <!-- AGENT BANNERS -->
            <div class="asset-card">
                <div class="card-title" style="justify-content: space-between;">
                    <span><i class='bx bx-image'></i> Agent Console Banners</span>
                    <button class="btn-brand-save" style="padding: 6px 14px; font-size: 11px;"
                        onclick="openUploadModal('agent')">+ Add Agent Banner</button>
                </div>
                <div class="grid-asset">
                    <?php
                    $agentBanners = mysqli_query($conn, "SELECT * FROM tbl_agent_banners WHERE status='true' ORDER BY id DESC");
                    if (mysqli_num_rows($agentBanners) == 0) {
                        echo '<p style="color: var(--text-muted); font-size: 12px; grid-column: 1/-1;">No Agent banners uploaded yet.</p>';
                    }
                    while ($b = mysqli_fetch_assoc($agentBanners)) {
                        ?>
                        <div class="banner-item">
                            <img src="../../<?php echo $b['image_path']; ?>" alt="Agent Banner">
                            <div class="banner-overlay" style="gap: 10px;">
                                <button class="btn-circle-action btn-view"
                                    onclick="ViewImage('../../<?php echo $b['image_path']; ?>')"><i
                                        class='bx bx-show'></i></button>
                                <button class="btn-circle-action btn-delete"
                                    onclick="DeleteBanner('agent', <?php echo $b['id']; ?>)"><i
                                        class='bx bx-trash'></i></button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- AFFILIATE BANNERS -->
            <div class="asset-card">
                <div class="card-title" style="justify-content: space-between;">
                    <span><i class='bx bx-slideshow'></i> Affiliate Portal Banners</span>
                    <button class="btn-brand-save" style="padding: 6px 14px; font-size: 11px;"
                        onclick="openUploadModal('affiliate')">+ Add Affiliate Banner</button>
                </div>
                <div class="grid-asset">
                    <?php
                    $affBanners = mysqli_query($conn, "SELECT * FROM tbl_affiliate_banners WHERE status='true' ORDER BY id DESC");
                    if (mysqli_num_rows($affBanners) == 0) {
                        echo '<p style="color: var(--text-muted); font-size: 12px; grid-column: 1/-1;">No Affiliate banners uploaded yet.</p>';
                    }
                    while ($b = mysqli_fetch_assoc($affBanners)) {
                        ?>
                        <div class="banner-item">
                            <img src="../../<?php echo $b['image_path']; ?>" alt="Affiliate Banner">
                            <div class="banner-overlay" style="gap: 10px;">
                                <button class="btn-circle-action btn-view"
                                    onclick="ViewImage('../../<?php echo $b['image_path']; ?>')"><i
                                        class='bx bx-show'></i></button>
                                <button class="btn-circle-action btn-delete"
                                    onclick="DeleteBanner('affiliate', <?php echo $b['id']; ?>)"><i
                                        class='bx bx-trash'></i></button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadBannerModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; padding:20px;">
        <div class="asset-card" style="width:100%; max-width:480px; margin:0; background:#111;">
            <div class="card-title" id="modalTitle">Upload Banner</div>
            <form action="manager-agent-control.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="add_banner">
                <input type="hidden" name="target_panel" id="target_panel" value="agent">
                <div class="form-group">
                    <label class="form-label">Select Banner Image(s)</label>
                    <input type="file" name="banner_imgs[]" class="brand-input" style="padding:10px;"
                        accept="image/*" multiple required>
                </div>
                <div class="form-group">
                    <label class="form-label">Action Link (Optional)</label>
                    <input type="text" name="action_url" class="brand-input" placeholder="https://...">
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn-brand-save">Upload Now</button>
                    <button type="button" class="btn-brand-save" style="background:var(--input-bg); color:#fff;"
                        onclick="document.getElementById('uploadBannerModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightboxModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:10000; align-items:center; justify-content:center;" onclick="this.style.display='none'">
        <img id="lightboxImg" style="max-width:90%; max-height:90%; border-radius:12px; box-shadow:0 0 40px rgba(0,0,0,0.8);" src="" alt="Full View">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function openUploadModal(target) {
            document.getElementById('target_panel').value = target;
            document.getElementById('modalTitle').innerText = target === 'affiliate' ? 'Upload Affiliate Banner' : 'Upload Agent Banner';
            document.getElementById('uploadBannerModal').style.display = 'flex';
        }

        function DeleteBanner(target, id) {
            if (confirm(`Are you sure you want to delete this ${target} banner?`)) {
                window.location.href = `manager-agent-control.php?action_type=delete_banner&target=${target}&id=${id}`;
            }
        }

        function ViewImage(src) {
            const modal = document.getElementById('lightboxModal');
            const img = document.getElementById('lightboxImg');
            img.src = src;
            modal.style.display = 'flex';
        }

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const err = urlParams.get('err');

            if (msg) {
                Swal.fire({
                    title: 'Success!',
                    text: msg,
                    icon: 'success',
                    confirmButtonText: 'OK'
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
    </script>
</body>
</html>
