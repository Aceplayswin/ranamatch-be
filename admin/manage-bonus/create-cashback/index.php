<?php
define("ACCESS_SECURITY","true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_gift")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../../logout-account');
    exit;
}

$is_edit = isset($_GET['id']);
$cb_id = $is_edit ? (int)$_GET['id'] : 0;
$cb_data = [];

if ($is_edit) {
    $res = mysqli_query($conn, "SELECT * FROM tbl_cashback_bonuses WHERE id = $cb_id");
    if ($res) $cb_data = mysqli_fetch_assoc($res);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Create Cashback</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    
    <style>
<?php include "../../components/theme-variables.php"; ?>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100vh; overflow-y: auto;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            padding: 24px;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 30px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 22px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 10px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .glass-card {
            background: var(--card-bg); backdrop-filter: blur(12px);
            border: 1px solid var(--border-dim); border-radius: 20px;
            padding: 18px; width: 100%; max-width: 1000px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 0 auto;
            display: flex; flex-direction: row; gap: 0; 
            height: calc(100vh - 180px); /* Fixed height for inner scrolling on desktop */
            min-height: 500px;
        }

        /* Inner Wizard Sidebar */
        .wizard-sidebar {
            width: 200px; flex-shrink: 0; display: flex; flex-direction: column; gap: 6px;
            border-right: 1px solid var(--border-dim); padding: 5px 15px 5px 5px;
            overflow-y: auto; height: 100%;
        }
        .wizard-sidebar::-webkit-scrollbar { width: 4px; }
        .wizard-sidebar::-webkit-scrollbar-thumb { background: rgba(59, 130, 246, 0.2); border-radius: 10px; }

        .wizard-step {
            padding: 12px; border-radius: 12px; background: transparent;
            border: 1px solid transparent; text-align: left; cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; gap: 0px;
        }
        .wizard-step:hover { background: rgba(255,255,255,0.03); }
        .wizard-step span { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .wizard-step small { font-size: 9px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }

        .wizard-step.active {
            background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2);
        }
        .wizard-step.active span { color: var(--text-main); }
        .wizard-step.active small { color: var(--accent-blue); }

        /* Form Content Area */
        .wizard-content { flex-grow: 1; padding: 10px 20px; overflow-y: auto; height: 100%; }
        .wizard-content::-webkit-scrollbar { width: 6px; }
        .wizard-content::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }

        @media (max-width: 900px) {
            body { overflow-y: auto !important; height: auto !important; }
            .main-panel { height: auto !important; padding: 12px !important; }
            .glass-card { flex-direction: column !important; height: auto !important; padding: 12px !important; border-radius: 16px !important; }
            .wizard-sidebar {
                width: 100% !important; height: auto !important; border-right: none !important;
                border-bottom: 1px solid var(--border-dim) !important;
                padding: 10px !important; flex-direction: row !important; overflow-x: auto !important;
                white-space: nowrap !important; gap: 8px !important; scrollbar-width: none;
            }
            .wizard-sidebar::-webkit-scrollbar { display: none; }
            .wizard-step { min-width: 130px !important; padding: 8px 12px !important; flex-direction: row !important; align-items: center !important; gap: 8px !important; background: var(--input-bg) !important; }
            .wizard-step span { font-size: 11px !important; }
            .wizard-step small { margin-bottom: 0 !important; }
            .wizard-content { padding: 15px 0 !important; height: auto !important; overflow: visible !important; }
        }

        .form-label {
            display: block; font-size: 10px; font-weight: 800; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
        }
        .form-label span { color: #f43f5e; margin-left: 2px; }

        .cus-inp, .cus-sel, .cus-txt {
            width: 100%; height: 40px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 10px !important;
            padding: 0 12px !important; color: var(--text-main) !important; font-size: 13px !important;
            transition: all 0.3s ease;
        }
        .cus-sel option { background-color: var(--panel-bg) !important; color: var(--text-main) !important; }
        .cus-inp:focus, .cus-sel:focus, .cus-txt:focus { border-color: var(--accent-blue) !important; background: var(--input-bg) !important; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important; outline: none; }
        .cus-txt { height: 80px !important; padding: 12px !important; resize: none; }

        .image-upload-box {
            background: rgba(0,0,0,0.22); border: 1px dashed var(--border-dim);
            border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: 0.3s;
        }
        .image-upload-box:hover { border-color: var(--accent-blue); background: rgba(59, 130, 246, 0.05); }
        .upload-trigger { cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .upload-trigger span { font-size: 12px; font-weight: 700; color: var(--text-main); }
        .upload-trigger small { font-size: 10px; color: var(--text-dim); }

        .toggle-group {
            display: flex; background: rgba(0,0,0,0.22);
            border: 1px solid var(--border-dim); border-radius: 8px;
            overflow: hidden; width: 100px;
        }
        .toggle-btn {
            flex: 1; padding: 6px 0; font-size: 11px; font-weight: 800;
            text-align: center; color: var(--text-dim); cursor: pointer;
            border: none; transition: all 0.2s; background: transparent;
        }
        .toggle-btn.active { background: var(--accent-blue); color: #fff; }

        .action-btn {
            width: 100px; height: 38px; background: var(--accent-blue);
            color: #fff; border: none; border-radius: 8px; font-weight: 800;
            font-size: 13px; cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .action-btn:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Cashback > Promotion Builder</span>
                <h1>Create Cashback</h1>
            </div>
        </div>

        <div style="padding-bottom: 100px;">
            <div class="glass-card">
                <!-- Outer Wizard Sidebar -->
                <div class="wizard-sidebar hide-native-scrollbar">
                    <div class="wizard-step active" data-step="1">
                        <span>General</span>
                        <small>Step 1 of 4</small>
                    </div>
                    <div class="wizard-step" data-step="2">
                        <span>Logic</span>
                        <small>Step 2 of 4</small>
                    </div>
                    <div class="wizard-step" data-step="3">
                        <span>Scheduling</span>
                        <small>Step 3 of 4</small>
                    </div>
                    <div class="wizard-step" data-step="4">
                        <span>Redemption</span>
                        <small>Step 4 of 4</small>
                    </div>
                </div>

                <!-- Main Form Area -->
                <div class="wizard-content hide-native-scrollbar">
                    <form id="cbForm" enctype="multipart/form-data">
                        <input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>">
                        <input type="hidden" name="edit_id" value="<?php echo $cb_id; ?>">
                        <input type="hidden" name="cb_image_base64" id="cb_image_base64" value="">
                        
                        <?php include "steps/step1.php"; ?>
                        <?php include "steps/step2.php"; ?>
                        <?php include "steps/step3.php"; ?>
                        <?php include "steps/step4.php"; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Crop Modal -->
<div id="cropModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; padding:20px; flex-direction:column;">
    <div style="background:var(--page-bg); padding:20px; border-radius:16px; width:100%; max-width:800px; max-height:90vh; display:flex; flex-direction:column; border:1px solid var(--border-dim); box-shadow: 0 10px 40px rgba(0,0,0,0.7);">
        <div style="font-size:18px; font-weight:700; margin-bottom:15px; color:var(--text-main);">Crop Promotion Banner</div>
        <div style="flex:1; min-height:300px; max-height:60vh; width:100%; background:#000; overflow:hidden;">
            <img id="cropImage" src="" style="max-width:100%; display:block;">
        </div>
        <div class="d-flex gap-3 justify-content-end mt-4">
            <button type="button" class="action-btn" style="background:#333; height:40px; width:auto; padding:0 20px;" onclick="closeCropModal()">Cancel</button>
            <button type="button" class="action-btn" id="btnApplyCrop" style="height:40px; width:auto; padding:0 20px;">Use This Crop</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<script>
$(document).ready(function() {
    // Step Switching Logic
    const steps = $('.wizard-step');
    const contents = $('.wizard-step-content');

    function goToStep(stepNumber) {
        stepNumber = parseInt(stepNumber);
        steps.removeClass('active');
        $(`.wizard-step[data-step="${stepNumber}"]`).addClass('active');

        contents.hide();
        const nextContent = $(`#step${stepNumber}`);
        if(nextContent.length) {
            nextContent.show();
            $('.wizard-content').scrollTop(0);
        }
    }

    steps.on('click', function() {
        goToStep($(this).data('step'));
    });

    $(document).on('click', '.next-step', function() {
        const next = $(this).data('next');
        if(next) goToStep(next);
    });

    $(document).on('click', '.btn-prev', function() {
        const current = $('.wizard-step.active').data('step');
        if(current > 1) goToStep(current - 1);
    });

    // Handle Toggles
    $(document).on('click', '.toggle-btn', function() {
        const group = $(this).closest('.toggle-group');
        const btns = group.find('.toggle-btn');
        const hiddenInp = group.find('input[type="hidden"]');
        btns.removeClass('active');
        $(this).addClass('active');
        hiddenInp.val($(this).data('val'));
    });

    // Image Cropping Logic
    let currentCropper = null;
    let currentInput = null;
    let currentFileName = "";

    $(document).on('change', '.crop-upload', function(e) {
        const input = e.target;
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        currentInput = input;
        currentFileName = file.name;
        const ratio = parseFloat($(input).data('ratio')) || NaN;
        
        const reader = new FileReader();
        reader.onload = function(evt) {
            $('#cropImage').attr('src', evt.target.result);
            $('#cropModal').css('display', 'flex');
            
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

    window.closeCropModal = function() {
        $('#cropModal').hide();
        if (currentCropper) { currentCropper.destroy(); currentCropper = null; }
        if (currentInput) currentInput.value = '';
    }

    $('#btnApplyCrop').on('click', function() {
        if (!currentCropper) return;
        const canvas = currentCropper.getCroppedCanvas({ imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
        
        // Convert to Base64 for 100% reliability
        const base64Data = canvas.toDataURL('image/jpeg', 0.9);
        $('#cb_image_base64').val(base64Data);
        
        // Show preview
        $('#new_image_preview').attr('src', base64Data);
        $('#new_image_preview_container').show();
        $('.current-image-preview').hide();
        
        // Clear the actual file input so we only send the base64 string
        if (currentInput) currentInput.value = '';
        
        closeCropModal();
    });

    $('#cbForm').on('submit', function(e) {
        e.preventDefault();
        
        // --- VALIDATION START ---
        const errors = [];
        let firstStep = null;
        if (!$('input[name="name"]').val().trim()) { errors.push('Promotion Name'); if(!firstStep) firstStep = 1; }
        if (!$('input[name="title"]').val().trim()) { errors.push('Display Title'); if(!firstStep) firstStep = 1; }
        if (!$('input[name="percentage"]').val()) { errors.push('Percentage'); if(!firstStep) firstStep = 2; }
        if (!$('input[name="min_loss"]').val()) { errors.push('Min Loss'); if(!firstStep) firstStep = 2; }
        if (!$('input[name="start_date"]').val()) { errors.push('Start Date'); if(!firstStep) firstStep = 3; }
        if ($('input[name="pattern_days[]"]:checked').length === 0) { errors.push('Selected Days'); if(!firstStep) firstStep = 3; }

        if (errors.length > 0) {
            goToStep(firstStep);
            Swal.fire({ icon: 'error', title: 'Incomplete Details', html: 'Please complete: ' + errors.join(', ') });
            return;
        }

        Swal.fire({ title: 'Saving Cashback...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: 'save_cashback.php',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.status === 'success') {
                        Swal.fire('Saved!', res.message, 'success').then(() => {
                            window.location.href = '../cashback-list.php';
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                } catch(e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Invalid server response.'
                    });
                    console.error('Raw Response:', response);
                }
            }
        });
    });
});
</script>
</body>
</html>
