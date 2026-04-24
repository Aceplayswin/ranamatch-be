<div id="step4" class="wizard-step-content" style="display: none;">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Turnover (Wagering Multiplier) <span>*</span></label>
            <input type="number" name="wagering_multiplier" class="cus-inp" step="0.1" placeholder="e.g. 1.0" required value="<?php echo $cb_data['wagering_multiplier'] ?? '1.0'; ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Max Profit from Bonus (₹)</label>
            <input type="number" name="max_profit" class="cus-inp" placeholder="e.g. 10000" value="<?php echo $cb_data['max_profit'] ?? '0'; ?>">
        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important;">
        <button type="button" class="btn-prev" style="background: transparent; border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="submit" class="action-btn" style="width: auto; padding: 0 40px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
            Finalize Promotion <i class='bx bx-check-double'></i>
        </button>
    </div>
</div>
