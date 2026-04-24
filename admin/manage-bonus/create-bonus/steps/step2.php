<div id="step2" class="wizard-step-content" style="display: none;">
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Redemption Type <span>*</span></label>
            <select name="redemption_type" class="cus-sel" required>
                <option value="" hidden>Select Redemption Type</option>
                <option value="percent_deposit" <?php echo ($is_edit && $bonus_data['redemption_type'] == 'percent_deposit') ? 'selected' : ''; ?>>% of deposit</option>
                <option value="fixed_deposit" <?php echo ($is_edit && $bonus_data['redemption_type'] == 'fixed_deposit') ? 'selected' : ''; ?>>Fixed amount on deposit</option>
                <option value="fixed_redemption" <?php echo ($is_edit && $bonus_data['redemption_type'] == 'fixed_redemption') ? 'selected' : ''; ?>>Fixed amount on redemption</option>
                <option value="referral_bonus" <?php echo ($is_edit && $bonus_data['redemption_type'] == 'referral_bonus') ? 'selected' : ''; ?>>Referral bonus</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Redemption Amount <span>*</span></label>
            <input type="number" name="redemption_amount" class="cus-inp" required value="<?php echo $is_edit ? $bonus_data['amount'] : ''; ?>">
        </div>

        <div class="col-md-12">
            <label class="form-label">Maximum Redeemable Value</label>
            <input type="number" name="max_redeem_value" class="cus-inp" value="<?php echo $is_edit ? $bonus_data['max_redeem_value'] : ''; ?>">
        </div>

        <div class="col-md-12">
            <label class="form-label">Minimum Deposit</label>
            <input type="number" name="min_deposit" class="cus-inp" value="<?php echo $is_edit ? $bonus_data['min_deposit'] : ''; ?>">
        </div>

        <div class="col-md-12">
            <label class="form-label">Payment Methods</label>
            <select name="payment_methods" class="cus-sel">
                <option value="" hidden>Select Payment Method</option>
                <option value="all" <?php echo ($is_edit && $bonus_data['payment_methods'] == 'all') ? 'selected' : ''; ?>>All Methods</option>
                <option value="upi" <?php echo ($is_edit && $bonus_data['payment_methods'] == 'upi') ? 'selected' : ''; ?>>UPI</option>
                <option value="crypto" <?php echo ($is_edit && $bonus_data['payment_methods'] == 'crypto') ? 'selected' : ''; ?>>Crypto</option>
                <option value="bank" <?php echo ($is_edit && $bonus_data['payment_methods'] == 'bank') ? 'selected' : ''; ?>>Bank Transfer</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Bonus Category <span>*</span></label>
            <select name="bonus_category" class="cus-sel" required>
                <option value="" hidden>Select Category</option>
                <option value="casino" <?php echo ($is_edit && $bonus_data['bonus_category'] == 'casino') ? 'selected' : ''; ?>>Casino</option>
                <option value="sports" <?php echo ($is_edit && $bonus_data['bonus_category'] == 'sports') ? 'selected' : ''; ?>>Sports</option>
                <option value="casino_sports" <?php echo ($is_edit && $bonus_data['bonus_category'] == 'casino_sports') ? 'selected' : ''; ?>>Casino + Sports (Both)</option>
            </select>
        </div>

        <div class="col-md-12 mt-4 d-flex align-items-center justify-content-between">
            <div style="flex: 1;">
                <label class="form-label mb-0">Redeem Only On First Deposit</label>
                <div id="firstDepositHint" style="display:none; color: #f43f5e; font-size: 10px; font-weight: 700; margin-top: 4px;">!! Not allowed for Redeposit Bonuses !!</div>
            </div>
            <div class="toggle-group" id="firstDepositToggle">
                <button type="button" class="toggle-btn <?php echo ($is_edit && $bonus_data['is_first_deposit']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                <button type="button" class="toggle-btn <?php echo (!$is_edit || !$bonus_data['is_first_deposit']) ? 'active' : ''; ?>" data-val="no">No</button>
                <input type="hidden" name="is_first_deposit" id="isFirstDepositInput" value="<?php echo ($is_edit && $bonus_data['is_first_deposit']) ? 'yes' : 'no'; ?>">
            </div>
        </div>

        <div class="col-md-12 d-flex align-items-center justify-content-between">
            <label class="form-label mb-0">Redeem Only On Second Deposit</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn <?php echo ($is_edit && $bonus_data['is_second_deposit']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                <button type="button" class="toggle-btn <?php echo (!$is_edit || !$bonus_data['is_second_deposit']) ? 'active' : ''; ?>" data-val="no">No</button>
                <input type="hidden" name="is_second_deposit" value="<?php echo ($is_edit && $bonus_data['is_second_deposit']) ? 'yes' : 'no'; ?>">
            </div>
        </div>

        <div class="col-md-12 d-flex align-items-center justify-content-between">
            <label class="form-label mb-0">Redeem Only On Third Deposit</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn <?php echo ($is_edit && $bonus_data['is_third_deposit']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                <button type="button" class="toggle-btn <?php echo (!$is_edit || !$bonus_data['is_third_deposit']) ? 'active' : ''; ?>" data-val="no">No</button>
                <input type="hidden" name="is_third_deposit" value="<?php echo ($is_edit && $bonus_data['is_third_deposit']) ? 'yes' : 'no'; ?>">
            </div>
        </div>

        <div class="col-md-12 d-flex align-items-center justify-content-between">
            <label class="form-label mb-0">Redeem This Coupon Only For New Players</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn <?php echo ($is_edit && $bonus_data['is_new_player_only']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                <button type="button" class="toggle-btn <?php echo (!$is_edit || !$bonus_data['is_new_player_only']) ? 'active' : ''; ?>" data-val="no">No</button>
                <input type="hidden" name="is_new_player_only" value="<?php echo ($is_edit && $bonus_data['is_new_player_only']) ? 'yes' : 'no'; ?>">
            </div>
        </div>

        <div class="col-md-12 d-flex align-items-center justify-content-between">
            <label class="form-label mb-0">Auto Redeem This Coupon On Login</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn <?php echo ($is_edit && $bonus_data['is_auto_redeem']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                <button type="button" class="toggle-btn <?php echo (!$is_edit || !$bonus_data['is_auto_redeem']) ? 'active' : ''; ?>" data-val="no">No</button>
                <input type="hidden" name="is_auto_redeem" value="<?php echo ($is_edit && $bonus_data['is_auto_redeem']) ? 'yes' : 'no'; ?>">
            </div>
        </div>

        <!-- Allow Redemption From -->
        <div class="col-md-12 mt-4">
            <label class="form-label mb-3">Allow Redemption From</label>
            
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span style="font-size: 13px; color: var(--text-dim);">Download</span>
                <div class="toggle-group">
                    <button type="button" class="toggle-btn <?php echo (!$is_edit || $bonus_data['allow_download']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                    <button type="button" class="toggle-btn <?php echo ($is_edit && !$bonus_data['allow_download']) ? 'active' : ''; ?>" data-val="no">No</button>
                    <input type="hidden" name="allow_download" value="<?php echo (!$is_edit || $bonus_data['allow_download']) ? 'yes' : 'no'; ?>">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3">
                <span style="font-size: 13px; color: var(--text-dim);">Instant Play</span>
                <div class="toggle-group">
                    <button type="button" class="toggle-btn <?php echo (!$is_edit || $bonus_data['allow_instant']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                    <button type="button" class="toggle-btn <?php echo ($is_edit && !$bonus_data['allow_instant']) ? 'active' : ''; ?>" data-val="no">No</button>
                    <input type="hidden" name="allow_instant" value="<?php echo (!$is_edit || $bonus_data['allow_instant']) ? 'yes' : 'no'; ?>">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between">
                <span style="font-size: 13px; color: var(--text-dim);">Mobile</span>
                <div class="toggle-group">
                    <button type="button" class="toggle-btn <?php echo (!$is_edit || $bonus_data['allow_mobile']) ? 'active' : ''; ?>" data-val="yes">Yes</button>
                    <button type="button" class="toggle-btn <?php echo ($is_edit && !$bonus_data['allow_mobile']) ? 'active' : ''; ?>" data-val="no">No</button>
                    <input type="hidden" name="allow_mobile" value="<?php echo (!$is_edit || $bonus_data['allow_mobile']) ? 'yes' : 'no'; ?>">
                </div>
            </div>
        </div>

        <!-- Redemption Scheduling -->
        <div class="col-md-12 mt-4">
            <label class="form-label mb-3">Redemption Scheduling</label>
            
            <div class="mb-4">
                <span class="d-block mb-3" style="font-size: 11px; font-weight: 800; color: var(--text-main);">Valid Date Range <span>*</span></span>
                <div class="row g-3">
                    <div class="col-md-12 d-flex align-items-center gap-3">
                        <span style="width: 80px; font-size: 13px; color: var(--text-dim);">Start</span>
                        <input type="date" name="start_date" class="cus-inp" style="flex: 1;" value="<?php echo $is_edit ? date('Y-m-d', strtotime($bonus_data['start_at'])) : ''; ?>">
                        <input type="time" name="start_time" class="cus-inp" style="flex: 0.5;" value="<?php echo $is_edit ? date('H:i', strtotime($bonus_data['start_at'])) : ''; ?>">
                    </div>
                    <div class="col-md-12 d-flex align-items-center gap-3">
                        <span style="width: 80px; font-size: 13px; color: var(--text-dim);">End</span>
                        <input type="date" name="end_date" class="cus-inp" style="flex: 1;" value="<?php echo $is_edit ? date('Y-m-d', strtotime($bonus_data['end_at'])) : ''; ?>">
                        <input type="time" name="end_time" class="cus-inp" style="flex: 0.5;" value="<?php echo $is_edit ? date('H:i', strtotime($bonus_data['end_at'])) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <span class="d-block mb-3" style="font-size: 11px; font-weight: 800; color: var(--text-main);">Redemption Pattern <span>*</span></span>
                <?php 
                $days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
                $pattern_data = $is_edit ? json_decode($bonus_data['redemption_pattern'], true) : [];
                foreach ($days as $d) {
                    $is_checked = isset($pattern_data[$d]);
                    ?>
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-2 d-flex align-items-center gap-2">
                            <input type="checkbox" name="pattern_days[]" value="<?php echo $d; ?>" class="provider-check" <?php echo $is_checked ? 'checked' : ''; ?>>
                            <span style="font-size: 13px; color: var(--text-dim);"><?php echo $d; ?></span>
                        </div>
                        <div class="col-md-5 d-flex align-items-center gap-2">
                            <span style="font-size: 12px; color: var(--text-dim);">Start</span>
                            <input type="time" name="pattern_start[<?php echo $d; ?>]" class="cus-inp" style="height: 40px;" value="<?php echo $is_checked ? $pattern_data[$d]['start'] : ''; ?>">
                        </div>
                        <div class="col-md-5 d-flex align-items-center gap-2">
                            <span style="font-size: 12px; color: var(--text-dim);">End</span>
                            <input type="time" name="pattern_end[<?php echo $d; ?>]" class="cus-inp" style="height: 40px;" value="<?php echo $is_checked ? $pattern_data[$d]['end'] : ''; ?>">
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <!-- Maximum Uses -->
        <div class="col-md-12 mt-4">
            <label class="form-label mb-3">Maximum Uses</label>
            
            <div class="mb-4">
                <span class="d-block mb-3" style="font-size: 11px; font-weight: 800; color: var(--text-dim);">Per Player</span>
                <div class="d-flex gap-4">
                    <label class="d-flex align-items-center gap-2" style="font-size: 13px; color: var(--text-main); cursor: pointer;">
                        <input type="radio" name="player_limit_type" value="daily" <?php echo (!$is_edit || $bonus_data['player_limit_type'] == 'daily') ? 'checked' : ''; ?>> Daily Once
                    </label>
                    <label class="d-flex align-items-center gap-2" style="font-size: 13px; color: var(--text-main); cursor: pointer;">
                        <input type="radio" name="player_limit_type" value="alternate" <?php echo ($is_edit && $bonus_data['player_limit_type'] == 'alternate') ? 'selected' : ''; ?>> Once In Alternateday
                    </label>
                    <label class="d-flex align-items-center gap-2" style="font-size: 13px; color: var(--text-main); cursor: pointer;">
                        <input type="radio" name="player_limit_type" value="weekly" <?php echo ($is_edit && $bonus_data['player_limit_type'] == 'weekly') ? 'selected' : ''; ?>> Weekly Once
                    </label>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-12 d-flex align-items-center justify-content-between">
                    <span style="font-size: 13px; color: var(--text-dim);">Per Day</span>
                    <input type="number" name="limit_daily" class="cus-inp" style="width: 150px;" placeholder="0" value="<?php echo $is_edit ? $bonus_data['limit_daily'] : '0'; ?>">
                </div>
                <div class="col-md-12 d-flex align-items-center justify-content-between">
                    <span style="font-size: 13px; color: var(--text-dim);">Per Week</span>
                    <input type="number" name="limit_weekly" class="cus-inp" style="width: 150px;" placeholder="0" value="<?php echo $is_edit ? $bonus_data['limit_weekly'] : '0'; ?>">
                </div>
                <div class="col-md-12 d-flex align-items-center justify-content-between">
                    <span style="font-size: 13px; color: var(--text-dim);">Per Month</span>
                    <input type="number" name="limit_monthly" class="cus-inp" style="width: 150px;" placeholder="0" value="<?php echo $is_edit ? $bonus_data['limit_monthly'] : '0'; ?>">
                </div>
                <div class="col-md-12 d-flex align-items-center justify-content-between">
                    <div style="flex: 1;">
                        <span style="font-size: 13px; color: var(--text-dim);">Maximum Coupon Redemptions</span>
                        <div id="singleUserHint" style="display:none; color: var(--accent-blue); font-size: 10px; font-weight: 700; margin-top: 4px;">(Force 1 for Single User Account)</div>
                    </div>
                    <input type="number" name="limit_total" id="limitTotalInput" class="cus-inp" style="width: 150px;" placeholder="0" value="<?php echo $is_edit ? $bonus_data['limit_total'] : '0'; ?>">
                </div>
            </div>
        </div>

        <script>
        function applyTypeConstraints() {
            const typeSel = document.getElementById('bonusTypeSelect');
            if(!typeSel) return;
            
            const type = typeSel.value;
            const firstDepToggle = document.getElementById('firstDepositToggle');
            const firstDepHint = document.getElementById('firstDepositHint');
            const firstDepInput = document.getElementById('isFirstDepositInput');
            
            const limitTotalInput = document.getElementById('limitTotalInput');
            const singleUserHint = document.getElementById('singleUserHint');

            if(!firstDepToggle || !limitTotalInput) return;

            // Reset
            if(firstDepHint) firstDepHint.style.display = 'none';
            if(singleUserHint) singleUserHint.style.display = 'none';
            limitTotalInput.readOnly = false;
            firstDepToggle.style.pointerEvents = 'auto';
            firstDepToggle.style.opacity = '1';

            if(type === 'redeposit_bonus') {
                // Force First Deposit to NO
                firstDepInput.value = 'no';
                const btns = firstDepToggle.querySelectorAll('.toggle-btn');
                btns.forEach(b => b.classList.remove('active'));
                const noBtn = firstDepToggle.querySelector('[data-val="no"]');
                if(noBtn) noBtn.classList.add('active');
                
                firstDepToggle.style.pointerEvents = 'none';
                firstDepToggle.style.opacity = '0.5';
                if(firstDepHint) {
                    firstDepHint.textContent = "!! Required: NO (Only for Redeposit) !!";
                    firstDepHint.style.display = 'block';
                }
            } else if(type === 'single_account') {
                // Force Limit Total to 1
                limitTotalInput.value = '1';
                limitTotalInput.readOnly = true;
                if(singleUserHint) singleUserHint.style.display = 'block';
            }
        }

        // Listen for changes from step1
        const typeSelect = document.getElementById('bonusTypeSelect');
        if(typeSelect) {
            typeSelect.addEventListener('change', applyTypeConstraints);
        }
        
        // Initial check after a short delay to ensure elements are ready
        setTimeout(applyTypeConstraints, 200);
        </script>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important;">
        <button type="button" class="btn-prev" style="background: transparent; border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="button" class="action-btn next-step" data-next="3">
            Next Step <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
