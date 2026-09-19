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

// Live real data metrics from Database
$pSumSql = "SELECT 
    COUNT(CASE WHEN status = 'requested' THEN 1 END) AS requested_count,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
    COALESCE(SUM(CASE WHEN status = 'requested' THEN amount ELSE 0 END), 0) AS requested_amount_total,
    COALESCE(SUM(CASE WHEN status = 'paid' AND MONTH(paid_at) = MONTH(CURRENT_DATE()) AND YEAR(paid_at) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END), 0) AS paid_month_total
FROM affiliate_payouts";
$pSumRes = mysqli_query($conn, $pSumSql);
$pSumData = $pSumRes ? mysqli_fetch_assoc($pSumRes) : [];
$pReqCount = (int)($pSumData['requested_count'] ?? 0);
$pReqAmount = (float)($pSumData['requested_amount_total'] ?? 0);
$pPaidMonth = (float)($pSumData['paid_month_total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Payout Approvals</title>
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
            border: 1px solid rgba(255, 255, 255, 0.1); overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
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

        /* Glowing Badges */
        .tag {
            padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;
        }
        .tag-success { background: rgba(16, 185, 129, 0.18); color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.4); }
        .tag-danger { background: rgba(244, 63, 94, 0.18); color: #f43f5e !important; border: 1px solid rgba(244, 63, 94, 0.4); }
        .tag-warning { background: rgba(245, 158, 11, 0.18); color: #fbbf24 !important; border: 1px solid rgba(245, 158, 11, 0.4); }
        .tag-info { background: rgba(59, 130, 246, 0.18); color: #60a5fa !important; border: 1px solid rgba(59, 130, 246, 0.4); }

        .text-bold-white { color: #ffffff !important; font-weight: 700; font-size: 14px; }
        .text-cyan-code { color: #38bdf8 !important; font-weight: 700; font-family: monospace; font-size: 12px; }
        .text-bright-sub { color: #cbd5e1 !important; font-weight: 600; font-size: 12px; }
        .text-bright-emerald { color: #34d399 !important; font-weight: 800; font-size: 14px; }
        .text-date-bright { color: #e2e8f0 !important; font-weight: 600; font-size: 12px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 12px;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            text-decoration: none !important; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
        .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff !important; }
        .btn-green-modern { background: rgba(16, 185, 129, 0.2); color: #34d399 !important; border: 1px solid #10b981; }
        .btn-red-modern { background: rgba(244, 63, 94, 0.2); color: #f43f5e !important; border: 1px solid #ef4444; }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Affiliate Management</span>
                <h1>Payout Approvals</h1>
            </div>
            <a href="../" class="btn-modern btn-primary-modern">
                <i class='bx bx-arrow-back fs-5'></i> Directory
            </a>
        </div>

        <!-- Summary Bar (Live Database Data) -->
        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Awaiting Review</span>
                    <h4 id="statAwaiting" style="color: #fbbf24 !important;"><?php echo $pReqCount; ?> Requests</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(245, 158, 11, 0.15);">
                    <i class='bx bx-time-five'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Pending Amount</span>
                    <h4 id="statPendingAmt" style="color: #38bdf8 !important;">₹ <?php echo number_format($pReqAmount, 2); ?></h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);">
                    <i class='bx bx-wallet'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Paid This Month</span>
                    <h4 id="statPaidMonth" class="text-bright-emerald">₹ <?php echo number_format($pPaidMonth, 2); ?></h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #34d399; background: rgba(52, 211, 153, 0.15);">
                    <i class='bx bx-check-circle'></i>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Affiliate</th>
                        <th>Amount & Ledger Items</th>
                        <th>Payout Details</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="payoutsTableBody">
                    <!-- Rendered dynamically -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background: var(--panel-bg); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 16px; color: #ffffff; font-size: 13px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            <div class="modal-header py-3" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h6 class="modal-title font-weight-bold mb-0 text-bright-emerald"><i class='bx bx-credit-card me-1'></i>Mark Payout as Paid</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(0.8);"></button>
            </div>
            <form onsubmit="submitMarkPaid(event)">
                <div class="modal-body py-3">
                    <input type="hidden" id="paidPayoutId">
                    <div class="mb-2">
                        <label class="form-label font-weight-bold small text-uppercase mb-1" style="color: #cbd5e1;">Transaction Ref / Hash</label>
                        <input type="text" class="form-control form-control-sm" id="txRefInput" placeholder="e.g. UTR-90812371923" required style="background: rgba(15, 23, 42, 0.8); border-color: rgba(255, 255, 255, 0.15); color: #ffffff;">
                    </div>
                </div>
                <div class="modal-footer py-2" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-green-modern">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let payoutsData = [];

async function loadPayouts() {
    try {
        const res = await fetch('api_payouts.php');
        const data = await res.json();
        if (data.status === 'success') {
            payoutsData = (data.data || []).map(p => ({
                id: p.id,
                affName: p.affiliate_name || ('Affiliate #' + p.affiliate_id),
                code: p.affiliate_code || 'N/A',
                amount: p.amount || 0,
                method: p.payout_method_type || 'Bank Transfer',
                details: p.payout_method_details ? (p.payout_method_details.account_number || p.payout_method_details.upi_id || p.payout_method_details.wallet_address || JSON.stringify(p.payout_method_details)) : (p.transaction_reference || 'Standard Method'),
                date: p.requested_at ? p.requested_at.split(' ')[0] : 'N/A',
                status: p.status ? (p.status.charAt(0).toUpperCase() + p.status.slice(1)) : 'Requested',
                txRef: p.transaction_reference || '',
                reason: p.rejection_reason || ''
            }));

            if (data.summary) {
                const reqCount = document.getElementById('statAwaiting');
                if (reqCount) reqCount.innerText = `${data.summary.requested_count || 0} Requests`;
                const pendAmt = document.getElementById('statPendingAmt');
                if (pendAmt) pendAmt.innerText = `₹ ${(data.summary.requested_amount_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                const paidAmt = document.getElementById('statPaidMonth');
                if (paidAmt) paidAmt.innerText = `₹ ${(data.summary.paid_month_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }

            renderPayoutsTable();
        }
    } catch (e) {
        console.error('Failed to load payouts:', e);
    }
}

function renderPayoutsTable() {
    const tbody = document.getElementById('payoutsTableBody');
    tbody.innerHTML = '';

    if (payoutsData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No payout requests found in database.</td></tr>`;
        return;
    }

    payoutsData.forEach(p => {
        let tagClass = 'tag-warning';
        if (p.status === 'Approved') tagClass = 'tag-info';
        else if (p.status === 'Paid') tagClass = 'tag-success';
        else if (p.status === 'Rejected') tagClass = 'tag-danger';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="text-bold-white">${p.affName}</div>
                <div><span style="color:#cbd5e1; font-size:11px;">Code:</span> <span class="text-cyan-code">${p.code}</span></div>
            </td>
            <td>
                <div class="text-bright-emerald">₹ ${p.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</div>
            </td>
            <td>
                <div class="text-bold-white">${p.method}</div>
                <div class="text-bright-sub">${p.details}</div>
            </td>
            <td><span class="text-date-bright">${p.date}</span></td>
            <td>
                <span class="tag ${tagClass}">${p.status}</span>
                ${p.reason ? `<div style="font-size:10px; color:#f43f5e; font-weight:700; margin-top:3px;">Reason: ${p.reason}</div>` : ''}
            </td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    ${p.status === 'Requested' ? `<button onclick="approvePayout('${p.id}')" class="btn-modern btn-green-modern" style="height: 28px; padding: 0 8px; font-size: 11px;">Approve</button>` : ''}
                    ${p.status === 'Requested' || p.status === 'Approved' ? `<button onclick="openMarkPaidModal('${p.id}')" class="btn-modern btn-primary-modern" style="height: 28px; padding: 0 8px; font-size: 11px;">Mark Paid</button>` : ''}
                    ${p.status === 'Requested' || p.status === 'Approved' ? `<button onclick="openRejectModal('${p.id}')" class="btn-modern btn-red-modern" style="height: 28px; padding: 0 8px; font-size: 11px;">Reject</button>` : '<span class="text-bright-sub">' + (p.txRef ? 'Ref: ' + p.txRef : p.reason ? 'Reason: ' + p.reason : 'Closed') + '</span>'}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function approvePayout(id) {
    Swal.fire({
        title: 'Approve Payout #' + id + '?',
        text: 'This authorizes the payout for processing and settlement.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Approve'
    }).then(res => {
        if (res.isConfirmed) {
            fetch('api_payouts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', payout_id: id })
            })
            .then(async r => {
                const text = await r.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }
                if (!r.ok || data.status === 'error') throw new Error(data.message || 'Failed to approve.');
                return data;
            })
            .then(data => {
                Swal.fire('Approved!', data.message || 'Payout approved.', 'success');
                loadPayouts();
            })
            .catch(err => {
                Swal.fire('Error', err.message || 'Network or server error.', 'error');
            });
        }
    });
}

function openMarkPaidModal(id) {
    document.getElementById('paidPayoutId').value = id;
    document.getElementById('txRefInput').value = '';
    new bootstrap.Modal(document.getElementById('markPaidModal')).show();
}

function submitMarkPaid(e) {
    e.preventDefault();
    const id = document.getElementById('paidPayoutId').value;
    const ref = document.getElementById('txRefInput').value.trim();
    if (!ref) return;

    fetch('api_payouts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'pay', payout_id: id, transaction_reference: ref })
    })
    .then(async r => {
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }
        if (!r.ok || data.status === 'error') throw new Error(data.message || 'Failed to mark paid.');
        return data;
    })
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('markPaidModal')).hide();
        Swal.fire('Payment Released!', `Ref: ${ref}`, 'success');
        loadPayouts();
    })
    .catch(err => {
        bootstrap.Modal.getInstance(document.getElementById('markPaidModal')).hide();
        Swal.fire('Error', err.message || 'Network or server error.', 'error');
    });
}

function openRejectModal(id) {
    Swal.fire({
        title: 'Reject Payout #' + id + '?',
        text: 'Entering a rejection reason is compulsory. The requested amount will be refunded to the affiliate.',
        input: 'textarea',
        inputPlaceholder: 'Type rejection reason here (required)...',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'Rejection reason is compulsory!';
            }
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirm Rejection',
        confirmButtonColor: '#ef4444'
    }).then(res => {
        if (res.isConfirmed && res.value) {
            fetch('api_payouts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reject', payout_id: id, rejection_reason: res.value.trim() })
            })
            .then(async r => {
                const text = await r.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }
                if (!r.ok || data.status === 'error') throw new Error(data.message || 'Failed to reject payout.');
                return data;
            })
            .then(data => {
                Swal.fire('Rejected!', data.message || 'Payout request rejected and balance refunded.', 'success');
                loadPayouts();
            })
            .catch(err => {
                Swal.fire('Error', err.message || 'Network or server error.', 'error');
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadPayouts();
});
</script>
</body>
</html>
