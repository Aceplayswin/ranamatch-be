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
    <title><?php echo $APP_NAME; ?>: Agent Credit Audit</title>
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

        .text-bold-white { color: #ffffff !important; font-weight: 700; font-size: 14px; }
        .text-cyan-code { color: #38bdf8 !important; font-weight: 700; font-family: monospace; font-size: 12px; }
        .text-bright-sub { color: #cbd5e1 !important; font-weight: 600; font-size: 12px; }
        .text-date-bright { color: #e2e8f0 !important; font-weight: 600; font-size: 12px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 12px;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            text-decoration: none !important; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
        .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff !important; }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Agent Management</span>
                <h1>Agent Credit & Settlement Console</h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button onclick="triggerSettlementCycle()" class="btn-modern btn-green-modern">
                    <i class='bx bx-play-circle fs-5'></i> Run Settlement Cycle
                </button>
                <a href="../settings/" class="btn-modern" style="background: rgba(255,255,255,0.1); color: #fff;">
                    <i class='bx bx-cog fs-5'></i> Cycle Settings
                </a>
                <a href="../" class="btn-modern btn-primary-modern">
                    <i class='bx bx-arrow-back fs-5'></i> Directory
                </a>
            </div>
        </div>

        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Total Injected Float</span>
                    <h4 style="color: #34d399 !important;">₹ 14,850,000</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #34d399; background: rgba(52, 211, 153, 0.15);">
                    <i class='bx bx-wallet'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Total Clawed Back</span>
                    <h4 style="color: #f43f5e !important;">₹ 1,200,000</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #f43f5e; background: rgba(244, 63, 94, 0.15);">
                    <i class='bx bx-minus-circle'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Net Platform Float</span>
                    <h4 id="netPlatformFloat" style="color: #38bdf8 !important;">₹ 0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);">
                    <i class='bx bx-stats'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Total Settled Profit</span>
                    <h4 id="totalWithdrawable" style="color: #fbbf24 !important;">₹ 0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(251, 191, 36, 0.15);">
                    <i class='bx bx-check-double'></i>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <button id="tabLedgerBtn" onclick="switchTab('ledger')" class="btn-modern btn-primary-modern">
                <i class='bx bx-list-ul'></i> Credit Ledger Audit
            </button>
            <button id="tabSettlementsBtn" onclick="switchTab('settlements')" class="btn-modern" style="background: rgba(255,255,255,0.08); color: #94a3b8;">
                <i class='bx bx-money-withdraw'></i> Settlement & Payout Requests
            </button>
        </div>

        <!-- Ledger Table -->
        <div id="ledgerSection" class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Txn ID</th>
                        <th>Agent</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Previous Balance</th>
                        <th>New Balance</th>
                        <th>Operator</th>
                        <th>Remark</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody id="creditTableBody">
                    <!-- Rendered dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Settlements Table -->
        <div id="settlementsSection" class="r-table-wrapper" style="display: none;">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Agent</th>
                        <th>Amount</th>
                        <th>Period</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Reference / UTR</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="settlementsTableBody">
                    <!-- Rendered dynamically -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentTab = 'ledger';

function switchTab(tab) {
    currentTab = tab;
    const lBtn = document.getElementById('tabLedgerBtn');
    const sBtn = document.getElementById('tabSettlementsBtn');
    const lSec = document.getElementById('ledgerSection');
    const sSec = document.getElementById('settlementsSection');

    if (tab === 'ledger') {
        lBtn.className = 'btn-modern btn-primary-modern';
        lBtn.style.color = '#fff';
        sBtn.className = 'btn-modern';
        sBtn.style.background = 'rgba(255,255,255,0.08)';
        sBtn.style.color = '#94a3b8';
        lSec.style.display = 'block';
        sSec.style.display = 'none';
        loadCreditAudit();
    } else {
        sBtn.className = 'btn-modern btn-primary-modern';
        sBtn.style.color = '#fff';
        lBtn.className = 'btn-modern';
        lBtn.style.background = 'rgba(255,255,255,0.08)';
        lBtn.style.color = '#94a3b8';
        lSec.style.display = 'none';
        sSec.style.display = 'block';
        loadSettlements();
    }
}

async function loadCreditAudit() {
    try {
        const res = await fetch('api_credit.php');
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('netPlatformFloat').innerText = '₹ ' + (data.total_float || 0).toLocaleString('en-IN');
            if (document.getElementById('totalWithdrawable')) {
                document.getElementById('totalWithdrawable').innerText = '₹ ' + (data.total_withdrawable || 0).toLocaleString('en-IN');
            }
            
            const tbody = document.getElementById('creditTableBody');
            tbody.innerHTML = '';

            if (!data.data || data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">No credit audit records found.</td></tr>`;
                return;
            }

            data.data.forEach(r => {
                const isInj = (parseFloat(r.amount) >= 0 || r.transaction_type === 'INJECTION' || r.transaction_type === 'TRANSFER_IN');
                const tagClass = isInj ? 'tag-success' : 'tag-danger';
                const amtColor = isInj ? '#34d399' : '#f43f5e';
                const sign = isInj ? '+' : '';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-cyan-code">CRD-${r.id}</td>
                    <td>
                        <div class="text-bold-white">${r.agent_name || r.username || 'Agent #' + r.agent_id}</div>
                        <div class="text-cyan-code">${r.agent_code || ''}</div>
                    </td>
                    <td><span class="tag ${tagClass}">${r.transaction_type}</span></td>
                    <td class="fw-bold" style="color: ${amtColor} !important;">${sign}₹ ${Math.abs(parseFloat(r.amount)).toLocaleString('en-IN')}</td>
                    <td>₹ ${parseFloat(r.balance_before || 0).toLocaleString('en-IN')}</td>
                    <td class="fw-bold text-bold-white">₹ ${parseFloat(r.balance_after || 0).toLocaleString('en-IN')}</td>
                    <td><span class="text-bold-white">${r.admin_id ? 'Admin #' + r.admin_id : 'System'}</span></td>
                    <td><span class="text-bright-sub">${r.remark || '-'}</span></td>
                    <td><span class="text-date-bright">${r.created_at || ''}</span></td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error("Failed to load credit audit:", e);
    }
}

async function loadSettlements() {
    try {
        const res = await fetch('api_credit.php?action=payout_requests');
        const data = await res.json();
        const tbody = document.getElementById('settlementsTableBody');
        tbody.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">No settlement or withdrawal records found.</td></tr>`;
            return;
        }

        data.data.forEach(r => {
            let statusTag = `<span class="tag tag-success">Paid</span>`;
            if (r.status === 'pending') statusTag = `<span class="tag" style="background: rgba(251,191,36,0.2); color:#fbbf24; border:1px solid rgba(251,191,36,0.4);">Pending</span>`;
            else if (r.status === 'rejected') statusTag = `<span class="tag tag-danger">Rejected</span>`;
            else if (r.status === 'approved') statusTag = `<span class="tag" style="background: rgba(56,189,248,0.2); color:#38bdf8; border:1px solid rgba(56,189,248,0.4);">Approved</span>`;

            let actionHtml = `<span class="text-muted fs-7">Completed</span>`;
            if (r.status === 'pending') {
                actionHtml = `
                    <button onclick="markPayoutPaid(${r.id})" class="btn btn-sm btn-success me-1" style="font-size:11px; font-weight:700;">
                        <i class='bx bx-check'></i> Mark Paid
                    </button>
                    <button onclick="rejectPayout(${r.id})" class="btn btn-sm btn-outline-danger" style="font-size:11px; font-weight:700;">
                        <i class='bx bx-x'></i> Reject
                    </button>
                `;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-cyan-code">SET-${r.id}</td>
                <td>
                    <div class="text-bold-white">${r.agent_name || r.username || 'Agent #' + r.agent_id}</div>
                    <div class="text-cyan-code">${r.agent_code || ''}</div>
                </td>
                <td class="fw-bold text-success fs-6">₹ ${parseFloat(r.amount).toLocaleString('en-IN')}</td>
                <td><span class="text-bright-sub">${r.period || '-'}</span></td>
                <td><span class="text-bold-white text-uppercase" style="font-size:11px;">${r.payout_method || 'bank'}</span></td>
                <td>${statusTag}</td>
                <td><span class="text-cyan-code">${r.transaction_ref || '-'}</span></td>
                <td><span class="text-date-bright">${r.created_at || ''}</span></td>
                <td>${actionHtml}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error("Failed to load settlements:", e);
    }
}

async function markPayoutPaid(id) {
    const { value: uTr } = await Swal.fire({
        title: 'Mark Withdrawal as Paid',
        input: 'text',
        inputLabel: 'Bank UTR / Transaction Reference Number',
        inputPlaceholder: 'e.g. UTR1234567890',
        inputValidator: (val) => !val ? 'UTR reference is required' : null,
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Confirm Paid'
    });

    if (uTr) {
        const res = await fetch('api_credit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_paid', payout_id: id, transaction_ref: uTr.trim() })
        });
        const d = await res.json();
        if (d.status === 'success') {
            Swal.fire('Success', d.message, 'success');
            loadSettlements();
        } else {
            Swal.fire('Error', d.message, 'error');
        }
    }
}

async function rejectPayout(id) {
    const { value: reason } = await Swal.fire({
        title: 'Reject Withdrawal Request',
        input: 'text',
        inputLabel: 'Rejection Reason (Refunds profit to agent)',
        inputPlaceholder: 'e.g. Invalid bank details, KYC pending',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Reject & Refund'
    });

    if (reason !== undefined) {
        const res = await fetch('api_credit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reject_payout', payout_id: id, reason: reason.trim() || 'Admin rejected' })
        });
        const d = await res.json();
        if (d.status === 'success') {
            Swal.fire('Refunded', d.message, 'success');
            loadSettlements();
        } else {
            Swal.fire('Error', d.message, 'error');
        }
    }
}

async function triggerSettlementCycle() {
    const conf = await Swal.fire({
        title: 'Run Settlement Cycle?',
        text: 'This will calculate net P&L for the configured settlement period and unlock net profits into withdrawable balance for all agents.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Run Settlement Now'
    });

    if (conf.isConfirmed) {
        Swal.fire({ title: 'Processing Settlement...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const res = await fetch('api_credit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'run_settlement_cycle' })
        });
        const d = await res.json();
        if (d.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Settlement Completed',
                html: `<p><strong>Period:</strong> ${d.data.period}</p>
                       <p><strong>Agents Processed:</strong> ${d.data.agents_processed}</p>
                       <p><strong>Total Unlocked Profit:</strong> ₹ ${d.data.total_unlocked_profit.toLocaleString('en-IN')}</p>`
            });
            loadCreditAudit();
            if (currentTab === 'settlements') loadSettlements();
        } else {
            Swal.fire('Error', d.message, 'error');
        }
    }
}

document.addEventListener('DOMContentLoaded', loadCreditAudit);
</script>
</body>
</html>
