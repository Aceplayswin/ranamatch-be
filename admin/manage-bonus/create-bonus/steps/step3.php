<div id="step3" class="wizard-step-content" style="display: none;">
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Language</label>
            <select name="lang_code" class="cus-sel">
                <option value="en" <?php echo (!$is_edit || $bonus_content['lang_code'] == 'en') ? 'selected' : ''; ?>>English</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Bonus Title <span>*</span></label>
            <input type="text" name="title" class="cus-inp" required placeholder="Display title for players" value="<?php echo $is_edit ? $bonus_content['title'] : ''; ?>">
        </div>

        <div class="col-md-12">
            <label class="form-label">Bonus Description <span>*</span></label>
            <textarea name="description" class="cus-txt" required placeholder="Describe the bonus benefits..."><?php echo $is_edit ? $bonus_content['description'] : ''; ?></textarea>
        </div>

        <div class="col-md-12">
            <label class="form-label">Bonus Image</label>
            <div class="image-upload-box">
                <?php if($is_edit && !empty($bonus_content['image_path'])): ?>
                    <div class="mb-3 current-image-preview">
                        <label class="form-label text-info" style="font-size: 8px;">CURRENT IMAGE</label>
                        <img src="../../../<?php echo $bonus_content['image_path']; ?>" alt="Current Image" style="max-width: 200px; border-radius: 10px; border: 1px solid var(--border-dim);">
                    </div>
                <?php endif; ?>
                
                <!-- New Image Preview Container -->
                <div id="new_image_preview_container" class="mb-3" style="display:none;">
                    <label class="form-label text-success" style="font-size: 8px;">NEW PREVIEW</label>
                    <img id="new_image_preview" src="" alt="New Preview" style="max-width: 200px; border-radius: 10px; border: 2px solid var(--accent-blue);">
                </div>

                <input type="file" name="bonus_image" id="bonus_image" class="d-none crop-upload" data-ratio="2" accept="image/*">
                <label for="bonus_image" class="upload-trigger">
                    <i class='bx bx-cloud-upload' style="font-size: 40px; color: var(--accent-blue);"></i>
                    <span>Click to upload new image</span>
                    <small style="color:var(--accent-blue); font-weight:bold;">Required size: 800x400px (2:1 ratio) (PNG/JPG)</small>
                </label>
                <input type="hidden" name="existing_image" value="<?php echo $is_edit ? $bonus_content['image_path'] : ''; ?>">
            </div>
        </div>

        <div class="col-md-12">
            <label class="form-label">Terms & Conditions <span>*</span></label>
            <textarea name="terms" class="cus-txt" style="height: 120px;" required placeholder="Detailed terms and conditions..."><?php echo $is_edit ? $bonus_content['terms_conditions'] : ''; ?></textarea>
        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important;">
        <button type="button" class="btn-prev" style="background: transparent; border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="button" class="action-btn next-step" data-next="4">
            Next Step <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
