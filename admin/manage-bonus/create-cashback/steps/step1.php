<div id="step1" class="wizard-step-content">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Promotion Name <span>*</span></label>
            <input type="text" name="name" class="cus-inp" placeholder="e.g. Weekly VIP Cashback" required value="<?php echo $cb_data['name'] ?? ''; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Display Title <span>*</span></label>
            <input type="text" name="title" class="cus-inp" placeholder="e.g. 10% Back on Casino Losses" required value="<?php echo $cb_data['title'] ?? ''; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Bonus Code (Optional)</label>
            <input type="text" name="coupon_code" class="cus-inp" placeholder="e.g. CASH10" value="<?php echo $cb_data['coupon_code'] ?? ''; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Status</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn <?php echo (!$is_edit || $cb_data['status'] == 'active') ? 'active' : ''; ?>" data-val="active">ACTIVE</button>
                <button type="button" class="toggle-btn <?php echo ($is_edit && $cb_data['status'] == 'inactive') ? 'active' : ''; ?>" data-val="inactive">INACTIVE</button>
                <input type="hidden" name="status" value="<?php echo $cb_data['status'] ?? 'active'; ?>">
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Target Player ID (Optional)</label>
            <input type="text" name="target_user_id" id="targetUserIDInput" class="cus-inp" placeholder="e.g. 5432101 (Leave blank for ALL)" value="<?php echo $cb_data['target_user_id'] ?? ''; ?>">
            <div id="userVerifyHint" class="hint-text" style="display:none; margin-top:8px; padding:8px; border-radius:6px; background:rgba(0,0,0,0.1);">
                <span id="userVerifyText" style="font-size:11px; font-weight:700;"></span>
            </div>
        </div>

        <script>
        let debounceTimer;
        document.getElementById('targetUserIDInput').addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const hint = document.getElementById('userVerifyHint');
            const text = document.getElementById('userVerifyText');
            const userId = this.value.trim();

            if (userId.length < 2) {
                hint.style.display = 'none';
                return;
            }

            hint.style.display = 'flex';
            text.style.color = '#94a3b8';
            text.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Verifying player...";

            debounceTimer = setTimeout(() => {
                fetch(`../create_bonus/get_user_details.php?user_id=${userId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            text.style.color = '#10b981';
                            text.innerHTML = `<i class='bx bxs-check-circle'></i> Verified: ${data.name} (${data.mobile})`;
                        } else {
                            text.style.color = '#f43f5e';
                            text.innerHTML = `<i class='bx bxs-x-circle'></i> User Not Found`;
                        }
                    });
            }, 400);
        });
        </script>

        <div class="col-md-12">
            <label class="form-label">Promotion Banner</label>
            <div class="image-upload-box">
                <?php if($is_edit && !empty($cb_data['image_path'])): ?>
                    <div class="mb-3 current-image-preview">
                        <label class="form-label text-info" style="font-size: 8px;">CURRENT BANNER</label>
                        <img src="../../../<?php echo $cb_data['image_path']; ?>" alt="Current Image" style="max-width: 240px; border-radius: 10px; border: 1px solid var(--border-dim);">
                    </div>
                <?php endif; ?>
                
                <div id="new_image_preview_container" class="mb-3" style="display:none;">
                    <label class="form-label text-success" style="font-size: 8px;">NEW PREVIEW</label>
                    <img id="new_image_preview" src="" alt="New Preview" style="max-width: 240px; border-radius: 10px; border: 2px solid var(--accent-blue);">
                </div>

                <input type="file" name="cb_image" id="cb_image" class="d-none crop-upload" data-ratio="2" accept="image/*">
                <label for="cb_image" class="upload-trigger">
                    <i class='bx bx-cloud-upload' style="font-size: 40px; color: var(--accent-blue);"></i>
                    <span>Click to upload promotion banner</span>
                    <small style="color:var(--accent-blue); font-weight:bold;">Requirement: 800x400px (2:1 ratio)</small>
                </label>
                <input type="hidden" name="existing_image" value="<?php echo $cb_data['image_path'] ?? ''; ?>">
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end">
        <button type="button" class="action-btn next-step" data-next="2">
            Next Step <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
