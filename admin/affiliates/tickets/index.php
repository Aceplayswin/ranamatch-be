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
    <title><?php echo $APP_NAME; ?>: Affiliate Support Tickets</title>
    <link href='../../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        .stat-grid-clean {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px; margin-bottom: 24px;
        }
        .stat-card-clean {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.7));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .stat-card-clean:hover {
            transform: translateY(-2px);
            border-color: rgba(56, 189, 248, 0.4);
        }
        .stat-card-clean h4 { font-size: 22px !important; font-weight: 800; margin: 4px 0 0; color: #ffffff !important; }
        .stat-card-clean span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8 !important; }
        .stat-icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-card-clean {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.4), rgba(15, 23, 42, 0.6));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px; padding: 14px 18px; margin-bottom: 20px;
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        }
        .cus-inp-clean {
            background: rgba(15, 23, 42, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important; border-radius: 10px; padding: 8px 14px; font-size: 13px;
        }
        .cus-inp-clean:focus {
            border-color: #06b6d4 !important; outline: none; box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
        }

        .r-table-wrapper {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.65));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(10px);
        }
        .r-table {
            width: 100%; border-collapse: collapse; text-align: left;
        }
        .r-table th {
            background: rgba(15, 23, 42, 0.85); color: #94a3b8; font-size: 11px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
            padding: 14px 18px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .r-table td {
            padding: 14px 18px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px; color: #f8fafc; vertical-align: middle;
        }
        .r-table tr:hover td {
            background: rgba(255, 255, 255, 0.03);
        }

        .btn-modern {
            display: inline-flex; align-items: center; gap: 8px; font-weight: 700;
            font-size: 12px; padding: 8px 16px; border-radius: 10px; border: none;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none !important;
        }
        .btn-cyan-modern {
            background: linear-gradient(135deg, #06b6d4, #0284c7); color: #fff !important;
        }
        .btn-cyan-modern:hover {
            box-shadow: 0 4px 14px rgba(6, 182, 212, 0.35); transform: translateY(-1px);
        }

        .chat-bubble-aff {
            background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px; border-top-left-radius: 4px; padding: 12px 16px;
            max-width: 80%; align-self: flex-start;
        }
        .chat-bubble-admin {
            background: linear-gradient(135deg, #0284c7, #0369a1); border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px; border-top-right-radius: 4px; padding: 12px 16px;
            max-width: 80%; align-self: flex-end; color: #fff;
        }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Affiliate Desk</span>
                <h1>Affiliate Support Tickets</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="../" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 700; color: #ffffff; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
                    <i class='bx bx-arrow-back'></i> Directory
                </a>
                <a href="../payouts/" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 700; color: #ffffff; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
                    <i class='bx bx-money-withdraw'></i> Payouts
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Total Inquiries</span>
                    <h4 id="statTotalTickets">0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);">
                    <i class='bx bx-support'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Open (Needs Attention)</span>
                    <h4 id="statOpenTickets" class="text-warning">0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(251, 191, 36, 0.15);">
                    <i class='bx bx-time-five'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>In Progress</span>
                    <h4 id="statInProgressTickets" class="text-info">0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(6, 182, 212, 0.15);">
                    <i class='bx bx-loader-circle'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Resolved & Closed</span>
                    <h4 id="statResolvedTickets" class="text-success">0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #34d399; background: rgba(52, 211, 153, 0.15);">
                    <i class='bx bx-check-circle'></i>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card-clean">
            <input type="text" id="ticketSearch" class="cus-inp-clean" style="flex: 1; min-width: 240px;" placeholder="Search partner, company, code, subject..." onkeyup="filterTickets()">
            
            <select id="statusFilter" class="cus-inp-clean" onchange="filterTickets()" style="min-width: 150px;">
                <option value="">All Statuses</option>
                <option value="open">Open (Unresolved)</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>

            <select id="priorityFilter" class="cus-inp-clean" onchange="filterTickets()" style="min-width: 140px;">
                <option value="">All Priorities</option>
                <option value="Normal">Normal</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
                <option value="Urgent">Urgent</option>
            </select>
        </div>

        <!-- Tickets Table -->
        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Partner Info</th>
                        <th>Subject & Category</th>
                        <th>Priority</th>
                        <th>Messages</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="ticketsTableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Loading affiliate tickets...</td></tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Ticket Conversation Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; color: #fff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="modal-header d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding: 16px 20px;">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="modal-title fw-bold mb-0" id="ticketModalTitle">Ticket #AFF-0</h5>
                        <span id="ticketModalStatusBadge" class="badge bg-warning text-dark">OPEN</span>
                        <span id="ticketModalPriorityBadge" class="badge bg-secondary">NORMAL</span>
                    </div>
                    <div id="ticketModalPartnerInfo" class="small mt-1" style="color: #94a3b8;">Loading partner info...</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center gap-1.5 px-2 py-1 rounded" style="background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(56, 189, 248, 0.3);">
                        <label class="small text-uppercase fw-bold mb-0" style="color: #38bdf8; font-size: 11px;">Update Status:</label>
                        <select id="modalHeaderStatusSelect" class="form-select form-select-sm cus-inp-clean py-0 px-2 fw-bold" style="font-size: 12px; width: 130px; height: 30px; background: #0f172a; color: #fff; border: 1px solid rgba(255,255,255,0.2);" onchange="updateTicketStatusFromModal(this.value)">
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            
            <div class="modal-body p-4" style="background: rgba(15,23,42,0.95);">
                <!-- Subject Header -->
                <div class="p-3 mb-3 rounded" style="background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase fw-bold text-info" style="font-size: 11px;" id="ticketModalCategory">COMMERCIAL</div>
                    <div class="fw-bold fs-6 text-white mt-0.5" id="ticketModalSubject">Inquiry Subject</div>
                </div>

                <!-- Chat History -->
                <div class="small fw-bold text-uppercase mb-2" style="color: #94a3b8; font-size: 11px;">Conversation History</div>
                <div id="ticketChatContainer" style="max-height: 320px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 12px; background: rgba(10, 15, 30, 0.6); border-radius: 12px; border: 1px solid rgba(255,255,255,0.06);" class="hide-native-scrollbar">
                    <!-- Dynamic chat messages -->
                </div>

                <!-- Admin Reply Form -->
                <form id="replyForm" onsubmit="submitAdminReply(event)" class="mt-3">
                    <input type="hidden" id="replyTicketId" value="0">
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; font-size: 11px;">Send Reply to Partner</label>
                        </div>
                        <div class="col-4 text-end">
                            <label class="small text-uppercase fw-bold" style="color: #94a3b8; font-size: 11px; margin-right: 6px;">Status on Reply:</label>
                            <select id="replyStatusSelect" onchange="updateTicketStatusFromModal(this.value)" class="form-select form-select-sm d-inline-block w-auto" style="background-color: #0f172a; color: #fff; border-color: rgba(255,255,255,0.2); font-size: 11px;">
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                                <option value="open">Keep Open</option>
                            </select>
                        </div>
                    </div>
                    <textarea id="replyMessageText" rows="3" class="form-control mb-2" placeholder="Write official response to affiliate partner..." required style="background: rgba(15, 23, 42, 0.8); border-color: rgba(255, 255, 255, 0.15); color: #fff; font-size: 13px;"></textarea>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small" style="color: #94a3b8; font-size: 11px;">The partner will see your reply immediately in their portal.</div>
                        <button type="submit" id="replySubmitBtn" class="btn btn-primary btn-sm fw-bold px-3 py-1.5" style="background: linear-gradient(135deg, #06b6d4, #0284c7); border: none; border-radius: 8px;">
                            <i class='bx bx-send'></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let allTickets = [];

async function loadTickets() {
    try {
        const res = await fetch('api_tickets.php');
        const data = await res.json();
        if (data.status === 'success') {
            allTickets = data.data || [];
            
            if (data.summary) {
                document.getElementById('statTotalTickets').innerText = data.summary.total || 0;
                document.getElementById('statOpenTickets').innerText = data.summary.open || 0;
                document.getElementById('statInProgressTickets').innerText = data.summary.in_progress || 0;
                document.getElementById('statResolvedTickets').innerText = data.summary.resolved || 0;
            }

            filterTickets();
        }
    } catch (err) {
        console.error("Failed to load tickets:", err);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function filterTickets() {
    const q = document.getElementById('ticketSearch').value.toLowerCase();
    const st = document.getElementById('statusFilter').value.toLowerCase();
    const pr = document.getElementById('priorityFilter').value.toLowerCase();

    const filtered = allTickets.filter(t => {
        const matchQ = (t.subject || '').toLowerCase().includes(q) ||
                       (t.affiliate_name || '').toLowerCase().includes(q) ||
                       (t.company_name || '').toLowerCase().includes(q) ||
                       (t.affiliate_code || '').toLowerCase().includes(q) ||
                       (t.phone || '').toLowerCase().includes(q);
        const matchSt = !st || (t.status || '').toLowerCase() === st;
        const matchPr = !pr || (t.priority || '').toLowerCase() === pr;
        return matchQ && matchSt && matchPr;
    });

    renderTable(filtered);
}

function renderTable(data) {
    const tbody = document.getElementById('ticketsTableBody');
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No affiliate support inquiries found.</td></tr>`;
        return;
    }

    data.forEach(t => {
        let prBadge = 'text-slate-400 border-secondary';
        if (t.priority === 'Urgent') prBadge = 'bg-danger text-white border-danger';
        else if (t.priority === 'High') prBadge = 'bg-warning text-dark border-warning';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong class="font-monospace text-info">#AFF-${t.id}</strong></td>
            <td>
                <div class="fw-bold text-white">${escapeHtml(t.affiliate_name)}</div>
                ${t.company_name ? `<div style="font-size: 11px; color: #cbd5e1;"><i class='bx bx-buildings'></i> ${escapeHtml(t.company_name)}</div>` : ''}
                ${t.phone ? `<div style="font-size: 11px; color: #94a3b8;"><i class='bx bx-phone'></i> ${escapeHtml(t.phone)}</div>` : ''}
                <div style="font-size: 11px; color: #38bdf8;" class="font-monospace">Code: ${escapeHtml(t.affiliate_code)}</div>
            </td>
            <td>
                <div class="fw-bold text-white">${escapeHtml(t.subject)}</div>
                <div style="font-size: 11px; color: #94a3b8;">Category: <span class="text-info">${escapeHtml(t.category)}</span></div>
            </td>
            <td><span class="badge ${prBadge} border">${escapeHtml(t.priority)}</span></td>
            <td><span class="badge bg-dark border border-secondary">${t.total_messages || 1} msg</span></td>
            <td>
                <select class="form-select form-select-sm cus-inp-clean py-0 px-2 fw-bold" 
                        style="font-size: 11px; width: 125px; height: 30px; cursor: pointer; border-color: ${t.status === 'resolved' ? '#10b981' : (t.status === 'in_progress' ? '#06b6d4' : (t.status === 'closed' ? '#64748b' : '#f59e0b'))};" 
                        onchange="changeTableTicketStatus(${t.id}, this.value)">
                    <option value="open" ${t.status === 'open' ? 'selected' : ''}>OPEN</option>
                    <option value="in_progress" ${t.status === 'in_progress' ? 'selected' : ''}>IN PROGRESS</option>
                    <option value="resolved" ${t.status === 'resolved' ? 'selected' : ''}>RESOLVED</option>
                    <option value="closed" ${t.status === 'closed' ? 'selected' : ''}>CLOSED</option>
                </select>
            </td>
            <td style="font-size: 12px; color: #cbd5e1;">${escapeHtml(t.formatted_date)}</td>
            <td class="text-end">
                <button type="button" onclick="openTicketModal(${t.id})" class="btn btn-sm btn-cyan-modern">
                    <i class='bx bx-message-dots'></i> View & Reply
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updateSummaryCounts() {
    const total = allTickets.length;
    const open = allTickets.filter(t => t.status === 'open').length;
    const in_progress = allTickets.filter(t => t.status === 'in_progress').length;
    const resolved = allTickets.filter(t => t.status === 'resolved').length;
    const elTot = document.getElementById('statTotalTickets'); if (elTot) elTot.innerText = total;
    const elOpn = document.getElementById('statOpenTickets'); if (elOpn) elOpn.innerText = open;
    const elInp = document.getElementById('statInProgressTickets'); if (elInp) elInp.innerText = in_progress;
    const elRes = document.getElementById('statResolvedTickets'); if (elRes) elRes.innerText = resolved;
}

async function changeTableTicketStatus(ticketId, newStatus) {
    try {
        const res = await fetch('api_tickets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'status', ticket_id: ticketId, status: newStatus })
        });
        const data = await res.json();
        if (data.status === 'success') {
            const ticket = allTickets.find(t => t.id === ticketId);
            if (ticket) ticket.status = newStatus;
            updateSummaryCounts();
            filterTickets();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: data.message || `Status updated to ${newStatus.toUpperCase()}`,
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to update status', 'error');
            loadTickets();
        }
    } catch (err) {
        console.error('Table status update error:', err);
        Swal.fire('Error', 'Server connection error', 'error');
        loadTickets();
    }
}

async function updateTicketStatusFromModal(newStatus) {
    const ticketId = parseInt(document.getElementById('replyTicketId').value);
    if (!ticketId) return;

    try {
        const res = await fetch('api_tickets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'status', ticket_id: ticketId, status: newStatus })
        });
        const data = await res.json();
        if (data.status === 'success') {
            // Update modal status badge
            const stBadge = document.getElementById('ticketModalStatusBadge');
            stBadge.innerText = newStatus.toUpperCase().replace('_', ' ');
            stBadge.className = `badge ${newStatus === 'resolved' ? 'bg-success' : (newStatus === 'in_progress' ? 'bg-info text-dark' : (newStatus === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'))}`;

            // Sync selects
            const hSel = document.getElementById('modalHeaderStatusSelect');
            if (hSel) hSel.value = newStatus;
            const rSel = document.getElementById('replyStatusSelect');
            if (rSel) rSel.value = newStatus;

            // Update in local memory and background table
            const ticket = allTickets.find(t => t.id === ticketId);
            if (ticket) ticket.status = newStatus;
            updateSummaryCounts();
            filterTickets();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: data.message || `Status changed to ${newStatus.toUpperCase()}`,
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to update status', 'error');
        }
    } catch (err) {
        console.error('Modal status update error:', err);
        Swal.fire('Error', 'Server connection error', 'error');
    }
}

async function openTicketModal(ticketId) {
    try {
        const res = await fetch(`api_tickets.php?ticket_id=${ticketId}`);
        const data = await res.json();
        if (data.status !== 'success' || !data.data) {
            Swal.fire('Error', 'Failed to load ticket conversation', 'error');
            return;
        }

        const { ticket, messages } = data.data;
        document.getElementById('ticketModalTitle').innerText = `Ticket #AFF-${ticket.id}`;
        document.getElementById('ticketModalPartnerInfo').innerHTML = `
            <strong>${escapeHtml(ticket.full_name || ticket.company_name)}</strong> &bull; 
            Code: <span class="font-monospace text-info">${escapeHtml(ticket.affiliate_code)}</span> &bull; 
            Phone: <span class="text-success">${escapeHtml(ticket.phone || 'N/A')}</span> &bull; 
            Email: <span class="text-light">${escapeHtml(ticket.email)}</span>
        `;
        document.getElementById('ticketModalCategory').innerText = (ticket.category || 'COMMERCIAL').toUpperCase();
        document.getElementById('ticketModalSubject').innerText = ticket.subject;

        const curStatus = ticket.status || 'open';
        const stBadge = document.getElementById('ticketModalStatusBadge');
        stBadge.innerText = curStatus.toUpperCase().replace('_', ' ');
        stBadge.className = `badge ${curStatus === 'resolved' ? 'bg-success' : (curStatus === 'in_progress' ? 'bg-info text-dark' : (curStatus === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'))}`;

        const prBadge = document.getElementById('ticketModalPriorityBadge');
        prBadge.innerText = (ticket.priority || 'Normal').toUpperCase();

        document.getElementById('replyTicketId').value = ticket.id;
        document.getElementById('replyMessageText').value = '';

        // Preset status selects to current ticket status
        const hSel = document.getElementById('modalHeaderStatusSelect');
        if (hSel) hSel.value = curStatus;
        const rSel = document.getElementById('replyStatusSelect');
        if (rSel) rSel.value = curStatus;

        // Render messages
        const chatContainer = document.getElementById('ticketChatContainer');
        chatContainer.innerHTML = '';

        if (!messages || messages.length === 0) {
            chatContainer.innerHTML = `<div class="text-center text-muted py-3">No messages yet.</div>`;
        } else {
            messages.forEach(m => {
                const isAdmin = m.sender_type === 'admin';
                const bubble = document.createElement('div');
                bubble.className = isAdmin ? 'chat-bubble-admin' : 'chat-bubble-aff';
                bubble.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1 gap-2" style="font-size: 10px; opacity: 0.85;">
                        <strong>${isAdmin ? 'Admin Support' : 'Partner (' + escapeHtml(ticket.full_name || ticket.affiliate_code) + ')'}</strong>
                        <span>${m.created_at}</span>
                    </div>
                    <div style="white-space: pre-wrap; font-size: 13px;">${escapeHtml(m.message)}</div>
                `;
                chatContainer.appendChild(bubble);
            });
            setTimeout(() => { chatContainer.scrollTop = chatContainer.scrollHeight; }, 100);
        }

        const modalEl = document.getElementById('ticketModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        console.error("Open ticket modal error:", err);
    }
}

async function submitAdminReply(e) {
    e.preventDefault();
    const ticketId = parseInt(document.getElementById('replyTicketId').value);
    const message = document.getElementById('replyMessageText').value.trim();
    const status = document.getElementById('replyStatusSelect').value;
    const btn = document.getElementById('replySubmitBtn');

    if (!ticketId || !message) return;
    btn.disabled = true;

    try {
        const res = await fetch('api_tickets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reply', ticket_id: ticketId, message: message, status: status })
        });
        const data = await res.json();
        btn.disabled = false;

        if (data.status === 'success') {
            document.getElementById('replyMessageText').value = '';
            openTicketModal(ticketId); // Refresh conversation
            loadTickets(); // Refresh background list
        } else {
            Swal.fire('Error', data.message || 'Failed to send reply', 'error');
        }
    } catch (err) {
        btn.disabled = false;
        console.error(err);
        Swal.fire('Error', 'Server connection error', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadTickets);
</script>
</body>
</html>
