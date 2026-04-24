<div id="step1" class="wizard-step-content">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Bonus Name <span>*</span></label>
                <input type="text" name="name" class="cus-inp" placeholder="Give it a name..." required value="<?php echo $is_edit ? $bonus_data['name'] : ''; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Bonus Type <span>*</span></label>
                <select name="type" id="bonusTypeSelect" class="cus-sel" required>
                    <option value="" hidden>Select Bonus Type</option>
                    <option value="mass" <?php echo ($is_edit && $bonus_data['type'] == 'mass') ? 'selected' : ''; ?>>Mass (Multiple Users)</option>
                    <option value="single_account" <?php echo ($is_edit && $bonus_data['type'] == 'single_account') ? 'selected' : ''; ?>>Single Account (One User)</option>
                    <option value="redeposit_bonus" <?php echo ($is_edit && $bonus_data['type'] == 'redeposit_bonus') ? 'selected' : ''; ?>>Redeposit Bonus (Loyalty)</option>
                </select>
                <div id="typeHint" class="hint-text" style="display:none; margin-top:10px; padding:10px; background:rgba(59,130,246,0.05); border-radius:8px; border:1px solid rgba(59,130,246,0.1);">
                    <i class='bx bx-info-circle'></i>
                    <span id="typeHintText" style="font-size:11px; font-weight:600;"></span>
                </div>
            </div>

            <div class="col-md-6" id="targetUserIDContainer" style="display:none;">
                <label class="form-label">Target Player ID (ID Only)</label>
                <input type="text" name="target_user_id" id="targetUserIDInput" class="cus-inp" placeholder="e.g. 5432101" value="<?php echo $is_edit ? $bonus_data['target_user_id'] : ''; ?>">
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
                    fetch(`get_user_details.php?user_id=${userId}`)
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

            document.getElementById('bonusTypeSelect').addEventListener('change', function() {
                const hint = document.getElementById('typeHint');
                const text = document.getElementById('typeHintText');
                const targetContainer = document.getElementById('targetUserIDContainer');
                const val = this.value;
                
                if(val) {
                    hint.style.display = 'flex';
                    if(val === 'mass') {
                        text.innerHTML = "MASS: This bonus is available to all eligible users until the shared pool (Max Redemptions) is exhausted.";
                        targetContainer.style.display = 'none';
                    } else if(val === 'single_account') {
                        text.innerHTML = "SINGLE ACCOUNT: This bonus is restricted to a single user redemption only. Perfect for individual rewards.";
                        targetContainer.style.display = 'block';
                    } else if(val === 'redeposit_bonus') {
                        text.innerHTML = "REDEPOSIT: Targets users making their 2nd, 3rd, etc. deposits. Blocked for first-time depositors.";
                        targetContainer.style.display = 'none';
                    }
                } else {
                    hint.style.display = 'none';
                    targetContainer.style.display = 'none';
                }
            });
            // Trigger on load for edit mode
            window.addEventListener('DOMContentLoaded', () => {
                const sel = document.getElementById('bonusTypeSelect');
                if(sel.value) sel.dispatchEvent(new Event('change'));
            });
            </script>

            <div class="col-md-6">
                <label class="form-label">Bonus Code</label>
                <input type="text" name="coupon_code" class="cus-inp" placeholder="Optional fixed promo code" value="<?php echo $is_edit ? $bonus_data['coupon_code'] : ''; ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Is Published</label>
                <div class="toggle-group">
                    <button type="button" class="toggle-btn <?php echo (!$is_edit || $bonus_data['is_published']) ? 'active' : ''; ?>" data-val="yes">YES</button>
                    <button type="button" class="toggle-btn <?php echo ($is_edit && !$bonus_data['is_published']) ? 'active' : ''; ?>" data-val="no">NO</button>
                    <input type="hidden" name="is_published" value="<?php echo (!$is_edit || $bonus_data['is_published']) ? 'yes' : 'no'; ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Is Public</label>
                <div class="toggle-group">
                    <button type="button" class="toggle-btn <?php echo (!$is_edit || $bonus_data['is_public']) ? 'active' : ''; ?>" data-val="yes">YES</button>
                    <button type="button" class="toggle-btn <?php echo ($is_edit && !$bonus_data['is_public']) ? 'active' : ''; ?>" data-val="no">NO</button>
                    <input type="hidden" name="is_public" value="<?php echo (!$is_edit || $bonus_data['is_public']) ? 'yes' : 'no'; ?>">
                </div>
            </div>

            <div class="col-md-12">
                <label class="form-label"> Comment</label>
                <textarea name="comment" class="cus-txt" placeholder="Add administrative notes..."><?php echo $is_edit ? $bonus_data['comment'] : ''; ?></textarea>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            <button type="button" class="action-btn next-step" data-next="2">
                Next Step <i class='bx bx-chevron-right'></i>
            </button>
        </div>
</div>
