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
    <title><?php echo $APP_NAME; ?>: Affiliate Settings</title>
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
                <span class="dash-breadcrumb">Affiliate Management</span>
                <h1>Affiliate Program Settings</h1>
            </div>
            <a href="../" class="btn-modern btn-primary-modern">
                <i class='bx bx-arrow-back fs-5'></i> Directory
            </a>
        </div>

        <form onsubmit="saveAffSettings(event)">
            <div class="settings-card">
                <h5><i class='bx bx-slider-alt text-info'></i> Default Acquisition Commission Defaults</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Rev Share Rate (%)</label>
                        <input type="number" id="revshare_pct" step="0.1" class="cus-inp-bright" value="35.0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">CPA Bonus Amount (₹)</label>
                        <input type="number" id="cpa_amount" step="1" class="cus-inp-bright" value="50" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">CPA Min Deposit Threshold (₹)</label>
                        <input type="number" id="cpa_threshold" step="1" class="cus-inp-bright" value="100" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Referral Cookie Duration (Days)</label>
                        <input type="number" id="cookie_days" class="cus-inp-bright" value="30" required>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h5><i class='bx bx-money-withdraw text-success'></i> Payout & Hold Period Rules</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Minimum Commission Payout (₹)</label>
                        <input type="number" id="min_payout" class="cus-inp-bright" value="1000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-bright mb-1">Payout Lock Hold Period (Days)</label>
                        <input type="number" id="payout_hold_days" class="cus-inp-bright" value="7" required>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" id="save_btn" class="btn-modern btn-green-modern">
                    <i class='bx bx-check'></i> Save Settings
                </button>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAffSettings();
});

function loadAffSettings() {
    fetch('api_settings.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const s = data.data;
                document.getElementById('revshare_pct').value = s.affiliate_default_revshare_pct || 35.0;
                document.getElementById('cpa_amount').value = s.affiliate_default_cpa_amount || 50;
                document.getElementById('cpa_threshold').value = s.affiliate_cpa_min_deposit_threshold || 100;
                document.getElementById('cookie_days').value = s.affiliate_cookie_duration_days || 30;
                document.getElementById('min_payout').value = s.affiliate_minimum_payout || 1000;
                document.getElementById('payout_hold_days').value = s.affiliate_payout_hold_days !== undefined ? s.affiliate_payout_hold_days : 7;
            }
        })
        .catch(err => console.error('Error loading settings:', err));
}

function saveAffSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('save_btn');
    btn.disabled = true;

    const payload = {
        affiliate_default_revshare_pct: parseFloat(document.getElementById('revshare_pct').value),
        affiliate_default_cpa_amount: parseFloat(document.getElementById('cpa_amount').value),
        affiliate_cpa_min_deposit_threshold: parseFloat(document.getElementById('cpa_threshold').value),
        affiliate_cookie_duration_days: parseInt(document.getElementById('cookie_days').value),
        affiliate_minimum_payout: parseFloat(document.getElementById('min_payout').value),
        affiliate_payout_hold_days: parseInt(document.getElementById('payout_hold_days').value)
    };

    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Server returned an unexpected response format.');
        }

        if (res.status === 401) {
            throw new Error('Admin session expired. Please refresh or re-login.');
        }

        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || ('Server error (' + res.status + ')'));
        }
        return data;
    })
    .then(data => {
        btn.disabled = false;
        Swal.fire({
            icon: 'success',
            title: 'Settings Saved',
            text: 'Payout Lock Hold Period & Affiliate Parameters updated successfully.',
            timer: 1800,
            showConfirmButton: false
        });
    })
    .catch(err => {
        btn.disabled = false;
        Swal.fire({
            icon: 'error',
            title: 'Save Failed',
            text: err.message || 'Error updating settings. Please try again.'
        });
    });
}
</script>
</body>
</html>
