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
    <title><?php echo $APP_NAME; ?>: Agent Applications</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        /* Premium Table Design */
        .r-table-wrapper {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.6));
            border-radius: 18px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            overflow-x: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        .r-table-wrapper::-webkit-scrollbar { height: 6px; }
        .r-table-wrapper::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.6); }
        .r-table-wrapper::-webkit-scrollbar-thumb { background: rgba(56, 189, 248, 0.3); border-radius: 10px; }
        .r-table-wrapper::-webkit-scrollbar-thumb:hover { background: rgba(56, 189, 248, 0.6); }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(15, 23, 42, 0.85); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1.1px; color: #cbd5e1 !important; border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .r-table td {
            padding: 14px 20px; font-size: 13px; font-weight: 600;
            color: #ffffff !important; border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            vertical-align: middle; white-space: nowrap;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: rgba(56, 189, 248, 0.06); }

        .tag {
            padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;
        }
        .tag-success { background: rgba(16, 185, 129, 0.18); color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.4); }
        .tag-danger { background: rgba(244, 63, 94, 0.18); color: #f43f5e !important; border: 1px solid rgba(244, 63, 94, 0.4); }
        .tag-warning { background: rgba(245, 158, 11, 0.18); color: #fbbf24 !important; border: 1px solid rgba(245, 158, 11, 0.4); }
        .tag-info { background: rgba(99, 102, 241, 0.18); color: #818cf8 !important; border: 1px solid rgba(99, 102, 241, 0.4); }
        .tag-blue { background: rgba(6, 182, 212, 0.18); color: #38bdf8 !important; border: 1px solid rgba(6, 182, 212, 0.4); }

        .text-bold-white { color: #ffffff !important; font-weight: 700; font-size: 14px; }
        .text-cyan-code { color: #38bdf8 !important; font-weight: 700; font-family: monospace; font-size: 12px; }
        .text-date-bright { color: #e2e8f0 !important; font-weight: 600; font-size: 12px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 12px;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            text-decoration: none !important; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
        .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff !important; }

        .btn-icon-action {
            height: 32px; padding: 0 10px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            font-size: 12px; font-weight: 700; border: 1px solid transparent;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none !important;
        }
        .btn-action-approve { background: rgba(16, 185, 129, 0.2); color: #34d399 !important; border-color: rgba(16, 185, 129, 0.4); }
        .btn-action-approve:hover { background: #10b981; color: #ffffff !important; }

        .btn-action-info { background: rgba(99, 102, 241, 0.2); color: #a5b4fc !important; border-color: rgba(99, 102, 241, 0.4); }
        .btn-action-info:hover { background: #6366f1; color: #ffffff !important; }

        .btn-action-reject { background: rgba(244, 63, 94, 0.2); color: #f43f5e !important; border-color: rgba(244, 63, 94, 0.4); }
        .btn-action-reject:hover { background: #ef4444; color: #ffffff !important; }

        /* Modal Dark Overlays & Custom Styling matching prompt screenshots */
        .modal-custom-content {
            background-color: #0f172a !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 20px !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7) !important;
            color: #f8fafc !important;
        }
        .modal-custom-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 20px 24px 16px !important;
        }
        .modal-custom-title {
            font-size: 18px !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: -0.3px;
        }
        .modal-custom-body {
            padding: 20px 24px !important;
        }
        .modal-custom-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 16px 24px !important;
            display: flex; justify-content: flex-end; gap: 12px;
        }

        #viewAppModal .modal-dialog {
            height: calc(100vh - 2rem);
            max-height: calc(100vh - 2rem);
        }
        #viewAppModal .modal-content {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
        }
        #viewAppModal .modal-custom-body {
            min-height: 0;
            overflow-y: auto;
            flex: 1 1 auto;
        }

        .dark-field-label {
            font-size: 10px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            color: #94a3b8 !important;
            margin-bottom: 6px !important;
            display: block;
        }

        .dark-input, .dark-select, .dark-textarea {
            width: 100% !important;
            background-color: #020617 !important;
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            outline: none !important;
            transition: border-color 0.2s ease;
        }
        .dark-input:focus, .dark-select:focus, .dark-textarea:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15) !important;
        }
        .dark-select option {
            background-color: #0f172a !important;
            color: #ffffff !important;
        }

        .btn-cancel-modal {
            background: #1e293b !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #94a3b8 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 8px 20px !important;
            border-radius: 10px !important;
            cursor: pointer;
        }
        .btn-cancel-modal:hover { color: #ffffff !important; background: #334155 !important; }

        .btn-approve-modal {
            background: #10b981 !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 13px !important;
            padding: 8px 24px !important;
            border-radius: 10px !important;
            border: none !important;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3) !important;
        }
        .btn-approve-modal:hover { background: #059669 !important; }

        .btn-info-modal {
            background: #6366f1 !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 13px !important;
            padding: 8px 24px !important;
            border-radius: 10px !important;
            border: none !important;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3) !important;
        }
        .btn-info-modal:hover { background: #4f46e5 !important; }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Agent Management</span>
                <h1>Agent Applications Review Queue</h1>
            </div>
            <a href="../" class="btn-modern btn-primary-modern">
                <i class='bx bx-arrow-back fs-5'></i> Directory
            </a>
        </div>

        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Pending Applications</span>
                    <h4 id="pendingCountDisplay" style="color: #fbbf24 !important;">0 Applications Pending</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(245, 158, 11, 0.15);">
                    <i class='bx bx-time-five'></i>
                </div>
            </div>
        </div>

        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Contact Email</th>
                        <th>Market / Region</th>
                        <th>Est. Volume</th>
                        <th>Requested Upline</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <!-- Rendered dynamically -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal 1: Approve Application Modal (Image 1) -->
<div class="modal fade" id="approveAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom-content">
            <div class="modal-custom-header flex justify-between items-center">
                <h5 class="modal-custom-title mb-0">Approve application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-custom-body">
                <p class="text-sm text-slate-300 mb-4" style="font-size: 13px; line-height: 1.5;">
                    Approving <strong id="approveModalApplicantName" class="text-white">...</strong>. This attaches the account to the tree and lets them sign in with the username and password they chose when applying.
                </p>

                <form id="approveAppForm">
                    <input type="hidden" id="approveAppId" value="">

                    <div class="mb-3">
                        <label class="dark-field-label">UPLINE</label>
                        <select id="approveModalUpline" class="dark-select">
                            <option value="none">None — open at the top of the tree</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label class="dark-field-label">LEVEL</label>
                            <select id="approveModalLevel" class="dark-select">
                                <option value="agent" selected>Agent</option>
                                <option value="master_agent">Master Agent</option>
                                <option value="super_agent">Super Agent</option>
                                <option value="senior_super_agent">Senior Super Agent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="dark-field-label">OPENING CREDIT (₹)</label>
                            <input type="number" id="approveModalCredit" class="dark-input" value="0" min="0" step="any">
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 mb-3" style="font-size: 11px; color: #64748b;">
                        Injected by the platform — a root account has no upline to debit.
                    </p>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="dark-field-label">PARTNERSHIP (%)</label>
                            <input type="number" id="approveModalPartnership" class="dark-input" value="25" min="0" max="100" step="any">
                        </div>
                        <div class="col-6">
                            <label class="dark-field-label">COMMISSION (%)</label>
                            <input type="number" id="approveModalCommission" class="dark-input" value="2" min="0" max="100" step="any">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-custom-footer">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-approve-modal" onclick="submitApproveApp()">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Request More Information Modal (Image 2) -->
<div class="modal fade" id="requestInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom-content">
            <div class="modal-custom-header flex justify-between items-center">
                <h5 class="modal-custom-title mb-0">Request more information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-custom-body">
                <p class="text-sm text-slate-300 mb-4" style="font-size: 13px; line-height: 1.5;">
                    <strong id="infoModalApplicantName" class="text-white">...</strong> sees this when they check their application status.
                </p>

                <form id="requestInfoForm">
                    <input type="hidden" id="infoAppId" value="">
                    
                    <div class="mb-2">
                        <label class="dark-field-label">WHAT DO YOU NEED FROM THEM?</label>
                        <textarea id="infoModalNotes" class="dark-textarea" rows="4" placeholder="e.g. Tell us which markets you already operate in, and roughly how many players you expect to bring."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-custom-footer">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-info-modal" onclick="submitRequestInfo()">Send request</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Full Application Details -->
<div class="modal fade" id="viewAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-custom-content">
            <div class="modal-custom-header flex justify-between items-center">
                <h5 class="modal-custom-title mb-0">Complete application details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-custom-body">
                <div id="viewAppDetails" class="row g-3"></div>
            </div>
            <div class="modal-custom-footer">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let applicationsData = [];
let uplinesData = [];

async function loadUplines() {
    try {
        const res = await fetch('api_applications.php?action=uplines');
        const data = await res.json();
        if (data.status === 'success') {
            uplinesData = data.data || [];
            const sel = document.getElementById('approveModalUpline');
            sel.innerHTML = '<option value="none">None — open at the top of the tree</option>';
            uplinesData.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.innerText = `${u.name} (@${u.username} - ${u.rank_level})`;
                sel.appendChild(opt);
            });
        }
    } catch (e) {
        console.error("Failed to load uplines:", e);
    }
}

async function loadApplications() {
    try {
        const res = await fetch('api_applications.php?status=ALL');
        const data = await res.json();
        if (data.status === 'success') {
            applicationsData = (data.data || []).map(a => ({
                id: a.id,
                name: a.full_name || a.username || 'Applicant',
                username: a.username || '',
                company: a.company || 'Individual Operator',
                email: a.email || '',
                market: a.market_region || 'N/A',
                volume: a.volume_bracket || 'N/A',
                phone: a.phone || 'N/A',
                expectedPlayers: a.expected_players || 'N/A',
                experience: a.experience || 'N/A',
                applicationNotes: a.application_notes || 'N/A',
                upline_id: a.requested_upline_id,
                upline: a.requested_upline_name || a.claimed_upline_text || 'Direct (Top of Tree)',
                date: a.applied_at ? a.applied_at.split(' ')[0] : '',
                status: a.status ? a.status : 'pending',
                notes: a.info_request_notes || '',
                appliedAt: a.applied_at || 'N/A',
                processedAt: a.processed_at || 'Not processed',
                rejectionReason: a.rejection_reason || 'N/A'
            }));
            renderAppsTable();
        }
    } catch (err) {
        console.error("Failed to load applications:", err);
    }
}

function renderAppsTable() {
    const tbody = document.getElementById('applicationsTableBody');
    tbody.innerHTML = '';
    
    if (applicationsData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No agent applications found.</td></tr>`;
        document.getElementById('pendingCountDisplay').innerText = `0 Applications Pending`;
        return;
    }

    let pendingCount = 0;
    applicationsData.forEach(app => {
        const statusLower = app.status.toLowerCase();
        if (statusLower === 'pending' || statusLower === 'info_requested') pendingCount++;
        
        let tagClass = 'tag-warning';
        let statusDisplay = 'Pending';
        
        if (statusLower === 'info_requested') {
            tagClass = 'tag-info';
            statusDisplay = 'Info Requested';
        } else if (statusLower === 'rejected') {
            tagClass = 'tag-danger';
            statusDisplay = 'Rejected';
        } else if (statusLower === 'approved') {
            tagClass = 'tag-success';
            statusDisplay = 'Approved';
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="text-bold-white">${app.name}</div>
                <div><span style="color:#cbd5e1; font-size:11px;">@${app.username} &bull; ${app.company}</span></div>
            </td>
            <td><span class="text-cyan-code">${app.email}</span></td>
            <td><span class="tag tag-blue">${app.market}</span></td>
            <td><span class="text-bold-white">${app.volume}</span></td>
            <td><span class="text-cyan-code">${app.upline}</span></td>
            <td><span class="text-date-bright">${app.date}</span></td>
            <td><span class="tag ${tagClass}">${statusDisplay}</span></td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1.5">
                    <button onclick="viewApplication('${app.id}')" class="btn-icon-action btn-action-info" title="View full application details">
                        <i class='bx bx-show fs-6'></i> View
                    </button>
                    ${(statusLower === 'pending' || statusLower === 'info_requested') ? `
                    <button onclick="openApproveModal('${app.id}')" class="btn-icon-action btn-action-approve" title="Approve Application">
                        <i class='bx bx-check fs-6'></i> Approve
                    </button>
                    <button onclick="openInfoModal('${app.id}')" class="btn-icon-action btn-action-info" title="Request Information">
                        <i class='bx bx-message-dots fs-6'></i> Request Info
                    </button>
                    <button onclick="rejectApp('${app.id}')" class="btn-icon-action btn-action-reject" title="Reject Application">
                        <i class='bx bx-x fs-6'></i>
                    </button>
                    ` : `<span class="text-muted text-xs font-bold">Processed</span>`}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('pendingCountDisplay').innerText = `${pendingCount} Applications Pending`;
}

function viewApplication(id) {
    const app = applicationsData.find(a => String(a.id) === String(id));
    if (!app) return;

    const fields = [
        ['Application ID', app.id],
        ['Status', app.status],
        ['Full Name', app.name],
        ['Username', app.username],
        ['Email Address', app.email],
        ['Phone / Telegram', app.phone],
        ['Company', app.company],
        ['Market / Region', app.market],
        ['Expected Players', app.expectedPlayers],
        ['Bookie Experience', app.experience],
        ['Expected Monthly Volume', app.volume],
        ['Upline Code', app.upline],
        ['Applied At', app.appliedAt],
        ['Processed At', app.processedAt],
        ['Application Notes', app.applicationNotes],
        ['Admin Information Request', app.notes],
        ['Rejection Reason', app.rejectionReason]
    ];

    const container = document.getElementById('viewAppDetails');
    container.innerHTML = fields.map(([label, value]) => `
        <div class="col-md-6">
            <div style="background:rgba(2, 6, 23, 0.7); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:11px 13px; min-height:64px;">
                <div class="dark-field-label">${escapeHtml(label)}</div>
                <div style="color:#fff; font-size:13px; font-weight:600; white-space:pre-wrap; overflow-wrap:anywhere;">${escapeHtml(String(value ?? 'N/A'))}</div>
            </div>
        </div>
    `).join('');

    new bootstrap.Modal(document.getElementById('viewAppModal')).show();
}

function escapeHtml(value) {
    return value.replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[character]));
}

function openApproveModal(id) {
    const app = applicationsData.find(a => String(a.id) === String(id));
    if (!app) return;

    document.getElementById('approveAppId').value = app.id;
    document.getElementById('approveModalApplicantName').innerText = app.name;
    
    // Set default upline if available
    const selUpline = document.getElementById('approveModalUpline');
    if (app.upline_id) {
        selUpline.value = app.upline_id;
    } else {
        selUpline.value = 'none';
    }

    document.getElementById('approveModalLevel').value = 'agent';
    document.getElementById('approveModalCredit').value = '0';
    document.getElementById('approveModalPartnership').value = '25';
    document.getElementById('approveModalCommission').value = '2';

    const bsModal = new bootstrap.Modal(document.getElementById('approveAppModal'));
    bsModal.show();
}

async function submitApproveApp() {
    const id = document.getElementById('approveAppId').value;
    const uplineId = document.getElementById('approveModalUpline').value;
    const rankLevel = document.getElementById('approveModalLevel').value;
    const openingCredit = parseFloat(document.getElementById('approveModalCredit').value || 0);
    const partnershipPct = parseFloat(document.getElementById('approveModalPartnership').value || 25);
    const commissionPct = parseFloat(document.getElementById('approveModalCommission').value || 2);

    try {
        const resp = await fetch('api_applications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'approve',
                application_id: id,
                upline_id: uplineId,
                rank_level: rankLevel,
                opening_credit: openingCredit,
                partnership_pct: partnershipPct,
                commission_pct: commissionPct
            })
        });
        const data = await resp.json();
        
        const modalElem = document.getElementById('approveAppModal');
        const modalInstance = bootstrap.Modal.getInstance(modalElem);
        if (modalInstance) modalInstance.hide();

        if (data.status === 'success') {
            Swal.fire({
                title: 'Application Approved!',
                text: 'Account attached to tree and active credentials issued.',
                icon: 'success',
                background: '#0f172a',
                color: '#fff'
            });
            loadApplications();
        } else {
            Swal.fire({
                title: 'Approval Failed',
                text: data.message || 'Error executing approval.',
                icon: 'error',
                background: '#0f172a',
                color: '#fff'
            });
        }
    } catch (e) {
        Swal.fire({
            title: 'Error',
            text: 'Network connection failed.',
            icon: 'error',
            background: '#0f172a',
            color: '#fff'
        });
    }
}

function openInfoModal(id) {
    const app = applicationsData.find(a => String(a.id) === String(id));
    if (!app) return;

    document.getElementById('infoAppId').value = app.id;
    document.getElementById('infoModalApplicantName').innerText = app.name;
    document.getElementById('infoModalNotes').value = app.notes || '';

    const bsModal = new bootstrap.Modal(document.getElementById('requestInfoModal'));
    bsModal.show();
}

async function submitRequestInfo() {
    const id = document.getElementById('infoAppId').value;
    const notes = document.getElementById('infoModalNotes').value;

    if (!notes.trim()) {
        Swal.fire({
            title: 'Note Required',
            text: 'Please describe what information you need from the applicant.',
            icon: 'warning',
            background: '#0f172a',
            color: '#fff'
        });
        return;
    }

    try {
        const resp = await fetch('api_applications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'request_info',
                application_id: id,
                notes: notes
            })
        });
        const data = await resp.json();

        const modalElem = document.getElementById('requestInfoModal');
        const modalInstance = bootstrap.Modal.getInstance(modalElem);
        if (modalInstance) modalInstance.hide();

        if (data.status === 'success') {
            Swal.fire({
                title: 'Information Requested',
                text: 'Status updated and notification sent to applicant.',
                icon: 'success',
                background: '#0f172a',
                color: '#fff'
            });
            loadApplications();
        } else {
            Swal.fire({
                title: 'Request Failed',
                text: data.message || 'Could not update application status.',
                icon: 'error',
                background: '#0f172a',
                color: '#fff'
            });
        }
    } catch (e) {
        Swal.fire({
            title: 'Error',
            text: 'Network connection failed.',
            icon: 'error',
            background: '#0f172a',
            color: '#fff'
        });
    }
}

async function rejectApp(id) {
    Swal.fire({
        title: 'Reject Application?',
        text: 'Reason for rejection:',
        input: 'text',
        inputPlaceholder: 'e.g. Compliance requirements not met',
        showCancelButton: true,
        confirmButtonText: 'Reject Application',
        confirmButtonColor: '#ef4444',
        background: '#0f172a',
        color: '#fff'
    }).then(async (res) => {
        if (res.isConfirmed) {
            try {
                const resp = await fetch('api_applications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reject', application_id: id, reason: res.value || 'Rejected by Admin' })
                });
                const data = await resp.json();
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Rejected',
                        text: 'Application rejected.',
                        icon: 'info',
                        background: '#0f172a',
                        color: '#fff'
                    });
                    loadApplications();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Rejection failed.',
                        icon: 'error',
                        background: '#0f172a',
                        color: '#fff'
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Error',
                    text: 'Network connection failed.',
                    icon: 'error',
                    background: '#0f172a',
                    color: '#fff'
                });
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadUplines();
    loadApplications();
});
</script>
</body>
</html>
