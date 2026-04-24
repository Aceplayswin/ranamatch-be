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
$bonus_id = $is_edit ? (int)$_GET['id'] : 0;
$bonus_data = [];
$bonus_content = [];
$bonus_abuse = [];
$bonus_providers = [];

if ($is_edit) {
    // Main data
    $res = mysqli_query($conn, "SELECT * FROM tbl_bonuses WHERE id = $bonus_id");
    if ($res) $bonus_data = mysqli_fetch_assoc($res);
    
    // Content (en)
    $res = mysqli_query($conn, "SELECT * FROM tbl_bonus_content WHERE bonus_id = $bonus_id AND lang_code='en'");
    if ($res) $bonus_content = mysqli_fetch_assoc($res);
    
    // Abuse
    $res = mysqli_query($conn, "SELECT * FROM tbl_bonus_abuse WHERE bonus_id = $bonus_id");
    if ($res) $bonus_abuse = mysqli_fetch_assoc($res);
    
    // Providers
    $res = mysqli_query($conn, "SELECT * FROM tbl_bonus_providers WHERE bonus_id = $bonus_id");
    if ($res) {
        while ($p = mysqli_fetch_assoc($res)) {
            $bonus_providers[$p['provider_name']] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Create Bonus</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    
    <style>
<?php include "../../components/theme-variables.php"; ?>
/* Page specific variable overrides only if needed */
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
        
        /* Custom scrollbar for sidebar */
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
        .wizard-content { 
            flex-grow: 1; 
            padding: 10px 20px; 
            overflow-y: auto; 
            height: 100%;
        }
        
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
                white-space: nowrap !important; gap: 8px !important;
                scrollbar-width: none;
            }
            .wizard-sidebar::-webkit-scrollbar { display: none; }
            
            .wizard-step {
                min-width: 130px !important; padding: 8px 12px !important;
                flex-direction: row !important; align-items: center !important; gap: 8px !important;
                background: var(--input-bg) !important;
            }
            .wizard-step span { font-size: 11px !important; }
            .wizard-step small { margin-bottom: 0 !important; }

            .wizard-content { padding: 15px 0 !important; height: auto !important; overflow: visible !important; }
            .dash-header { margin-bottom: 20px !important; }
            .dash-title h1 { font-size: 20px !important; margin-left: 35px; }
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
        .cus-sel option {
            background-color: var(--panel-bg) !important;
            color: var(--text-main) !important;
        }
        .cus-inp:focus, .cus-sel:focus, .cus-txt:focus {
            border-color: var(--accent-blue) !important; background: var(--input-bg) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important; outline: none;
        }
        .cus-txt { height: 80px !important; padding: 12px !important; resize: none; }

        .hint-text {
            font-size: 12px; color: var(--text-dim); margin-top: 6px;
            display: flex; align-items: flex-start; gap: 6px; line-height: 1.4;
        }
        .hint-text i { font-size: 16px; color: var(--accent-blue); }

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

        @media (max-width: 900px) {
        .wizard-step { flex-shrink: 0; min-width: 140px; padding: 12px; }
        }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Bonus > Promotion Builder</span>
                <h1>Create Bonus</h1>
            </div>
        </div>

        <div style="padding-bottom: 100px;">
            <div class="glass-card">
                <!-- Outer Wizard Sidebar -->
                <div class="wizard-sidebar hide-native-scrollbar">
                    <div class="wizard-step active" data-step="1">
                        <span>General</span>
                        <small>Step 1 of 5</small>
                    </div>
                    <div class="wizard-step" data-step="2">
                        <span>Redemption</span>
                        <small>Step 2 of 5</small>
                    </div>
                    <div class="wizard-step" data-step="3">
                        <span>Coupon Sets</span>
                        <small>Step 3 of 5</small>
                    </div>
                    <div class="wizard-step" data-step="4">
                        <span>Abuse</span>
                        <small>Step 4 of 5</small>
                    </div>
                    <div class="wizard-step" data-step="5">
                        <span>Wagering Conditions</span>
                        <small>Step 5 of 5</small>
                    </div>
                </div>

                <!-- Main Form Area -->
                <div class="wizard-content hide-native-scrollbar">
                    <form id="bonusForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>">
                        <input type="hidden" name="edit_id" value="<?php echo $bonus_id; ?>">
                        
                        <?php include "steps/step1.php"; ?>
                        <?php include "steps/step2.php"; ?>
                        <?php include "steps/step3.php"; ?>
                        <?php include "steps/step4.php"; ?>
                        <?php include "steps/step5.php"; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Step Switching Logic
    const steps = document.querySelectorAll('.wizard-step');
    const contents = document.querySelectorAll('.wizard-step-content');
    const nextBtns = document.querySelectorAll('.next-step');
    const prevBtns = document.querySelectorAll('.btn-prev');

    function goToStep(stepNumber) {
        stepNumber = parseInt(stepNumber);
        // Update Sidebar
        steps.forEach(s => s.classList.remove('active'));
        const activeLink = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
        if(activeLink) activeLink.classList.add('active');

        // Update Content
        contents.forEach(c => c.style.display = 'none');
        const nextContent = document.getElementById(`step${stepNumber}`);
        if(nextContent) {
            nextContent.style.display = 'block';
            document.querySelector('.wizard-content').scrollTop = 0;
        }
    }

    steps.forEach(step => {
        step.addEventListener('click', () => {
            const stepNum = step.getAttribute('data-step');
            goToStep(stepNum);
        });
    });

    nextBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const nextStep = btn.getAttribute('data-next');
            goToStep(nextStep);
        });
    });

    prevBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const currentStepLine = document.querySelector('.wizard-step.active');
            const currentStep = parseInt(currentStepLine.getAttribute('data-step'));
            if(currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
    });

    // Handle Toggles
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('toggle-btn')) {
            const btn = e.target;
            const group = btn.closest('.toggle-group');
            const btns = group.querySelectorAll('.toggle-btn');
            const hiddenInp = group.querySelector('input[type="hidden"]');
            
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            hiddenInp.value = btn.getAttribute('data-val');
        }
    });
</script>

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
    
    function closeCropModal(clearInput = true) {
        document.getElementById('cropModal').style.display = 'none';
        if (currentCropper) {
            currentCropper.destroy();
            currentCropper = null;
        }
        if (currentInput && clearInput) {
            currentInput.value = '';
        }
    }
    
    document.getElementById('btnApplyCrop').addEventListener('click', function() {
        if (!currentCropper) return;
        
        const canvas = currentCropper.getCroppedCanvas({
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        canvas.toBlob(function(blob) {
            const dataTransfer = new DataTransfer();
            const file = new File([blob], currentFileName, { type: 'image/jpeg' });
            dataTransfer.items.add(file);
            currentInput.files = dataTransfer.files;
            
            // --- NEW PREVIEW LOGIC START ---
            const previewImg = document.getElementById('new_image_preview');
            const previewContainer = document.getElementById('new_image_preview_container');
            const currentPreview = document.querySelector('.current-image-preview');
            
            if (previewImg && previewContainer) {
                previewImg.src = canvas.toDataURL('image/jpeg');
                previewContainer.style.display = 'block';
                // Hide the "old" image if we have a new one
                if (currentPreview) currentPreview.style.display = 'none';
            }
            // --- NEW PREVIEW LOGIC END ---

            const labelText = currentInput.closest('.image-upload-box')?.querySelector('.upload-trigger span');
            if (labelText) {
                labelText.textContent = currentFileName + " (Cropped)";
            }
            
            closeCropModal(false);
        }, 'image/jpeg', 0.9);
    });
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$(document).ready(function() {
    // Step Switching Logic
    const steps = $('.wizard-step');
    const contents = $('.wizard-step-content');
    const nextBtns = $('.next-step');
    const prevBtns = $('.btn-prev');

    function goToStep(stepNumber) {
        stepNumber = parseInt(stepNumber);
        // Update Sidebar
        steps.removeClass('active');
        $(`.wizard-step[data-step="${stepNumber}"]`).addClass('active');

        // Update Content
        contents.hide();
        const nextContent = $(`#step${stepNumber}`);
        if(nextContent.length) {
            nextContent.show();
            $('.wizard-content').scrollTop(0);
        }
    }

    steps.on('click', function() {
        const stepNum = $(this).data('step');
        goToStep(stepNum);
    });

    nextBtns.on('click', function() {
        const nextStep = $(this).data('next');
        goToStep(nextStep);
    });

    prevBtns.on('click', function() {
        const currentStep = $('.wizard-step.active').data('step');
        if(currentStep > 1) {
            goToStep(currentStep - 1);
        }
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

    // Final Form Submission
    $('#bonusForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validation - check required fields by step
        const errors = [];
        let firstErrorStep = null;

        // Step 1: General
        if (!$('input[name="name"]').val().trim()) { errors.push('Bonus Name'); if(!firstErrorStep) firstErrorStep = 1; }
        if (!$('select[name="type"]').val()) { errors.push('Bonus Type'); if(!firstErrorStep) firstErrorStep = 1; }

        // Step 2: Redemption
        if (!$('select[name="redemption_type"]').val()) { errors.push('Redemption Type'); if(!firstErrorStep) firstErrorStep = 2; }
        if (!$('input[name="redemption_amount"]').val()) { errors.push('Redemption Amount'); if(!firstErrorStep) firstErrorStep = 2; }
        if (!$('select[name="bonus_category"]').val()) { errors.push('Bonus Category'); if(!firstErrorStep) firstErrorStep = 2; }

        // Step 3: Coupon Sets
        if (!$('input[name="title"]').val().trim()) { errors.push('Bonus Title'); if(!firstErrorStep) firstErrorStep = 3; }
        if (!$('textarea[name="description"]').val().trim()) { errors.push('Bonus Description'); if(!firstErrorStep) firstErrorStep = 3; }
        if (!$('textarea[name="terms"]').val().trim()) { errors.push('Terms & Conditions'); if(!firstErrorStep) firstErrorStep = 3; }

        if (errors.length > 0) {
            // Navigate to the first step with errors
            goToStep(firstErrorStep);

            // Build the error list
            let errorList = '<div style="text-align: left; margin-top: 20px;">';
            errorList += '<p style="margin-bottom: 15px; font-size: 14px; color: #94a3b8;">Please complete the following sections:</p>';
            errorList += '<div style="display: grid; grid-template-columns: 1fr; gap: 10px;">';
            errors.forEach(function(err) {
                errorList += '<div style="display: flex; align-items: center; gap: 10px; background: rgba(244, 63, 94, 0.1); padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(244, 63, 94, 0.2);">';
                errorList += '<span style="color: #f43f5e; font-size: 18px;"><i class="bx bx-error-circle"></i></span>';
                errorList += '<span style="color: #f1f5f9; font-size: 13px; font-weight: 600;">' + err + '</span>';
                errorList += '</div>';
            });
            errorList += '</div></div>';

            Swal.fire({
                icon: 'error',
                title: 'Submission Incomplete',
                html: errorList,
                showConfirmButton: true,
                confirmButtonText: 'Review Now',
                customClass: {
                    confirmButton: 'premium-swal-confirm'
                }
            });
            return;
        }

        const formData = new FormData(this);
        
        Swal.fire({
            title: 'Saving Bonus...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'save_bonus.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text',
            success: function(response) {
                try {
                    // Try to extract JSON from response (in case of stray output)
                    var jsonMatch = response.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        const res = JSON.parse(jsonMatch[0]);
                        if (res.status === 'success') {
                            Swal.fire('Saved!', res.message, 'success').then(() => {
                                window.location.href = '../bonus-list/?v=' + Date.now();
                            });
                        } else {
                            Swal.fire('Error', res.message || 'Failed to save bonus', 'error');
                        }
                    } else {
                        console.log('Raw response:', response);
                        Swal.fire('Error', 'No valid JSON in response. Check console.', 'error');
                    }
                } catch (e) {
                    console.log('Parse error. Raw response:', response);
                    Swal.fire('Error', 'Parse error. Check browser console for details.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error, xhr.responseText);
                Swal.fire('Error', 'Server error: ' + error, 'error');
            }
        });
    });
});
</script>

</body>
</html>
