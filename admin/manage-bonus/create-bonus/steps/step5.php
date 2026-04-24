<div id="step5" class="wizard-step-content" style="display: none;">
    <div class="row g-3 justify-content-center">
        <div class="col-md-11">
            <?php 
            $providers = ["All", "SA", "SSC", "Ezugi Games", "XPG", "Evolution", "Vivo Games", "Awc Games", "Spribe", "Qsoft", "Jili", "CQ9"];
            foreach ($providers as $p) {
                $p_data = isset($bonus_providers[$p]) ? $bonus_providers[$p] : null;
                $is_enabled = ($p_data !== null && $p_data['is_wagering_enabled']);
                $multiplier_val = ($p_data !== null) ? $p_data['wagering_multiplier'] : '';
                ?>
                <div class="row align-items-center mb-3">
                    <div class="col-md-4">
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-main);"><?php echo $p; ?></span>
                    </div>
                    <div class="col-md-3">
                        <div class="toggle-group" style="width: 100px;">
                            <button type="button" class="toggle-btn <?php echo $is_enabled ? 'active' : ''; ?>" style="padding: 4px 0; font-size: 11px;" data-val="yes">Yes</button>
                            <button type="button" class="toggle-btn <?php echo !$is_enabled ? 'active' : ''; ?>" style="padding: 4px 0; font-size: 11px;" data-val="no">No</button>
                            <input type="hidden" name="wagering_enabled[<?php echo $p; ?>]" value="<?php echo $is_enabled ? 'yes' : 'no'; ?>">
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <span style="font-size: 12px; color: var(--text-dim); font-weight: 600;">Wagering Multiplier</span>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="multiplier[<?php echo $p; ?>]" class="cus-inp" style="height: 36px; font-size: 12px;" placeholder="" value="<?php echo $multiplier_val; ?>">
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important; width: 92%; margin-left: 4%;">
        <button type="button" class="btn-prev" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="submit" class="action-btn" style="width: 140px;">
            Save All
        </button>
    </div>
</div>
