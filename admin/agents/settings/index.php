<?php
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../../logout-account');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Agent Settings</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../../components/theme-variables.php"; ?>

        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: #f8fafc !important;
            margin: 0; padding: 0; overflow-x: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 16px; flex-wrap: wrap; gap: 14px;
        }
        .dash-title h1 { font-size: 26px; font-weight: 800; color: #ffffff !important; margin: 0; letter-spacing: -0.5px; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: #38bdf8 !important; text-transform: uppercase; letter-spacing: 1.2px; }

        .settings-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.7));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 18px; padding: 26px; margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); backdrop-filter: blur(10px);
        }
        .settings-card h5 {
            font-size: 17px; font-weight: 800; color: #ffffff !important; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }

        .form-label-bright {
            color: #cbd5e1 !important; font-weight: 700; font-size: 12px;
            text-transform: uppercase; letter-spacing: 0.6px;
        }

        .cus-inp-bright {
            background: rgba(15, 23, 42, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px; color: #ffffff !important; padding: 10px 16px;
            font-size: 14px; font-weight: 600; outline: none; width: 100%;
            transition: all 0.2s ease; color-scheme: dark;
        }
        select {
            color-scheme: dark !important;
            background-color: #0f172a !important;
            color: #ffffff !important;
        }
        option, optgroup {
            background-color: #ffffff !important;
            color: #0f172a !important;
            font-weight: 600 !important;
        }
        .cus-inp-bright:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        .btn-modern {
            height: 42px; padding: 0 20px; border-radius: 12px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            text-decoration: none !important; transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.35); }
        .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff !important; }
        .btn-green-modern { background: linear-gradient(135deg, #10b981, #047857); color: #ffffff !important; }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Agent Management</span>
                <h1>Global Agent Program Settings</h1>
            </div>
            <a href="../" class="btn-modern btn-primary-modern">
                <i class='bx bx-arrow-back fs-5'></i> Directory
            </a>
        </div>

        <form onsubmit="saveAgentSettings(event)">
            <div class="settings-card">
                <h5><i class='bx bx-slider-alt text-info'></i> Default Hierarchy Defaults & Caps</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Senior Super Agent Default Share (%)</label>
                        <input type="number" id="seniorSuperShare" step="0.1" class="cus-inp-bright" value="80.0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Super Agent Default Share (%)</label>
                        <input type="number" id="superShare" step="0.1" class="cus-inp-bright" value="70.0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Master Agent Default Share (%)</label>
                        <input type="number" id="masterShare" step="0.1" class="cus-inp-bright" value="60.0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Agent Default Share (%)</label>
                        <input type="number" id="agentShare" step="0.1" class="cus-inp-bright" value="50.0" required>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h5><i class='bx bx-wallet text-success'></i> Credit Float Limits</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Maximum Agent Float Exposure Cap (₹)</label>
                        <input type="number" id="exposureCap" class="cus-inp-bright" value="5000000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Review & Clawback Grace Period (Hours)</label>
                        <input type="number" id="reviewHours" class="cus-inp-bright" value="24" required>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h5><i class='bx bx-calendar-check text-warning'></i> Settlement & Withdrawal Schedule</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-bright mb-1">Settlement Cycle</label>
                        <select id="agentSettlementCycle" class="cus-inp-bright" required>
                            <option value="weekly_monday">Weekly (Every Monday) [Standard]</option>
                            <option value="daily">Daily (Every Midnight)</option>
                            <option value="bi_weekly">Bi-Weekly (1st & 16th)</option>
                            <option value="monthly">Monthly (1st of Month)</option>
                        </select>
                        <small class="text-secondary" style="font-size: 11px;">When net profits are locked and unlocked for withdrawal.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-bright mb-1">Minimum Withdrawal Threshold (₹)</label>
                        <input type="number" id="agentMinPayout" step="100" class="cus-inp-bright" value="1000" required>
                        <small class="text-secondary" style="font-size: 11px;">Agent cannot withdraw if net profit is below this amount.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-bright mb-1">Negative Loss Carryover</label>
                        <select id="agentNegativeCarryover" class="cus-inp-bright" required>
                            <option value="1">Yes (Roll over losses to next cycle)</option>
                            <option value="0">No (Reset net losses to ₹0 each cycle)</option>
                        </select>
                        <small class="text-secondary" style="font-size: 11px;">Recommended: Yes (prevents platform loss on winning streaks).</small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <button type="submit" id="saveSettingsBtn" class="btn-modern btn-green-modern">
                    <i class='bx bx-check'></i> Save Settings
                </button>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', loadAgentSettings);

async function loadAgentSettings() {
    try {
        const res = await fetch('api_settings.php');
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            const d = data.data;
            if (d.agent_default_partnership_pct !== undefined) {
                document.getElementById('agentShare').value = d.agent_default_partnership_pct;
            }
            if (d.agent_review_time_hours !== undefined) {
                document.getElementById('reviewHours').value = d.agent_review_time_hours;
            }
            if (d.agent_settlement_cycle) {
                document.getElementById('agentSettlementCycle').value = d.agent_settlement_cycle;
            }
            if (d.agent_minimum_payout !== undefined) {
                document.getElementById('agentMinPayout').value = d.agent_minimum_payout;
            }
            if (d.agent_negative_carryover !== undefined) {
                document.getElementById('agentNegativeCarryover').value = d.agent_negative_carryover;
            }
        }
    } catch (err) {
        console.error('Error loading settings:', err);
    }
}

async function saveAgentSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('saveSettingsBtn');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Saving...`;

    const payload = {
        agent_default_partnership_pct: parseFloat(document.getElementById('agentShare').value) || 50.0,
        agent_review_time_hours: parseInt(document.getElementById('reviewHours').value) || 24,
        agent_settlement_cycle: document.getElementById('agentSettlementCycle').value,
        agent_minimum_payout: parseFloat(document.getElementById('agentMinPayout').value) || 1000.0,
        agent_negative_carryover: parseInt(document.getElementById('agentNegativeCarryover').value)
    };

    try {
        const res = await fetch('api_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved',
                text: 'Settlement cycle & agent defaults updated successfully.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: data.message || 'Unable to update settings.'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: err.message
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class='bx bx-check'></i> Save Settings`;
    }
}
</script>
</body>
</html>
