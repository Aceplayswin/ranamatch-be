<div id="step3" class="wizard-step-content" style="display: none;">
    <div class="row g-4">
        <div class="col-md-12">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <div class="d-flex gap-2">
                        <?php $s_at = (!empty($cb_data['start_at']) && $cb_data['start_at'] != '0000-00-00 00:00:00') ? strtotime($cb_data['start_at']) : false; ?>
                        <input type="date" name="start_date" class="cus-inp" value="<?php echo $s_at ? date('Y-m-d', $s_at) : ''; ?>" style="flex: 1;">
                        <input type="time" name="start_time" class="cus-inp" value="<?php echo $s_at ? date('H:i', $s_at) : ''; ?>" style="width: 100px;">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date</label>
                    <div class="d-flex gap-2">
                        <?php $e_at = (!empty($cb_data['end_at']) && $cb_data['end_at'] != '0000-00-00 00:00:00') ? strtotime($cb_data['end_at']) : false; ?>
                        <input type="date" name="end_date" class="cus-inp" value="<?php echo $e_at ? date('Y-m-d', $e_at) : ''; ?>" style="flex: 1;">
                        <input type="time" name="end_time" class="cus-inp" value="<?php echo $e_at ? date('H:i', $e_at) : ''; ?>" style="width: 100px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <label class="form-label">Redemption Pattern (Schedule Days) <span>*</span></label>
            <div class="p-3 rounded-xl border border-black/5 dark:border-white/5 bg-white/[0.02]">
                <div class="row g-3">
                    <?php
                    $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    $pattern_data = json_decode($cb_data['redemption_pattern'] ?? '{}', true);
                    foreach($days as $d):
                        $is_active = isset($pattern_data[$d]);
                    ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-lg hover:bg-white/[0.03]">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" name="pattern_days[]" value="<?php echo $d; ?>" class="form-check-input" style="width: 18px; height: 18px;" <?php echo $is_active ? 'checked' : ''; ?>>
                                    <span style="font-size: 13px; font-weight: 700; color: #fff;"><?php echo $d; ?></span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="time" name="pattern_start[<?php echo $d; ?>]" class="cus-inp" style="width: 100px; height: 32px; font-size: 11px;" value="<?php echo $is_active ? $pattern_data[$d]['start'] : '00:00'; ?>">
                                    <span style="color: var(--text-dim); font-size: 10px;">to</span>
                                    <input type="time" name="pattern_end[<?php echo $d; ?>]" class="cus-inp" style="width: 100px; height: 32px; font-size: 11px;" value="<?php echo $is_active ? $pattern_data[$d]['end'] : '23:59'; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: var(--border-dim) !important;">
        <button type="button" class="btn-prev" style="background: transparent; border: 1px solid var(--border-dim); color: var(--text-main); padding: 10px 30px; border-radius: 8px; font-weight: 700;">Previous</button>
        <button type="button" class="action-btn next-step" data-next="4">
            Next Step <i class='bx bx-chevron-right'></i>
        </button>
    </div>
</div>
