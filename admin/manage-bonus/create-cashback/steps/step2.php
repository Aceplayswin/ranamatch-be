<div id="step2" class="wizard-step-content" style="display: none;">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Cashback Percentage (%) <span>*</span></label>
            <input type="number" name="percentage" class="cus-inp" step="0.01" placeholder="e.g. 10.00" required value="<?php echo $cb_data['percentage'] ?? ''; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Minimum Net Loss (₹) <span>*</span></label>
            <input type="number" name="min_loss" class="cus-inp" placeholder="e.g. 500" required value="<?php echo $cb_data['min_loss'] ?? ''; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Max Cashback (₹) - [0 = No Limit]</label>
            <input type="number" name="max_cashback" class="cus-inp" value="<?php echo $cb_data['max_cashback'] ?? '0'; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Claim Mode <span>*</span></label>
            <select name="claim_mode" class="cus-sel">
                <option value="automatic" <?php if(($cb_data['claim_mode'] ?? 'automatic') == 'automatic') echo 'selected'; ?>>Automatic (Direct Wallet)</option>
                <option value="manual" <?php if(($cb_data['claim_mode'] ?? '') == 'manual') echo 'selected'; ?>>Manual (Player Must Claim)</option>
            </select>
        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important;">
        <button type="button" class="btn-prev" style="background: transparent; border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="button" class="action-btn next-step" data-next="3">
            Next Step <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
