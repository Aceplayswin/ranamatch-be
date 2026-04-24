<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_settings")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../../logout-account');
    exit;
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : "";
if (empty($id)) {
    header("Location: index.php");
    exit;
}

// Fetch existing data
$sql = "SELECT * FROM tbl_offer_promotions WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    header("Location: index.php?msg=Promotion Not Found");
    exit;
}

if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "<script>alert('Game is under Demo Mode. So, you can not modify.'); window.location.href='index.php';</script>";
    return;
  }
  
  $title = mysqli_real_escape_string($conn, $_POST['title']);
  $description = mysqli_real_escape_string($conn, $_POST['description']);
  $category = mysqli_real_escape_string($conn, $_POST['category']);
  $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
  
  $image_url = $data['image_path']; // Default to old image
  
  // Higher priority to cropped base64 data
  if (!empty($_POST['cropped_image_base64'])) {
      $base64_data = $_POST['cropped_image_base64'];
      if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
          $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
          $type = strtolower($type[1]); // jpg, png, etc
          $base64_data = base64_decode($base64_data);
          
          if ($base64_data !== false) {
              $upload_dir = '../../uploads/promotions/';
              if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
              $file_name = uniqid() . '.' . $type;
              if (file_put_contents($upload_dir . $file_name, $base64_data)) {
                  $image_url = 'uploads/promotions/' . $file_name;
              }
          }
      }
  } 
  
  $update_sql = "UPDATE tbl_offer_promotions 
                 SET title = '$title', description = '$description', category = '$category', end_date = '$end_date', image_path = '$image_url' 
                 WHERE id = '$id'";
  $update_result = mysqli_query($conn, $update_sql);

  if ($update_result){
      header("Location: index.php?msg=Promotion Updated");
      exit;
  } else {
      echo "<script>alert('Failed to update promotion!');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Edit Promotion</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 40px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--status-info); text-transform: uppercase; letter-spacing: 1px; }

        .main-panel {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .glass-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim); border-radius: 24px;
            padding: 40px; width: 100%; max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 0 auto;
        }

        .form-group { margin-bottom: 24px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .cus-inp, .cus-select {
            width: 100%; height: 52px; background: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important; border-radius: 14px !important;
            padding: 0 16px !important; color: var(--text-main) !important; font-size: 15px !important;
            transition: all 0.3s ease;
        }
        .cus-inp:focus, .cus-select:focus {
            border-color: var(--accent-blue) !important; background: var(--table-row-hover) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
        .cus-inp::placeholder { color: var(--text-dim); opacity: 0.5; }
        
        .cus-select option { background: var(--panel-bg); color: var(--text-main); }

        textarea.cus-inp { height: 100px !important; padding: 16px !important; resize: none; }

        .action-btn {
            width: 100%; height: 52px; background: var(--accent-blue);
            color: #fff; border: none; border-radius: 14px; font-weight: 800;
            font-size: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .action-btn:hover {
            transform: translateY(-2px); background: #2563eb;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-dim);
            text-decoration: none; font-weight: 700; font-size: 11px; text-transform: uppercase;
            margin-bottom: 15px; cursor: pointer; transition: color 0.2s;
        }
        .back-link:hover { color: var(--text-main); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <a href="index.php" class="back-link">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </a><br>
                <span class="dash-breadcrumb">Content Management</span>
                <h1>Edit Promotion</h1>
            </div>
        </div>

        <div class="v-center" style="min-height: calc(100vh - 200px); padding-bottom: 50px;">
            <div class="glass-card">
                <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label class="form-label">Promotion Title</label>
                        <input type="text" name="title" class="cus-inp" value="<?php echo $data['title']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description / Subtitle</label>
                        <input type="text" name="description" class="cus-inp" value="<?php echo $data['description']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="cus-select" required>
                            <option value="all" <?php if($data['category']=='all') echo 'selected'; ?>>All Channels</option>
                            <option value="sports" <?php if($data['category']=='sports') echo 'selected'; ?>>Sports</option>
                            <option value="casino" <?php if($data['category']=='casino') echo 'selected'; ?>>Casino</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Offer Ends At</label>
                        <input type="datetime-local" name="end_date" class="cus-inp" value="<?php echo date('Y-m-d\TH:i', strtotime($data['end_date'])); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Banner Image <span style="color:var(--accent-blue);">(800x400px - 2:1)</span></label>
                        <input type="file" id="promo_image_input" class="cus-inp crop-upload" data-ratio="2" accept="image/*" style="padding-top:12px;">
                        <input type="hidden" name="cropped_image_base64" id="cropped_image_base64">
                        
                        <div id="cropPreviewContainer" style="margin-top:15px; <?php if(empty($data['image_path'])) echo 'display:none;'; ?>">
                            <label class="form-label" style="color:var(--status-success);">Current / New Banner:</label>
                            <img id="imgPreview" src="../../<?php echo $data['image_path']; ?>" style="width:100%; border-radius:14px; border:1px solid var(--status-success); box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                        </div>
                    </div>

                    <button type="submit" name="submit" class="action-btn">
                        <i class='bx bx-save'></i> Update Promotion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../script.js?v=2"></script>

<!-- Crop Modal -->
<div id="cropModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; padding:20px; flex-direction:column;">
    <div style="background:var(--page-bg); padding:20px; border-radius:16px; width:100%; max-width:800px; max-height:90vh; display:flex; flex-direction:column; border:1px solid var(--border-dim); box-shadow: 0 10px 40px rgba(0,0,0,0.7);">
        <div style="font-size:18px; font-weight:700; margin-bottom:15px; color:var(--text-main);">Crop Image</div>
        <div style="flex:1; min-height:300px; max-height:60vh; width:100%; background:#000; overflow:hidden;">
            <img id="cropImage" src="" style="max-width:100%; display:block;">
        </div>
        <div class="d-flex gap-3 justify-content-end mt-4">
            <button type="button" class="action-btn" style="background:#333; height:40px; width:auto; padding:0 20px;" onclick="closeCropModal()">Cancel</button>
            <button type="button" class="action-btn" id="btnApplyCrop" style="height:40px; width:auto; padding:0 20px;">Use This Crop</button>
        </div>
    </div>
</div>

<!-- Cropper JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
    let currentCropper = null;
    let currentInput = null;
    let currentFileName = "";
    
    document.addEventListener('change', function(e) {
        if (!e.target || !e.target.classList.contains('crop-upload')) return;
        
        const input = e.target;
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            input.value = '';
            return;
        }
        
        currentInput = input;
        currentFileName = file.name;
        const ratio = parseFloat(input.getAttribute('data-ratio')) || NaN;
        
        const reader = new FileReader();
        reader.onload = function(evt) {
            document.getElementById('cropImage').src = evt.target.result;
            document.getElementById('cropModal').style.display = 'flex';
            
            if (currentCropper) currentCropper.destroy();
            
            currentCropper = new Cropper(document.getElementById('cropImage'), {
                aspectRatio: ratio,
                viewMode: 2,
                autoCropArea: 1,
                background: false
            });
        };
        reader.readAsDataURL(file);
    });
    
    function closeCropModal() {
        document.getElementById('cropModal').style.display = 'none';
        if (currentCropper) {
            currentCropper.destroy();
            currentCropper = null;
        }
        if (currentInput) {
            currentInput.value = '';
        }
    }
    
    document.getElementById('btnApplyCrop').addEventListener('click', function() {
        if (!currentCropper) return;
        
        const canvas = currentCropper.getCroppedCanvas({
            width: 800,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        const base64Data = canvas.toDataURL('image/jpeg', 0.9);
        document.getElementById('cropped_image_base64').value = base64Data;
        document.getElementById('imgPreview').src = base64Data;
        document.getElementById('cropPreviewContainer').style.display = 'block';
        
        closeCropModal();
    });
</script>
</body>
</html>
