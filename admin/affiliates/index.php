<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../logout-account');
    exit;
}

// Live real data metrics from Database
$sumSql = "SELECT 
    COUNT(*) AS total_affiliates,
    COUNT(CASE WHEN status IN ('approved','active') THEN 1 END) AS active_affiliates,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_affiliates,
    COALESCE(SUM(available_balance), 0) AS total_available_balance,
    COALESCE(SUM(pending_balance), 0) AS total_pending_balance,
    COALESCE(SUM(lifetime_earnings), 0) AS total_lifetime_earnings
FROM affiliates";
$sumRes = mysqli_query($conn, $sumSql);
$sumData = $sumRes ? mysqli_fetch_assoc($sumRes) : [];
$activeCount = (int)($sumData['active_affiliates'] ?? 0);
$pendingCount = (int)($sumData['pending_affiliates'] ?? 0);
$lifetimeCommissions = (float)($sumData['total_lifetime_earnings'] ?? 0);
$pendingBal = (float)($sumData['total_pending_balance'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Affiliates</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../components/theme-variables.php"; ?>

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

        /* Modern Glass Stats Cards */
        .stat-grid-clean {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px; margin-bottom: 24px;
        }
        .stat-card-clean {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.7));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card-clean:hover {
            transform: translateY(-3px);
            border-color: rgba(56, 189, 248, 0.4);
            box-shadow: 0 12px 30px rgba(56, 189, 248, 0.15);
        }
        .stat-card-clean h4 { font-size: 22px !important; font-weight: 800; margin: 4px 0 0; color: #ffffff !important; }
        .stat-card-clean span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #cbd5e1 !important; }
        .stat-icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Filter Area */
        .filter-card-clean {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.4), rgba(15, 23, 42, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px; padding: 14px 18px; margin-bottom: 20px;
            display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        input.cus-inp-clean {
            background: rgba(15, 23, 42, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px; color: #ffffff !important; padding: 8px 14px;
            font-size: 13px; font-weight: 600; outline: none; height: 40px;
            transition: all 0.2s ease; color-scheme: dark !important;
        }
        /* Custom Dark Dropdown Component */
        .custom-select-wrapper {
            position: relative; min-width: 155px;
        }
        .custom-select-trigger {
            background: rgba(15, 23, 42, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 10px; color: #ffffff !important;
            padding: 8px 14px; font-size: 13px; font-weight: 600;
            outline: none; height: 40px; width: 100%;
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer; transition: all 0.2s ease; user-select: none;
        }
        .custom-select-trigger:hover, .custom-select-wrapper.open .custom-select-trigger {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .custom-select-options {
            position: absolute; top: 46px; left: 0; right: 0;
            background: #0f172a !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            z-index: 999; display: none; overflow: hidden;
            backdrop-filter: blur(12px); padding: 4px 0;
        }
        .custom-select-wrapper.open .custom-select-options {
            display: block; animation: fadeInDown 0.15s ease-out;
        }
        .custom-option {
            padding: 9px 14px; color: #cbd5e1 !important;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all 0.15s ease;
        }
        .custom-option:hover {
            background: #38bdf8 !important; color: #0f172a !important; font-weight: 700;
        }
        .custom-option.selected {
            background: rgba(56, 189, 248, 0.15) !important; color: #38bdf8 !important; font-weight: 700;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .cus-inp-clean:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .cus-inp-clean::placeholder { color: #94a3b8 !important; font-weight: 500; }

        .btn-approve-pill {
            background: rgba(16, 185, 129, 0.15) !important;
            border: 1px solid rgba(16, 185, 129, 0.45) !important;
            color: #34d399 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            padding: 2px 9px !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
        }
        .btn-approve-pill:hover {
            background: #10b981 !important;
            color: #ffffff !important;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.45) !important;
            transform: translateY(-1px);
        }
        .btn-icon-approve {
            border-color: rgba(16, 185, 129, 0.4) !important;
            color: #34d399 !important;
        }
        .btn-icon-btn {
            width: 34px; height: 34px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 17px; border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05); color: #f8fafc !important;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none !important;
        }
        .btn-icon-btn:hover { background: rgba(56, 189, 248, 0.2); color: #38bdf8 !important; border-color: #38bdf8; transform: scale(1.08); }
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Affiliate Management</span>
                <h1>Affiliates</h1>
            </div>
            <div class="d-flex gap-2">
                <button onclick="triggerCommissionRun()" class="btn-modern btn-pink-modern">
                    <i class='bx bx-rocket fs-5'></i> Run Commissions
                </button>
                <a href="payouts/" class="btn-modern btn-green-modern">
                    <i class='bx bx-money-withdraw fs-5'></i> Payout Approvals
                </a>
                <a href="tickets/" class="btn-modern btn-cyan-modern" style="background: linear-gradient(135deg, #06b6d4, #0284c7); color: #fff;">
                    <i class='bx bx-support fs-5'></i> Support Tickets
                </a>
                <a href="settings/" class="btn-modern btn-primary-modern">
                    <i class='bx bx-cog fs-5'></i> Settings
                </a>
            </div>
        </div>

        <!-- Summary Bar (Live Database Data) -->
        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Active Affiliates</span>
                    <h4 id="statActiveCount"><?php echo $activeCount; ?> Partners<?php echo ($pendingCount > 0 ? " <span style='font-size: 13px; color: #fbbf24;'>({$pendingCount} Pending)</span>" : ""); ?></h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);">
                    <i class='bx bx-share-alt'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Lifetime Commissions</span>
                    <h4 id="statLifetimeEarnings" class="text-bright-emerald">₹ <?php echo number_format($lifetimeCommissions, 2); ?></h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #34d399; background: rgba(52, 211, 153, 0.15);">
                    <i class='bx bx-check-double'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Pending Commissions</span>
                    <h4 id="statPendingBal" style="color: #fbbf24 !important;">₹ <?php echo number_format($pendingBal, 2); ?></h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(251, 191, 36, 0.15);">
                    <i class='bx bx-time-five'></i>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card-clean">
            <input type="text" id="affSearch" class="cus-inp-clean" style="flex: 1; min-width: 220px;" placeholder="Search name, code, company..." onkeyup="filterAffiliates()">
            
            <!-- Tier Filter Custom Dropdown -->
            <div class="custom-select-wrapper" id="tierFilterWrapper">
                <input type="hidden" id="tierFilter" value="">
                <button type="button" class="custom-select-trigger" onclick="toggleCustomDropdown('tierFilterWrapper')">
                    <span class="selected-text" id="tierFilterLabel">All Tiers</span>
                    <i class='bx bx-chevron-down ms-2'></i>
                </button>
                <div class="custom-select-options">
                    <div class="custom-option selected" data-value="" onclick="selectCustomOption('tierFilterWrapper', '', 'All Tiers', filterAffiliates)">All Tiers</div>
                    <div class="custom-option" data-value="Bronze" onclick="selectCustomOption('tierFilterWrapper', 'Bronze', 'Bronze', filterAffiliates)">Bronze</div>
                    <div class="custom-option" data-value="Silver" onclick="selectCustomOption('tierFilterWrapper', 'Silver', 'Silver', filterAffiliates)">Silver</div>
                    <div class="custom-option" data-value="Gold" onclick="selectCustomOption('tierFilterWrapper', 'Gold', 'Gold', filterAffiliates)">Gold</div>
                    <div class="custom-option" data-value="Platinum" onclick="selectCustomOption('tierFilterWrapper', 'Platinum', 'Platinum', filterAffiliates)">Platinum</div>
                </div>
            </div>

            <!-- Status Filter Custom Dropdown -->
            <div class="custom-select-wrapper" id="statusFilterWrapper">
                <input type="hidden" id="statusFilter" value="">
                <button type="button" class="custom-select-trigger" onclick="toggleCustomDropdown('statusFilterWrapper')">
                    <span class="selected-text" id="statusFilterLabel">All Statuses</span>
                    <i class='bx bx-chevron-down ms-2'></i>
                </button>
                <div class="custom-select-options">
                    <div class="custom-option selected" data-value="" onclick="selectCustomOption('statusFilterWrapper', '', 'All Statuses', filterAffiliates)">All Statuses</div>
                    <div class="custom-option" data-value="Pending" onclick="selectCustomOption('statusFilterWrapper', 'Pending', 'Pending', filterAffiliates)">Pending</div>
                    <div class="custom-option" data-value="Active" onclick="selectCustomOption('statusFilterWrapper', 'Active', 'Active', filterAffiliates)">Active</div>
                    <div class="custom-option" data-value="Approved" onclick="selectCustomOption('statusFilterWrapper', 'Approved', 'Approved', filterAffiliates)">Approved</div>
                    <div class="custom-option" data-value="Suspended" onclick="selectCustomOption('statusFilterWrapper', 'Suspended', 'Suspended', filterAffiliates)">Suspended</div>
                </div>
            </div>
        </div>

        <!-- Clean Table -->
        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Affiliate</th>
                        <th>Status</th>
                        <th>Tier</th>
                        <th>Commission Deal</th>
                        <th>Referred Players</th>
                        <th>Earnings (Total / Pending)</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="affTableBody">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="approveAffiliateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a; border:1px solid rgba(255,255,255,.12); color:#fff;">
            <div class="modal-header" style="border-bottom-color:rgba(255,255,255,.1);">
                <h5 class="modal-title">Approve application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-slate-300">Approving <strong id="approveAffiliateName"></strong>. Choose the commission settings for this affiliate.</p>
                <input type="hidden" id="approveAffiliateId">
                <label class="form-label small text-uppercase fw-bold text-slate-400">Commission Type</label>
                <select id="approveDealType" class="form-select mb-3" style="background:#020617; color:#fff; border-color:#334155;">
                    <option value="revenue_share">Revenue share</option>
                    <option value="cpa">CPA</option>
                    <option value="hybrid">Hybrid (CPA + revenue share)</option>
                </select>
                <div id="approveRevshareGroup" class="mb-3">
                    <label class="form-label small text-uppercase fw-bold text-slate-400">Revenue Share %</label>
                    <input type="number" id="approveRevshare" class="form-control" value="30" min="0" max="100" step="0.01" style="background:#020617; color:#fff; border-color:#334155;">
                </div>
                <div id="approveCpaGroup" class="mb-3 d-none">
                    <label class="form-label small text-uppercase fw-bold text-slate-400">CPA Amount (₹)</label>
                    <input type="number" id="approveCpa" class="form-control" value="50" min="0" step="0.01" style="background:#020617; color:#fff; border-color:#334155;">
                </div>
                <label class="form-label small text-uppercase fw-bold text-slate-400">Tier</label>
                <select id="approveTier" class="form-select" style="background:#020617; color:#fff; border-color:#334155;">
                    <option value="bronze">Bronze</option>
                    <option value="silver">Silver</option>
                    <option value="gold">Gold</option>
                    <option value="platinum">Platinum</option>
                </select>
            </div>
            <div class="modal-footer" style="border-top-color:rgba(255,255,255,.1);">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold" onclick="submitAffiliateApproval()">Approve</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let affiliatesData = [];

async function loadAffiliates() {
    try {
        const res = await fetch('api_list.php');
        const data = await res.json();
        if (data.status === 'success') {
            affiliatesData = (data.data || []).map(a => ({
                id: a.id,
                name: a.full_name || a.company_name || a.email,
                fullName: a.full_name || '',
                company: a.company_name || '',
                phone: a.phone || '',
                email: a.email || '',
                code: a.affiliate_code,
                parentName: a.parent_name || '',
                parentCode: a.parent_code || '',
                status: a.status ? (a.status.charAt(0).toUpperCase() + a.status.slice(1)) : 'Pending',
                kycStatus: (a.kyc_status || 'not_submitted').toLowerCase(),
                pendingKycCount: parseInt(a.pending_kyc_count || 0),
                tier: a.tier ? (a.tier.charAt(0).toUpperCase() + a.tier.slice(1)) : 'Bronze',
                deal: a.deal_type === 'cpa' ? `₹${a.cpa_amount} CPA` : `${a.revshare_pct}% Rev Share`,
                players: a.total_referrals || 0,
                totalEarnings: a.lifetime_earnings || 0,
                pendingEarnings: a.pending_balance || 0,
                joined: a.created_at ? a.created_at.split(' ')[0] : ''
            }));

            // Update Summary Card Indicators with real live metrics
            if (data.summary) {
                const activeEl = document.getElementById('statActiveCount');
                if (activeEl) {
                    let text = `${data.summary.active_affiliates} Partners`;
                    if (data.summary.pending_affiliates > 0) {
                        text += ` <span style="font-size: 13px; color: #fbbf24;">(${data.summary.pending_affiliates} Pending)</span>`;
                    }
                    activeEl.innerHTML = text;
                }
                const earnEl = document.getElementById('statLifetimeEarnings');
                if (earnEl) {
                    earnEl.innerText = `₹ ${(data.summary.total_lifetime_earnings || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }
                const pendEl = document.getElementById('statPendingBal');
                if (pendEl) {
                    pendEl.innerText = `₹ ${(data.summary.total_pending_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }
            }

            filterAffiliates();
        }
    } catch (err) {
        console.error("Failed to load affiliates:", err);
    }
}

function escapeQuotes(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function renderAffTable(data) {
    const tbody = document.getElementById('affTableBody');
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No affiliates found.</td></tr>`;
        return;
    }

    data.forEach(aff => {
        let tagClass = 'tag-warning';
        if (aff.tier === 'Silver') tagClass = 'tag-blue';
        else if (aff.tier === 'Gold') tagClass = 'tag-success';
        else if (aff.tier === 'Platinum') tagClass = 'tag-purple';

        let statusTag = 'tag-warning';
        const stLower = (aff.status || '').toLowerCase();
        const isPending = stLower === 'pending';

        if (stLower === 'active' || stLower === 'approved') {
            statusTag = 'tag-success';
        } else if (isPending) {
            statusTag = 'tag-warning';
        } else if (stLower === 'suspended') {
            statusTag = 'tag-warning';
        }

        let statusCellHtml = `<span class="tag ${statusTag}">${aff.status}</span>`;
        if (isPending) {
            statusCellHtml = `
                <div class="d-flex align-items-center gap-2">
                    <span class="tag ${statusTag}">${aff.status}</span>
                    <button type="button" onclick="approveAffiliate('${aff.id}', '${escapeQuotes(aff.name)}')" class="btn-approve-pill" title="Approve Affiliate">
                        <i class='bx bx-check'></i> Approve
                    </button>
                </div>
            `;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="text-bold-white">${escapeQuotes(aff.name)}</div>
                ${aff.company ? `<div style="color: #cbd5e1; font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top: 2px;"><i class='bx bx-buildings text-info'></i> ${escapeQuotes(aff.company)}</div>` : ''}
                ${aff.phone ? `<div style="color: #94a3b8; font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top: 1px;"><i class='bx bx-phone text-success'></i> ${escapeQuotes(aff.phone)}</div>` : ''}
                <div class="d-flex align-items-center gap-1.5 mt-1 flex-wrap">
                    <span style="color:#94a3b8; font-size:11px;">Code:</span> <span class="text-cyan-code">${aff.code}</span>
                    ${aff.parentName ? `
                        <span class="tag tag-blue" style="font-size: 10px; padding: 1px 6px;" title="Referred by Sponsor">
                            <i class='bx bx-user-voice'></i> Ref: ${escapeQuotes(aff.parentName)} (${aff.parentCode})
                        </span>
                    ` : `
                        <span class="tag tag-purple" style="font-size: 10px; padding: 1px 6px;">Direct</span>
                    `}
                    ${aff.pendingKycCount > 0 ? `
                        <a href="detail/?id=${aff.id}" class="badge bg-warning text-dark text-decoration-none" title="Review Pending KYC Documents" style="font-size: 10px; padding: 2px 6px;">
                            <i class='bx bx-time'></i> KYC Review (${aff.pendingKycCount})
                        </a>
                    ` : (aff.kycStatus === 'verified' ? `
                        <span class="badge bg-success" style="font-size: 10px; padding: 2px 6px;"><i class='bx bx-check-shield'></i> KYC Verified</span>
                    ` : '')}
                </div>
            </td>
            <td>${statusCellHtml}</td>
            <td><span class="tag ${tagClass}">${aff.tier}</span></td>
            <td><span class="text-bold-white">${aff.deal}</span></td>
            <td><span class="text-bold-white">${(aff.players || 0).toLocaleString('en-IN')}</span></td>
            <td>
                <div class="text-bright-emerald">₹ ${(aff.totalEarnings || 0).toLocaleString('en-IN')}</div>
                <div class="text-bright-sub">Pending: ₹ ${(aff.pendingEarnings || 0).toLocaleString('en-IN')}</div>
            </td>
            <td><span class="text-date-bright">${aff.joined}</span></td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    <a href="detail/?id=${aff.id}" class="btn-icon-btn" title="View Detail">
                        <i class='bx bx-show'></i>
                    </a>
                    ${isPending ? `
                        <button type="button" onclick="approveAffiliate('${aff.id}', '${escapeQuotes(aff.name)}')" class="btn-icon-btn" title="Approve Affiliate">
                            <i class='bx bx-check-circle'></i>
                        </button>
                    ` : `
                        <button type="button" onclick="toggleAffStatus('${aff.id}')" class="btn-icon-btn" title="${aff.status === 'Active' || aff.status === 'Approved' ? 'Suspend Affiliate' : 'Activate Affiliate'}">
                            <i class='bx ${aff.status === 'Approved' || aff.status === 'Active' ? 'bx-pause-circle' : 'bx-play-circle'}'></i>
                        </button>
                    `}
                    <button type="button" onclick="openChangePasswordModal('${aff.id}', '${escapeQuotes(aff.name)}')" class="btn-icon-btn" title="Change Password">
                        <i class='bx bx-key'></i>
                    </button>
                    <button type="button" onclick="deleteAffiliate('${aff.id}')" class="btn-icon-btn" title="Delete">
                        <i class='bx bx-trash'></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function filterAffiliates() {
    const q = document.getElementById('affSearch').value.toLowerCase();
    const tier = document.getElementById('tierFilter').value;
    const status = document.getElementById('statusFilter').value;

    const filtered = affiliatesData.filter(a => {
        const matchesQ = (a.name || '').toLowerCase().includes(q) || 
                         (a.code || '').toLowerCase().includes(q) ||
                         (a.company || '').toLowerCase().includes(q) ||
                         (a.phone || '').toLowerCase().includes(q) ||
                         (a.email || '').toLowerCase().includes(q);
        const matchesTier = tier === '' || a.tier.toLowerCase() === tier.toLowerCase();
        const matchesStatus = status === '' || a.status.toLowerCase() === status.toLowerCase();
        return matchesQ && matchesTier && matchesStatus;
    });

    renderAffTable(filtered);
}

function triggerCommissionRun() {
    Swal.fire({
        title: 'Run Daily Commission Engine?',
        text: 'This will calculate yesterday\'s NGR and deposit CPA commissions.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Execute Run Now'
    }).then((res) => {
        if (res.isConfirmed) {
            fetch('commissions/run/', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    Swal.fire('Commission Run Completed!', data.message || 'Successfully updated balances.', 'success');
                    loadAffiliates();
                });
        }
    });
}

function approveAffiliate(id, name) {
    document.getElementById('approveAffiliateId').value = id;
    document.getElementById('approveAffiliateName').innerText = name || 'this affiliate';
    document.getElementById('approveDealType').value = 'revenue_share';
    document.getElementById('approveRevshare').value = '30';
    document.getElementById('approveCpa').value = '50';
    document.getElementById('approveTier').value = 'bronze';
    updateApprovalCommissionFields();
    new bootstrap.Modal(document.getElementById('approveAffiliateModal')).show();
}

function updateApprovalCommissionFields() {
    const dealType = document.getElementById('approveDealType').value;
    document.getElementById('approveRevshareGroup').classList.toggle('d-none', dealType === 'cpa');
    document.getElementById('approveCpaGroup').classList.toggle('d-none', dealType === 'revenue_share');
}

async function submitAffiliateApproval() {
    const modalElement = document.getElementById('approveAffiliateModal');
    const modal = bootstrap.Modal.getInstance(modalElement);
    try {
        const resp = await fetch('api_list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                affiliate_id: document.getElementById('approveAffiliateId').value,
                action: 'approve',
                status: 'active',
                deal_type: document.getElementById('approveDealType').value,
                revshare_pct: parseFloat(document.getElementById('approveRevshare').value || 0),
                cpa_amount: parseFloat(document.getElementById('approveCpa').value || 0),
                tier: document.getElementById('approveTier').value
            })
        });
        const data = await resp.json();
        if (modal) modal.hide();
        if (data.status === 'success') {
            Swal.fire({
                title: 'Approved!',
                text: data.message || 'Affiliate has been approved and activated.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            loadAffiliates();
        } else {
            Swal.fire('Error', data.message || 'Failed to approve affiliate.', 'error');
        }
    } catch (err) {
        if (modal) modal.hide();
        console.error(err);
        Swal.fire('Error', 'Network or server error.', 'error');
    }
}

document.getElementById('approveDealType').addEventListener('change', updateApprovalCommissionFields);

function toggleAffStatus(id) {
    const aff = affiliatesData.find(a => a.id == id);
    if (!aff) return;
    const nextStatus = (aff.status === 'Approved' || aff.status === 'Active') ? 'suspended' : 'active';

    Swal.fire({
        title: `Change Status?`,
        text: `Set ${aff.name} status to ${nextStatus}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirm'
    }).then((res) => {
        if (res.isConfirmed) {
            fetch('api_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ affiliate_id: id, status: nextStatus })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Status Updated', `Affiliate status changed to ${nextStatus}.`, 'success');
                    loadAffiliates();
                } else {
                    Swal.fire('Error', data.message || 'Failed to update status.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error.', 'error');
            });
        }
    });
}

function deleteAffiliate(id) {
    const aff = affiliatesData.find(a => a.id == id);
    if (!aff) return;

    if (aff.pendingEarnings > 0) {
        Swal.fire({ icon: 'error', title: 'Deletion Blocked', text: `Unpaid pending commissions exist.` });
        return;
    }

    Swal.fire({
        title: `Delete Affiliate ${aff.name}?`,
        text: 'Action is permanent and will remove all tracking data.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete'
    }).then((res) => {
        if (res.isConfirmed) {
            fetch('api_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ affiliate_id: id, action: 'delete' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Deleted', 'Affiliate removed.', 'success');
                    loadAffiliates();
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete affiliate.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error.', 'error');
            });
        }
    });
}

function toggleCustomDropdown(wrapperId) {
    const target = document.getElementById(wrapperId);
    const isOpen = target.classList.contains('open');
    document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
    if (!isOpen) {
        target.classList.add('open');
    }
}

function selectCustomOption(wrapperId, val, label, callback) {
    const wrapper = document.getElementById(wrapperId);
    const hiddenInput = wrapper.querySelector('input[type="hidden"]');
    const labelSpan = wrapper.querySelector('.selected-text');
    
    hiddenInput.value = val;
    labelSpan.innerText = label;

    wrapper.querySelectorAll('.custom-option').forEach(opt => {
        if (opt.getAttribute('data-value') === val) {
            opt.classList.add('selected');
        } else {
            opt.classList.remove('selected');
        }
    });

    wrapper.classList.remove('open');
    if (callback) callback();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select-wrapper')) {
        document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
    }
});

function openChangePasswordModal(id, name) {
    document.getElementById('changePassAffId').value = id;
    document.getElementById('changePassAffiliateName').innerText = 'Partner: ' + name;
    document.getElementById('newAffPassword').value = '';
    document.getElementById('confirmAffPassword').value = '';
    document.getElementById('passMismatchMsg').classList.add('d-none');

    const modalEl = document.getElementById('changePasswordModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-hide';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-show';
    }
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    let pass = '';
    for (let i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const newPassInput = document.getElementById('newAffPassword');
    const confirmPassInput = document.getElementById('confirmAffPassword');
    newPassInput.value = pass;
    newPassInput.type = 'text';
    confirmPassInput.value = pass;
    confirmPassInput.type = 'text';
    document.getElementById('passMismatchMsg').classList.add('d-none');
}

function submitChangePassword(e) {
    e.preventDefault();
    const affId = document.getElementById('changePassAffId').value;
    const newPass = document.getElementById('newAffPassword').value.trim();
    const confirmPass = document.getElementById('confirmAffPassword').value.trim();
    const mismatchMsg = document.getElementById('passMismatchMsg');

    if (newPass !== confirmPass) {
        mismatchMsg.classList.remove('d-none');
        return;
    }
    mismatchMsg.classList.add('d-none');

    if (newPass.length < 6) {
        Swal.fire('Error', 'Password must be at least 6 characters.', 'error');
        return;
    }

    const btn = document.getElementById('savePassBtn');
    btn.disabled = true;
    btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Updating...";

    fetch('/admin/affiliates/api_list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'change_password',
            affiliate_id: affId,
            new_password: newPass
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-check'></i> Update Password";
        if (data.status === 'success') {
            const modalEl = document.getElementById('changePasswordModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            Swal.fire({
                icon: 'success',
                title: 'Password Updated!',
                text: data.message || 'Affiliate password has been changed successfully.',
                timer: 2500
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to update password.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-check'></i> Update Password";
        console.error(err);
        Swal.fire('Error', 'Network or server error occurred.', 'error');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadAffiliates();
});
</script>

<!-- Change Affiliate Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; color: #fff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding: 16px 20px;">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.3); display: flex; align-items: center; justify-content: center;">
                        <i class='bx bx-key text-info fs-5'></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="changePasswordModalLabel" style="font-size: 16px;">Change Affiliate Password</h5>
                        <div class="small" id="changePassAffiliateName" style="color: #94a3b8; font-size: 12px;"></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form onsubmit="submitChangePassword(event)">
                <input type="hidden" id="changePassAffId" value="">
                <div class="modal-body p-4" style="background: rgba(15,23,42,0.95);">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small text-uppercase font-weight-bold mb-0" style="color: #cbd5e1;">New Password</label>
                            <button type="button" onclick="generateRandomPassword()" class="btn btn-link p-0 text-decoration-none small text-info" style="font-size: 11px;">
                                <i class='bx bx-refresh'></i> Generate Strong Password
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="password" id="newAffPassword" class="form-control" placeholder="Enter new password (min 6 characters)" required minlength="6" style="background: rgba(15, 23, 42, 0.8); border-color: rgba(255, 255, 255, 0.15); color: #ffffff;">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('newAffPassword', this)" style="border-color: rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: #cbd5e1;">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-uppercase font-weight-bold mb-1" style="color: #cbd5e1;">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" id="confirmAffPassword" class="form-control" placeholder="Re-enter new password" required minlength="6" style="background: rgba(15, 23, 42, 0.8); border-color: rgba(255, 255, 255, 0.15); color: #ffffff;">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('confirmAffPassword', this)" style="border-color: rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: #cbd5e1;">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                        <div id="passMismatchMsg" class="text-danger small mt-1 d-none"><i class='bx bx-error-circle'></i> Passwords do not match!</div>
                    </div>
                    <div class="p-2 rounded" style="background: rgba(56,189,248,0.08); border: 1px dashed rgba(56,189,248,0.25); color: #94a3b8; font-size: 11px;">
                        <i class='bx bx-info-circle text-info'></i> Changing the password will immediately update the affiliate's credentials. They will need to use this new password to sign in to their affiliate portal.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1); padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="savePassBtn" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1" style="background: linear-gradient(135deg, #0284c7, #0369a1); border: none; border-radius: 8px;">
                        <i class='bx bx-check'></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
