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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Agents</title>
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
        select.cus-inp-clean, select {
            color-scheme: dark !important;
            background: #0f172a !important;
            background-color: #0f172a !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px; padding: 8px 14px;
            font-size: 13px; font-weight: 600; outline: none; height: 40px;
        }
        select.cus-inp-clean option, select option, option {
            background-color: #ffffff !important;
            color: #0f172a !important;
            font-weight: 600 !important;
        }
        .cus-inp-clean:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .cus-inp-clean::placeholder { color: #94a3b8 !important; font-weight: 500; }

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

        /* Vibrant Glowing Badges */
        .tag {
            padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;
        }
        .tag-success { background: rgba(16, 185, 129, 0.18); color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.4); }
        .tag-warning { background: rgba(245, 158, 11, 0.18); color: #fbbf24 !important; border: 1px solid rgba(245, 158, 11, 0.4); }
        .tag-purple { background: rgba(168, 85, 247, 0.18); color: #c084fc !important; border: 1px solid rgba(168, 85, 247, 0.4); }
        .tag-blue { background: rgba(59, 130, 246, 0.18); color: #60a5fa !important; border: 1px solid rgba(59, 130, 246, 0.4); }

        /* High Contrast Crisp Typography */
        .text-bold-white { color: #ffffff !important; font-weight: 700; font-size: 14px; }
        .text-cyan-code { color: #38bdf8 !important; font-weight: 700; font-family: monospace; font-size: 12px; }
        .text-bright-sub { color: #cbd5e1 !important; font-weight: 600; font-size: 12px; }
        .text-bright-emerald { color: #34d399 !important; font-weight: 800; font-size: 14px; }
        .text-date-bright { color: #e2e8f0 !important; font-weight: 600; font-size: 12px; }

        /* Modern Action Buttons */
        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 12px;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            text-decoration: none !important; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
        .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff !important; }
        .btn-green-modern { background: rgba(16, 185, 129, 0.2); color: #34d399 !important; border: 1px solid #10b981; }

        .btn-icon-btn {
            width: 34px; height: 34px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 17px; border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05); color: #f8fafc !important;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none !important;
        }
        .btn-icon-btn:hover { background: rgba(56, 189, 248, 0.2); border-color: #38bdf8; color: #ffffff !important; }

        /* Credit Adjustment Modal Styling */
        .credit-modal-content {
            background: #090e1a !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 20px !important;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7), 0 0 35px rgba(16, 185, 129, 0.15) !important;
            overflow: hidden;
        }
        .credit-modal-header {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.5)) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 20px 24px !important;
        }
        .credit-icon-badge {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981; display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-right: 14px;
        }
        .credit-mode-switcher {
            display: flex; gap: 8px; background: #020617;
            padding: 5px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .credit-mode-pill {
            flex: 1; text-align: center; padding: 10px 14px; border-radius: 8px;
            font-weight: 700; font-size: 13px; color: #94a3b8; cursor: pointer;
            transition: all 0.2s ease; border: none; background: transparent;
        }
        .credit-mode-pill.active-deposit {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: #ffffff !important; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .credit-mode-pill.active-withdraw {
            background: linear-gradient(135deg, #f43f5e, #be123c) !important;
            color: #ffffff !important; box-shadow: 0 4px 14px rgba(244, 63, 94, 0.3);
        }
        .credit-preview-box {
            background: #020617; border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px; padding: 16px 20px;
        }
        .credit-chip {
            background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);
            color: #38bdf8; border-radius: 8px; padding: 6px 12px; font-size: 12px;
            font-weight: 700; cursor: pointer; transition: all 0.2s ease;
        }
        .credit-chip:hover {
            background: rgba(56, 189, 248, 0.15); border-color: #38bdf8; color: #ffffff;
        }
        .reason-chip {
            background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
            color: #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px;
            font-weight: 600; cursor: pointer; transition: all 0.2s ease;
        }
        .reason-chip:hover {
            background: rgba(255, 255, 255, 0.1); color: #ffffff;
        }
    </style>
</head>
<body style="background-color: var(--page-bg) !important;">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Agent System</span>
                <h1>Agents Directory</h1>
            </div>
            <div class="d-flex gap-2">
                <button onclick="openCreateAgentModal()" class="btn-modern btn-green-modern">
                    <i class='bx bx-plus-circle fs-5'></i> Add Agent
                </button>
                <a href="applications/" class="btn-modern btn-pink-modern">
                    <i class='bx bx-git-pull-request fs-5'></i> Applications
                </a>
                <a href="credit/" class="btn-modern btn-green-modern">
                    <i class='bx bx-wallet fs-5'></i> Credit Audit
                </a>
                <a href="settings/" class="btn-modern btn-primary-modern">
                    <i class='bx bx-cog fs-5'></i> Settings
                </a>
            </div>
        </div>

        <!-- Summary Bar -->
        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <div>
                    <span>Total Agents</span>
                    <h4 id="statTotalAgents">0 Agents</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);">
                    <i class='bx bx-group'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Total Active Credit</span>
                    <h4 id="statTotalCredit" class="text-bright-emerald">₹ 0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #34d399; background: rgba(52, 211, 153, 0.15);">
                    <i class='bx bx-wallet'></i>
                </div>
            </div>
            <div class="stat-card-clean">
                <div>
                    <span>Total Exposure</span>
                    <h4 id="statTotalExposure" style="color:#fbbf24 !important">₹ 0</h4>
                </div>
                <div class="stat-icon-wrapper" style="color: #fbbf24; background: rgba(245, 158, 11, 0.15);">
                    <i class='bx bx-line-chart-down'></i>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card-clean">
            <input type="text" id="agentSearchInput" class="cus-inp-clean" style="flex: 1; min-width: 220px;" placeholder="Search name, code, upline..." onkeyup="filterAgentsTable()">
            
            <select id="levelFilter" class="cus-inp-clean" style="min-width: 170px;" onchange="filterAgentsTable()">
                <option value="">All Levels</option>
                <option value="Senior Super Agent">Senior Super Agent</option>
                <option value="Super Agent">Super Agent</option>
                <option value="Master Agent">Master Agent</option>
                <option value="Agent">Agent</option>
            </select>

            <select id="statusFilter" class="cus-inp-clean" style="min-width: 160px;" onchange="filterAgentsTable()">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Suspended">Suspended</option>
                <option value="Locked">Locked</option>
            </select>
        </div>

        <!-- Clean Table -->
        <div class="r-table-wrapper">
            <table class="r-table" id="agentsTable">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Terms (Share/Comm)</th>
                        <th>Credit & Exposed</th>
                        <th>Unsettled P&L</th>
                        <th>Downline</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="agentsTableBody">
                    <!-- Populated via JavaScript -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let agentsData = [];

async function loadAgents() {
    try {
        const res = await fetch('api_list.php');
        const data = await res.json();
        if (data.status === 'success') {
            if (data.summary) {
                const totalCount = (data.summary.active_agents || 0) + (data.summary.suspended_agents || 0);
                document.getElementById('statTotalAgents').innerText = totalCount + ' Agents';
                document.getElementById('statTotalCredit').innerText = '₹ ' + (data.summary.total_credit_outstanding || 0).toLocaleString('en-IN');
                document.getElementById('statTotalExposure').innerText = '₹ ' + (data.summary.total_exposure || 0).toLocaleString('en-IN');
            }

            agentsData = (data.data || []).map(a => ({
                id: a.id,
                name: a.name || a.username,
                code: a.agent_code,
                parent: a.parent_agent_name || a.parent_name || 'Root Platform',
                level: (a.rank_level === 'senior_super_agent' || a.rank_level === 1) ? 'Senior Super Agent' : ((a.rank_level === 'super_agent' || a.rank_level === 2) ? 'Super Agent' : ((a.rank_level === 'master_agent' || a.rank_level === 3) ? 'Master Agent' : 'Agent')),
                status: a.status ? (a.status.charAt(0).toUpperCase() + a.status.slice(1)) : 'Active',
                share: a.partnership_pct !== undefined ? a.partnership_pct : (a.revshare_pct || 0),
                comm: a.turnover_commission_pct !== undefined ? a.turnover_commission_pct : (a.commission_rate || 0),
                credit: a.current_credit !== undefined ? a.current_credit : (a.current_balance || 0),
                exposed: a.exposed_credit !== undefined ? a.exposed_credit : (a.exposure_amount || 0),
                pnl: a.unsettled_pnl || 0,
                subAgents: a.downline_agents_count !== undefined ? a.downline_agents_count : (a.downlines_count || 0),
                players: a.downline_players_count !== undefined ? a.downline_players_count : (a.players_count || 0),
                joined: a.created_at ? a.created_at.split(' ')[0] : ''
            }));

            filterAgentsTable();
        }
    } catch (err) {
        console.error("Failed to load agents:", err);
    }
}

function renderAgentsTable(data) {
    const tbody = document.getElementById('agentsTableBody');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">No agents found.</td></tr>`;
        return;
    }

    data.forEach(ag => {
        let tagClass = 'tag-blue';
        if (ag.level === 'Senior Super Agent') tagClass = 'tag-purple';
        else if (ag.level === 'Super Agent') tagClass = 'tag-blue';
        else if (ag.level === 'Master Agent') tagClass = 'tag-warning';

        let statusTag = ag.status === 'Active' ? 'tag-success' : 'tag-warning';

        let pnlFormatted = ag.pnl >= 0 ? `+₹ ${(ag.pnl || 0).toLocaleString('en-IN')}` : `-₹ ${Math.abs(ag.pnl || 0).toLocaleString('en-IN')}`;
        let pnlColor = ag.pnl >= 0 ? '#34d399' : '#f43f5e';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="text-bold-white">${ag.name}</div>
                <div><span style="color:#cbd5e1; font-size:11px;">${ag.code} &bull; Upline:</span> <span class="text-cyan-code">${ag.parent}</span></div>
            </td>
            <td><span class="tag ${tagClass}">${ag.level}</span></td>
            <td><span class="tag ${statusTag}">${ag.status}</span></td>
            <td>
                <div class="text-bold-white">Share: <strong>${ag.share}%</strong></div>
                <div class="text-bright-sub">Comm: ${ag.comm}%</div>
            </td>
            <td>
                <div class="text-bright-emerald">₹ ${(ag.credit || 0).toLocaleString('en-IN')}</div>
                <div class="text-bright-sub">Exp: ₹ ${(ag.exposed || 0).toLocaleString('en-IN')}</div>
            </td>
            <td class="fw-bold" style="color:${pnlColor}">${pnlFormatted}</td>
            <td>
                <div class="text-bold-white">${ag.subAgents} Subs</div>
                <div class="text-bright-sub">${ag.players} Players</div>
            </td>
            <td><span class="text-date-bright">${ag.joined}</span></td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    <a href="detail/?id=${ag.id}" class="btn-icon-btn" title="View Detail">
                        <i class='bx bx-show'></i>
                    </a>
                    <button onclick="openCreditModal('${ag.id}', '${ag.name}', '${ag.credit}')" class="btn-icon-btn text-emerald-400" title="Credit Adjustment" style="color: #34d399 !important;">
                        <i class='bx bx-wallet'></i>
                    </button>
                    <button onclick="changeAgentPassword('${ag.id}', '${ag.name}')" class="btn-icon-btn" title="Change Password">
                        <i class='bx bx-key'></i>
                    </button>
                    <button onclick="toggleAgentStatus('${ag.id}')" class="btn-icon-btn" title="Toggle Status">
                        <i class='bx ${ag.status === 'Active' ? 'bx-pause-circle' : 'bx-play-circle'}'></i>
                    </button>
                    <button onclick="deleteAgent('${ag.id}')" class="btn-icon-btn" title="Delete">
                        <i class='bx bx-trash'></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function filterAgentsTable() {
    const q = document.getElementById('agentSearchInput').value.toLowerCase();
    const level = document.getElementById('levelFilter').value;
    const status = document.getElementById('statusFilter').value;

    const filtered = agentsData.filter(a => {
        const matchesQuery = a.name.toLowerCase().includes(q) || a.code.toLowerCase().includes(q) || a.parent.toLowerCase().includes(q);
        const matchesLevel = level === '' || a.level.toLowerCase() === level.toLowerCase();
        const matchesStatus = status === '' || a.status.toLowerCase() === status.toLowerCase();
        return matchesQuery && matchesLevel && matchesStatus;
    });

    renderAgentsTable(filtered);
}

function changeAgentPassword(id, name) {
    Swal.fire({
        title: `Change Password for ${name}`,
        input: 'password',
        inputLabel: 'New Password (min 6 characters)',
        inputPlaceholder: 'Enter new password',
        inputAttributes: {
            minlength: 6,
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Update Password',
        confirmButtonColor: '#3b82f6',
        preConfirm: (pass) => {
            if (!pass || pass.length < 6) {
                Swal.showValidationMessage('Password must be at least 6 characters');
                return false;
            }
            return pass;
        }
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            fetch('api_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: id, new_password: res.value })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Password Updated!', data.message || `Password for ${name} updated successfully.`, 'success');
                } else {
                    Swal.fire('Error', data.message || 'Failed to update password.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network connection failed: ' + err.message, 'error');
            });
        }
    });
}

function toggleAgentStatus(id) {
    const ag = agentsData.find(a => a.id == id);
    if (!ag) return;
    const nextStatus = ag.status === 'Active' ? 'suspended' : 'active';

    Swal.fire({
        title: `Change Status?`,
        text: `Set ${ag.name} status to ${nextStatus}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirm'
    }).then((res) => {
        if (res.isConfirmed) {
            fetch('status/api_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: id, status: nextStatus })
            })
            .then(r => r.json())
            .then(() => {
                Swal.fire('Status Updated', `Agent is now ${nextStatus}.`, 'success');
                loadAgents();
            });
        }
    });
}

function deleteAgent(id) {
    const ag = agentsData.find(a => a.id == id);
    if (!ag) return;

    Swal.fire({
        title: `Delete Agent ${ag.name}?`,
        html: `<p style="color:#94a3b8;font-size:0.9rem;margin-bottom:12px;">This action will archive all financial history, earnings, player lists & downlines before permanent deletion.</p>
               <label style="display:block;text-align:left;color:#cbd5e1;font-size:0.85rem;margin-bottom:6px;font-weight:600;">Reason for Deletion <span style="color:#ef4444">*</span></label>`,
        input: 'text',
        inputPlaceholder: 'e.g. Violation of terms, Voluntary termination, System cleanup...',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'Please enter a valid reason for deleting this account.';
            }
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Archive & Delete'
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            fetch('api_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: id, reason: res.value.trim() })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Account Deleted & Archived', data.message || 'Agent deleted permanently and archived.', 'success');
                    loadAgents();
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete agent', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network connection failed: ' + err.message, 'error');
            });
        }
    });
}

function openCreateAgentModal() {
    const parentSelect = document.getElementById('newAgentParent');
    parentSelect.innerHTML = '<option value="">Root Platform (Top-Level)</option>';
    agentsData.forEach(a => {
        parentSelect.innerHTML += `<option value="${a.id}">${a.name} (${a.code}) - ${a.level}</option>`;
    });
    const modal = new bootstrap.Modal(document.getElementById('createAgentModal'));
    modal.show();
}

async function submitCreateAgent(e) {
    e.preventDefault();
    const name = document.getElementById('newAgentName').value.trim();
    const username = document.getElementById('newAgentUsername').value.trim();
    const email = document.getElementById('newAgentEmail').value.trim();
    const password = document.getElementById('newAgentPassword').value.trim();
    const rank_level = document.getElementById('newAgentRank').value;
    const partnership_pct = parseFloat(document.getElementById('newAgentPartnership').value) || 50.0;
    const turnover_commission_pct = parseFloat(document.getElementById('newAgentCommission').value) || 2.5;
    const opening_credit = parseFloat(document.getElementById('newAgentCredit').value) || 0.0;
    const parent_id = document.getElementById('newAgentParent').value;

    try {
        const res = await fetch('api_create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name, username, email, password, rank_level, partnership_pct, turnover_commission_pct, opening_credit, parent_id
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire('Success', 'Agent account created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createAgentModal')).hide();
            document.getElementById('createAgentForm').reset();
            loadAgents();
        } else {
            Swal.fire('Error', data.message || 'Failed to create agent', 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Network request failed: ' + err.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadAgents();
});
</script>

<!-- Create Agent Modal -->
<div class="modal fade" id="createAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 16px;">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title font-bold text-cyan-code"><i class='bx bx-user-plus me-2'></i>Create New Agent Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createAgentForm" onsubmit="submitCreateAgent(event)">
                <div class="modal-body space-y-3" style="font-size: 13px;">
                    <div class="mb-3">
                        <label class="form-label text-bright-sub font-bold">Full Contact Name *</label>
                        <input type="text" id="newAgentName" class="cus-inp-clean w-100" placeholder="e.g. Rahul Sharma" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-bright-sub font-bold">Agent Username *</label>
                        <input type="text" id="newAgentUsername" class="cus-inp-clean w-100" placeholder="e.g. rahul_master" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-bright-sub font-bold">Email Address</label>
                            <input type="email" id="newAgentEmail" class="cus-inp-clean w-100" placeholder="Optional email">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-bright-sub font-bold">Initial Password</label>
                            <input type="text" id="newAgentPassword" class="cus-inp-clean w-100" placeholder="Default: agent123">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-bright-sub font-bold">Agent Rank Level</label>
                            <select id="newAgentRank" class="cus-inp-clean w-100">
                                <option value="senior_super_agent">Senior Super Agent</option>
                                <option value="super_agent">Super Agent</option>
                                <option value="master_agent">Master Agent</option>
                                <option value="agent" selected>Agent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-bright-sub font-bold">Parent Upline</label>
                            <select id="newAgentParent" class="cus-inp-clean w-100">
                                <option value="">Root Platform (Top-Level)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label text-bright-sub font-bold">Partnership %</label>
                            <input type="number" step="0.1" id="newAgentPartnership" class="cus-inp-clean w-100" value="50.0">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-bright-sub font-bold">Turnover Comm %</label>
                            <input type="number" step="0.1" id="newAgentCommission" class="cus-inp-clean w-100" value="2.5">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-bright-sub font-bold">Opening Credit (₹)</label>
                            <input type="number" id="newAgentCredit" class="cus-inp-clean w-100" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">Create Agent Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Credit Adjustment Modal -->
<div class="modal fade" id="creditAdjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content credit-modal-content">
            <div class="credit-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="credit-icon-badge" id="creditModalIcon">
                        <i class='bx bx-wallet-alt'></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0 fs-6">Platform Credit Adjustment</h5>
                        <div class="text-slate-400 text-xs mt-0.5">
                            Target Account: <span class="text-cyan-code fw-bold" id="creditModalAgentName">Agent</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="creditAdjustmentForm" onsubmit="submitCreditAdjustment(event)">
                <div class="modal-body p-4">
                    <!-- Segmented Mode Switcher -->
                    <div class="mb-4">
                        <label class="dark-field-label" style="font-size:10px; font-weight:800; text-transform:uppercase; color:#94a3b8;">TRANSACTION TYPE</label>
                        <div class="credit-mode-switcher">
                            <button type="button" id="btnModeDeposit" class="credit-mode-pill active-deposit" onclick="setCreditMode('deposit')">
                                <i class='bx bx-plus-circle me-1'></i> Deposit / Inject Float (+)
                            </button>
                            <button type="button" id="btnModeWithdraw" class="credit-mode-pill" onclick="setCreditMode('withdraw')">
                                <i class='bx bx-minus-circle me-1'></i> Withdraw / Claw Back (-)
                            </button>
                        </div>
                    </div>

                    <!-- Live Calculation Card -->
                    <div class="credit-preview-box mb-4">
                        <div class="row g-2 align-items-center text-xs">
                            <div class="col-4">
                                <span class="text-slate-400 font-semibold block mb-1">Current Balance</span>
                                <div class="fw-bold text-white fs-6" id="creditModalCurrentBalance">₹ 0</div>
                            </div>
                            <div class="col-4 text-center border-start border-end border-secondary border-opacity-25">
                                <span class="text-slate-400 font-semibold block mb-1">Adjustment</span>
                                <div class="text-emerald-400 fw-bold fs-6" id="creditPreviewAdjustment">+ ₹ 0</div>
                            </div>
                            <div class="col-4 text-end">
                                <span class="text-slate-400 font-semibold block mb-1">New Balance</span>
                                <div class="fw-bold text-white fs-6" id="creditPreviewNewBalance">₹ 0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Amount Chips -->
                    <div class="mb-3">
                        <label class="dark-field-label" style="font-size:10px; font-weight:800; text-transform:uppercase; color:#94a3b8;">QUICK AMOUNTS (₹)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="credit-chip" onclick="selectCreditChip(1000)">+ ₹1,000</button>
                            <button type="button" class="credit-chip" onclick="selectCreditChip(5000)">+ ₹5,000</button>
                            <button type="button" class="credit-chip" onclick="selectCreditChip(10000)">+ ₹10,000</button>
                            <button type="button" class="credit-chip" onclick="selectCreditChip(50000)">+ ₹50,000</button>
                            <button type="button" class="credit-chip" onclick="selectCreditChip(100000)">+ ₹1,00,000</button>
                        </div>
                    </div>

                    <!-- Amount Input -->
                    <div class="mb-4">
                        <label class="dark-field-label" style="font-size:10px; font-weight:800; text-transform:uppercase; color:#94a3b8;">AMOUNT (₹)</label>
                        <div class="position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-slate-400 fw-bold fs-6">₹</span>
                            <input type="number" step="any" min="0.01" id="creditAmountInput" class="cus-inp-clean ps-5 fs-6 fw-bold text-emerald-400 w-100" placeholder="0.00" oninput="updateCreditPreview()" required>
                        </div>
                    </div>

                    <!-- Remark / Reason -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="dark-field-label mb-0" style="font-size:10px; font-weight:800; text-transform:uppercase; color:#94a3b8;">REMARK / TRANSACTION REASON</label>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            <span class="reason-chip" onclick="selectRemarkChip('Opening Float Injection')">Opening Float</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Weekly P&L Settlement')">Weekly Settlement</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Bonus Credit Top-up')">Bonus Top-up</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Float Clawback Correction')">Clawback Correction</span>
                        </div>
                        <textarea id="creditRemarkInput" class="cus-inp-clean w-100" rows="2" placeholder="e.g. Platform float adjustment for Q3 launch" style="height:auto;"></textarea>
                    </div>
                </div>

                <div class="modal-footer p-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300 font-bold px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" id="creditSubmitBtn" class="btn btn-sm font-bold text-white px-4 py-2" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        Confirm Credit Deposit (+)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentCreditAgentId = null;
let currentCreditAgentBalance = 0;
let creditMode = 'deposit';

function openCreditModal(id, name, balance) {
    currentCreditAgentId = id;
    const ag = agentsData.find(a => a.id == id);
    const targetName = name || (ag ? ag.name : 'Agent');
    currentCreditAgentBalance = parseFloat(balance !== undefined ? balance : (ag ? ag.credit : 0));
    
    document.getElementById('creditModalAgentName').innerText = targetName;
    document.getElementById('creditModalCurrentBalance').innerText = '₹ ' + currentCreditAgentBalance.toLocaleString('en-IN');
    document.getElementById('creditAmountInput').value = '';
    document.getElementById('creditRemarkInput').value = '';
    setCreditMode('deposit');
    updateCreditPreview();

    const modal = new bootstrap.Modal(document.getElementById('creditAdjustmentModal'));
    modal.show();
}

function setCreditMode(mode) {
    creditMode = mode;
    const depBtn = document.getElementById('btnModeDeposit');
    const witBtn = document.getElementById('btnModeWithdraw');
    const submitBtn = document.getElementById('creditSubmitBtn');

    if (mode === 'deposit') {
        depBtn.className = 'credit-mode-pill active-deposit';
        witBtn.className = 'credit-mode-pill';
        submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        submitBtn.innerText = 'Confirm Credit Deposit (+)';
    } else {
        witBtn.className = 'credit-mode-pill active-withdraw';
        depBtn.className = 'credit-mode-pill';
        submitBtn.style.background = 'linear-gradient(135deg, #f43f5e 0%, #be123c 100%)';
        submitBtn.innerText = 'Confirm Credit Withdrawal (-)';
    }
    updateCreditPreview();
}

function selectCreditChip(amt) {
    document.getElementById('creditAmountInput').value = amt;
    updateCreditPreview();
}

function selectRemarkChip(txt) {
    document.getElementById('creditRemarkInput').value = txt;
}

function updateCreditPreview() {
    const rawVal = parseFloat(document.getElementById('creditAmountInput').value) || 0;
    const adjVal = creditMode === 'deposit' ? rawVal : -rawVal;
    const newBal = currentCreditAgentBalance + adjVal;

    const previewAdj = document.getElementById('creditPreviewAdjustment');
    const previewNew = document.getElementById('creditPreviewNewBalance');

    if (creditMode === 'deposit') {
        previewAdj.className = 'text-emerald-400 fw-bold fs-6';
        previewAdj.innerText = '+ ₹ ' + rawVal.toLocaleString('en-IN');
    } else {
        previewAdj.className = 'text-rose-400 fw-bold fs-6';
        previewAdj.innerText = '- ₹ ' + rawVal.toLocaleString('en-IN');
    }

    if (newBal < 0) {
        previewNew.className = 'text-rose-500 fw-bold fs-6';
        previewNew.innerText = '₹ ' + newBal.toLocaleString('en-IN') + ' (Exceeds Credit!)';
    } else {
        previewNew.className = 'text-white fw-bold fs-6';
        previewNew.innerText = '₹ ' + newBal.toLocaleString('en-IN');
    }
}

async function submitCreditAdjustment(e) {
    e.preventDefault();
    if (!currentCreditAgentId) return;

    const rawAmt = parseFloat(document.getElementById('creditAmountInput').value);
    if (!rawAmt || rawAmt <= 0) {
        Swal.fire('Invalid Amount', 'Please enter a valid credit amount greater than 0.', 'warning');
        return;
    }

    const finalAmount = creditMode === 'deposit' ? rawAmt : -rawAmt;
    const remark = document.getElementById('creditRemarkInput').value.trim() || (creditMode === 'deposit' ? 'Platform float injection' : 'Platform float clawback');

    if (creditMode === 'withdraw' && Math.abs(finalAmount) > currentCreditAgentBalance) {
        Swal.fire('Insufficient Balance', 'Clawback amount cannot exceed current credit balance.', 'error');
        return;
    }

    const submitBtn = document.getElementById('creditSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Processing...`;

    try {
        const res = await fetch('credit/api_credit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                agent_id: currentCreditAgentId,
                amount: finalAmount,
                remark: remark
            })
        });
        const data = await res.json();

        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('creditAdjustmentModal')).hide();
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Credit balance updated successfully.',
                icon: 'success',
                confirmButtonColor: '#10b981'
            });
            if (typeof loadAgents === 'function') loadAgents();
        } else {
            Swal.fire('Failed', data.message || 'Failed to adjust credit balance.', 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Connection failed: ' + err.message, 'error');
    } finally {
        submitBtn.disabled = false;
    }
}
</script>

</body>
</html>
