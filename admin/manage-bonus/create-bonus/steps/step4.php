<div id="step4" class="wizard-step-content" style="display: none;">
    <div class="row g-3 justify-content-center">
        <div class="col-md-11">
            
            <div class="row align-items-center mb-3">
                <div class="col-md-6">
                    <label class="form-label mb-0" style="text-transform: none; font-size: 13px; font-weight: 600; color: var(--text-main); letter-spacing: normal;">Do not redeem if coupon has been redeemed by a player as similar as (%)</label>
                </div>
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        <input type="number" name="similarity_percent" class="cus-inp" style="height: 38px;" value="<?php echo ($is_edit && isset($bonus_abuse['similarity_threshold_percent'])) ? $bonus_abuse['similarity_threshold_percent'] : '30'; ?>" placeholder="30">
                        <span style="color: var(--text-dim); font-weight: 700; font-size: 13px;">%</span>
                    </div>
                </div>
            </div>

            <div class="row align-items-center mb-3">
                <div class="col-md-6">
                    <label class="form-label mb-0" style="text-transform: none; font-size: 13px; font-weight: 600; color: var(--text-main); letter-spacing: normal;">Exclude all players who have deposited real money and subsequently played for real money in the last (days)</label>
                </div>
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        <input type="number" name="exclude_deposited_played_days" class="cus-inp" style="height: 38px;" value="<?php echo ($is_edit && isset($bonus_abuse['exclude_days_deposited_played'])) ? $bonus_abuse['exclude_days_deposited_played'] : '3'; ?>" placeholder="3">
                        <span style="color: var(--text-dim); font-weight: 700; font-size: 13px;">Days</span>
                    </div>
                </div>
            </div>

            <div class="row align-items-center mb-3">
                <div class="col-md-6">
                    <label class="form-label mb-0" style="text-transform: none; font-size: 13px; font-weight: 600; color: var(--text-main); letter-spacing: normal;">Exclude all players who have played for real money in the last (days)</label>
                </div>
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        <input type="number" name="exclude_played_days" class="cus-inp" style="height: 38px;" value="<?php echo ($is_edit && isset($bonus_abuse['exclude_days_played'])) ? $bonus_abuse['exclude_days_played'] : ''; ?>" placeholder="">
                        <span style="color: var(--text-dim); font-weight: 700; font-size: 13px;">Days</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important; width: 92%; margin-left: 4%;">
        <button type="button" class="btn-prev" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="button" class="action-btn next-step" data-next="5">
            Next <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
