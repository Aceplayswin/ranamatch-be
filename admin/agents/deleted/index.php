<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
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
    <title><?php echo $APP_NAME; ?>: Deleted Accounts Archive</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: #ef4444 !important; text-transform: uppercase; letter-spacing: 1.2px; display: inline-flex; align-items: center; gap: 4px; }

        /* Modern Glass Stats Cards */
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
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card-clean:hover {
            transform: translateY(-3px);
            border-color: rgba(239, 68, 68, 0.4);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.15);
        }
        .stat-card-clean h4 { font-size: 22px !important; font-weight: 800; margin: 4px 0 0; color: #ffffff !important; }
        .stat-card-clean span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #cbd5e1 !important; }
        .stat-icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }

        /* Filter Card */
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
        .cus-inp-clean:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }
        .cus-inp-clean::placeholder { color: #94a3b8 !important; font-weight: 500; }

        .btn-refresh-clean {
            background: rgba(255, 255, 255, 0.06);
            color: #e2e8f0 !important;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-refresh-clean:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* Scrollable Responsive Table */
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
        .r-table-wrapper::-webkit-scrollbar-thumb { background: rgba(239, 68, 68, 0.4); border-radius: 10px; }
        .r-table-wrapper::-webkit-scrollbar-thumb:hover { background: rgba(239, 68, 68, 0.7); }
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
        .r-table tr:hover td { background: rgba(239, 68, 68, 0.06); }

        .tag-danger-pill {
            background: rgba(239, 68, 68, 0.18); color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.4); padding: 4px 10px;
            border-radius: 8px; font-size: 11px; font-weight: 700;
            max-width: 200px; overflow: hidden; text-overflow: ellipsis; display: inline-block;
        }

        .btn-inspect {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: #ffffff !important; border: none; border-radius: 8px;
            padding: 6px 14px; font-size: 12px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-inspect:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.35);
            color: #fff !important;
        }

        .btn-restore-clean {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff !important; border: none; border-radius: 8px;
            padding: 6px 14px; font-size: 12px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-restore-clean:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            color: #fff !important;
        }

        .restore-option-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .restore-option-card:hover {
            background: rgba(30, 41, 59, 0.9);
            border-color: rgba(56, 189, 248, 0.4);
        }
        .restore-option-card input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #10b981;
            cursor: pointer;
        }

        /* Modal styling */
        .modal-content-glass {
            background: #0f172a !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 20px !important; color: #ffffff !important;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        .modal-header-glass {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 18px 24px;
        }
        .modal-footer-glass {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 16px 24px;
        }
        .nav-tabs-glass {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            gap: 8px; margin-bottom: 16px;
        }
        .nav-tabs-glass .nav-link {
            color: #94a3b8; border: none; border-bottom: 2px solid transparent;
            font-weight: 700; font-size: 13px; padding: 8px 16px; background: transparent;
        }
        .nav-tabs-glass .nav-link.active {
            color: #38bdf8 !important; border-bottom-color: #38bdf8 !important;
            background: rgba(56, 189, 248, 0.1) !important; border-radius: 8px 8px 0 0;
        }
    </style>
</head>
<body>

    <?php include "../../components/side-menu.php"; ?>

    <div class="admin-main-content" style="padding: 24px;">
        <div class="container-fluid">

            <!-- Dash Header -->
            <div class="dash-header">
                <div class="dash-title">
                    <div class="dash-breadcrumb"><i class='bx bx-user-x'></i> Audit & Permanent Archive</div>
                    <h1>Deleted Agent Accounts</h1>
                </div>
                <div>
                    <span style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class='bx bx-archive' style="font-size: 16px;"></i> Complete Lifetime Snapshot Preserved
                    </span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stat-grid-clean">
                <div class="stat-card-clean">
                    <div>
                        <span>Total Archived</span>
                        <h4 id="statTotalDeleted">0</h4>
                    </div>
                    <div class="stat-icon-wrapper" style="color: #ef4444; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3);">
                        <i class='bx bx-user-x'></i>
                    </div>
                </div>

                <div class="stat-card-clean">
                    <div>
                        <span>Turnover Commission</span>
                        <h4 id="statTurnoverComm" style="color: #38bdf8 !important;">₹ 0.00</h4>
                    </div>
                    <div class="stat-icon-wrapper" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3);">
                        <i class='bx bx-wallet'></i>
                    </div>
                </div>

                <div class="stat-card-clean">
                    <div>
                        <span>Lifetime Net PnL</span>
                        <h4 id="statNetPnl" style="color: #f59e0b !important;">₹ 0.00</h4>
                    </div>
                    <div class="stat-icon-wrapper" style="color: #f59e0b; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class='bx bx-trending-up'></i>
                    </div>
                </div>

                <div class="stat-card-clean">
                    <div>
                        <span>Total Net Earnings</span>
                        <h4 id="statTotalEarnings" style="color: #10b981 !important;">₹ 0.00</h4>
                    </div>
                    <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class='bx bx-dollar-circle'></i>
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-card-clean">
                <div class="position-relative flex-grow-1" style="max-width: 380px;">
                    <i class='bx bx-search position-absolute' style="left:12px; top:11px; color:#94a3b8; font-size:18px;"></i>
                    <input type="text" id="searchInput" class="cus-inp-clean w-100 ps-5" placeholder="Search by name, code, email or reason..." onkeyup="filterData()">
                </div>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <button class="btn-refresh-clean" onclick="loadDeletedAccounts()">
                        <i class='bx bx-refresh' style="font-size: 16px;"></i> Refresh List
                    </button>
                </div>
            </div>

            <!-- Data Table Wrapper -->
            <div class="r-table-wrapper">
                <table class="r-table">
                    <thead>

                        <tr>
                            <th>Agent Details</th>
                            <th>Contact / Rank</th>
                            <th>Reason for Deletion</th>
                            <th>Deleted By</th>
                            <th>Commission & Earnings</th>
                            <th>Assigned</th>
                            <th>Deleted Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="deletedTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-danger me-2" role="status"></div>
                                Loading deleted agent archives...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Snapshot Detail Modal -->
    <div class="modal fade" id="snapshotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-content-glass">
                <div class="modal-header modal-header-glass">
                    <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2" id="snapshotTitle">
                        <i class='bx bx-file-find text-cyan-400'></i> Archived Agent Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="snapshotModalBody">
                    <!-- Dynamic modal content loaded via JS -->
                </div>
                <div class="modal-footer modal-footer-glass">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:10px;">Close Audit View</button>
                    <button type="button" class="btn btn-success btn-sm ms-auto" id="modalRestoreBtn" style="border-radius:10px; font-weight:700;">
                        <i class='bx bx-refresh me-1'></i> Restore Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Options Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-glass">
                <div class="modal-header modal-header-glass">
                    <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2">
                        <i class='bx bx-refresh text-success fs-4'></i> Restore Agent Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="restoreArchiveId">
                    <div class="p-3 mb-3" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px;">
                        <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #34d399;">TARGET AGENT</div>
                        <div class="fw-bold text-white fs-6 mt-1" id="restoreAgentTargetText">Loading...</div>
                    </div>

                    <label style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #94a3b8; display: block; margin-bottom: 10px;">
                        SELECT RESTORATION TIMING
                    </label>

                    <div class="d-flex flex-column gap-2 mb-3">
                        <label class="restore-option-card">
                            <input type="radio" name="restoreTiming" value="immediate" checked onchange="toggleCustomDatetime(false)">
                            <div>
                                <div class="fw-bold text-white"><i class='bx bx-bolt text-warning me-1'></i> Restore Immediately</div>
                                <div style="font-size:11px; color:#94a3b8;">Re-activate agent account right now</div>
                            </div>
                        </label>

                        <label class="restore-option-card">
                            <input type="radio" name="restoreTiming" value="1_hour" onchange="toggleCustomDatetime(false)">
                            <div>
                                <div class="fw-bold text-white"><i class='bx bx-time text-info me-1'></i> Restore After 1 Hour</div>
                                <div style="font-size:11px; color:#94a3b8;">Automatically restore 1 hour from now</div>
                            </div>
                        </label>

                        <label class="restore-option-card">
                            <input type="radio" name="restoreTiming" value="6_hours" onchange="toggleCustomDatetime(false)">
                            <div>
                                <div class="fw-bold text-white"><i class='bx bx-time text-info me-1'></i> Restore After 6 Hours</div>
                                <div style="font-size:11px; color:#94a3b8;">Automatically restore 6 hours from now</div>
                            </div>
                        </label>

                        <label class="restore-option-card">
                            <input type="radio" name="restoreTiming" value="24_hours" onchange="toggleCustomDatetime(false)">
                            <div>
                                <div class="fw-bold text-white"><i class='bx bx-calendar text-primary me-1'></i> Restore After 24 Hours</div>
                                <div style="font-size:11px; color:#94a3b8;">Automatically restore 24 hours from now</div>
                            </div>
                        </label>

                        <label class="restore-option-card">
                            <input type="radio" name="restoreTiming" value="custom" onchange="toggleCustomDatetime(true)">
                            <div>
                                <div class="fw-bold text-white"><i class='bx bx-calendar-event text-success me-1'></i> Custom Date & Time Range</div>
                                <div style="font-size:11px; color:#94a3b8;">Choose a specific date and time for auto-restoration</div>
                            </div>
                        </label>
                    </div>

                    <div id="customDatetimeContainer" class="d-none mb-3">
                        <label style="font-size:11px; font-weight:800; color:#34d399; display:block;" class="mb-1">PICK DATETIME RANGE</label>
                        <input type="datetime-local" id="customDatetimeInput" class="cus-inp-clean w-100">
                    </div>
                </div>
                <div class="modal-footer modal-footer-glass">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="submitRestorationPlan()" style="border-radius:10px; font-weight:700;">
                        <i class='bx bx-check-circle me-1'></i> Confirm Restoration Plan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let rawData = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadDeletedAccounts();
        });

        function loadDeletedAccounts() {
            fetch('api_deleted.php?action=list')
            .then(res => res.json())
            .then(resData => {
                if (resData.status === 'success') {
                    rawData = resData.data || [];
                    updateStats(resData.summary);
                    renderTable(rawData);
                } else {
                    document.getElementById('deletedTableBody').innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-4 text-danger">
                                Failed to load archive records: ${resData.message || 'Unknown error'}
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(err => {
                document.getElementById('deletedTableBody').innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-danger">
                            Network error connecting to API: ${err.message}
                        </td>
                    </tr>
                `;
            });
        }

        function updateStats(summary) {
            if (!summary) return;
            document.getElementById('statTotalDeleted').innerText = summary.total_deleted_accounts || 0;
            document.getElementById('statTurnoverComm').innerText = '₹ ' + (summary.aggregate_turnover_commission || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('statNetPnl').innerText = '₹ ' + (summary.aggregate_net_pnl || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('statTotalEarnings').innerText = '₹ ' + (summary.aggregate_net_earnings || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
        }

        function renderTable(list) {
            const tbody = document.getElementById('deletedTableBody');
            if (!list || list.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5" style="color: #94a3b8;">
                            <i class='bx bx-folder-open' style="font-size: 48px; opacity: 0.5; display: block; margin: 0 auto 12px; color: #64748b;"></i>
                            <div style="font-weight: 600; font-size: 14px;">No deleted agent accounts found in the archive.</div>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            list.forEach(item => {
                const comm = item.total_turnover_commission ? '₹ ' + item.total_turnover_commission.toFixed(2) : '₹ 0.00';
                const pnl = item.total_net_pnl ? '₹ ' + item.total_net_pnl.toFixed(2) : '₹ 0.00';
                const earnings = item.total_net_earnings ? '₹ ' + item.total_net_earnings.toFixed(2) : '₹ 0.00';

                let statusBadge = '';
                if (item.status === 'restored') {
                    statusBadge = `
                        <div style="font-size:11px; color:#34d399; font-weight:700;">
                            <i class='bx bx-check-circle me-1'></i>Restored
                        </div>
                        <div style="font-size:10px; color:#94a3b8;">${escapeHtml(item.restored_at || '')}</div>
                    `;
                } else if (item.status === 'scheduled') {
                    statusBadge = `
                        <div style="font-size:11px; color:#fbbf24; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                            <i class='bx bx-time me-1'></i>Scheduled
                            <button onclick="cancelSchedule(${item.id})" class="btn btn-link text-danger p-0 border-0 ms-1" title="Cancel Schedule">
                                <i class='bx bx-x-circle' style="font-size:16px;"></i>
                            </button>
                        </div>
                        <div style="font-size:10px; color:#cbd5e1;">${escapeHtml(item.scheduled_restore_at || '')}</div>
                    `;
                } else {
                    statusBadge = `
                        <div style="font-size:11px; color:#fca5a5; font-weight:700;">
                            <i class='bx bx-archive me-1'></i>Archived
                        </div>
                    `;
                }

                let actionButtons = '';
                if (item.status === 'restored') {
                    actionButtons = `
                        <button class="btn-inspect" onclick="inspectSnapshot(${item.id})">
                            <i class='bx bx-search-alt'></i> Audit Details
                        </button>
                        <span class="badge bg-success bg-opacity-20 text-success ms-1" style="font-size:11px; padding:6px 10px;">
                            <i class='bx bx-check me-1'></i>Active
                        </span>
                    `;
                } else {
                    actionButtons = `
                        <button class="btn-inspect" onclick="inspectSnapshot(${item.id})">
                            <i class='bx bx-search-alt'></i> Full Details
                        </button>
                        <button class="btn-restore-clean ms-1" onclick="openRestoreModal(${item.id})">
                            <i class='bx bx-refresh'></i> Restore
                        </button>
                    `;
                }

                html += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:36px; height:36px; border-radius:10px; background:${item.status === 'restored' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'}; color:${item.status === 'restored' ? '#34d399' : '#ef4444'}; display:flex; align-items:center; justify-content:center; font-weight:800;">
                                    <i class='bx ${item.status === 'restored' ? 'bx-check-circle' : 'bx-user-x'}'></i>
                                </div>
                                <div>
                                    <div style="font-weight:700; color:#ffffff;">${escapeHtml(item.name)}</div>
                                    <div style="font-size:11px; color:#38bdf8;">${escapeHtml(item.agent_code)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px; color:#cbd5e1;">${escapeHtml(item.email || 'N/A')}</div>
                            <span class="badge bg-secondary" style="font-size:10px;">Level: ${escapeHtml(item.rank_level || '1')}</span>
                        </td>
                        <td>
                            <span class="tag-danger-pill" title="${escapeHtml(item.deletion_reason)}">
                                <i class='bx bx-info-circle me-1'></i>${escapeHtml(item.deletion_reason || 'No reason specified')}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:12px; font-weight:700; color:#f8fafc;">${escapeHtml(item.deleted_by_admin || 'System Admin')}</div>
                            ${statusBadge}
                        </td>
                        <td>
                            <div style="font-size:12px;"><span style="color:#94a3b8;">Comm:</span> <strong style="color:#38bdf8;">${comm}</strong></div>
                            <div style="font-size:12px;"><span style="color:#94a3b8;">Net Earnings:</span> <strong style="color:#34d399;">${earnings}</strong></div>
                        </td>
                        <td>
                            <div style="font-size:11px; color:#cbd5e1;"><i class='bx bx-user me-1'></i>Players: <strong>${item.downline_players_count}</strong></div>
                            <div style="font-size:11px; color:#cbd5e1;"><i class='bx bx-sitemap me-1'></i>Agents: <strong>${item.downline_agents_count}</strong></div>
                        </td>
                        <td>
                            <div style="font-size:12px; color:#cbd5e1;">${escapeHtml(item.deleted_at)}</div>
                        </td>
                        <td>
                            ${actionButtons}
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function filterData() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            if (!query) {
                renderTable(rawData);
                return;
            }
            const filtered = rawData.filter(item => {
                return (item.name && item.name.toLowerCase().includes(query)) ||
                       (item.agent_code && item.agent_code.toLowerCase().includes(query)) ||
                       (item.email && item.email.toLowerCase().includes(query)) ||
                       (item.deletion_reason && item.deletion_reason.toLowerCase().includes(query)) ||
                       (item.username && item.username.toLowerCase().includes(query));
            });
            renderTable(filtered);
        }

        function inspectSnapshot(archiveId) {
            fetch(`api_deleted.php?action=detail&id=${archiveId}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    renderSnapshotModal(res.data);
                } else {
                    Swal.fire('Error', res.message || 'Unable to fetch snapshot details.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error: ' + err.message, 'error');
            });
        }

        function renderSnapshotModal(arch) {
            document.getElementById('snapshotTitle').innerHTML = `
                <i class='bx bx-archive text-danger'></i> Archived Agent: ${escapeHtml(arch.name)} (${escapeHtml(arch.agent_code)})
            `;

            const snap = arch.snapshot_metadata || {};
            const profile = snap.agent || {};
            const fin = snap.financials || {};
            const players = snap.players || [];
            const downlines = snap.sub_agents || [];

            let playersRows = '';
            if (players.length === 0) {
                playersRows = `<tr><td colspan="5" class="text-center text-muted py-3">No players directly assigned to this agent at time of deletion.</td></tr>`;
            } else {
                players.forEach(p => {
                    playersRows += `
                        <tr>
                            <td><strong>${escapeHtml(p.tbl_user_name || p.username || 'N/A')}</strong> (ID: ${p.id || p.tbl_uniq_id || '?'})</td>
                            <td>${escapeHtml(p.tbl_full_name || p.name || 'N/A')}</td>
                            <td>${escapeHtml(p.tbl_mobile_num || p.mobile || 'N/A')}</td>
                            <td>₹ ${(parseFloat(p.tbl_balance || p.current_balance || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                            <td><span class="badge ${p.tbl_account_status === 'Y' ? 'bg-success' : 'bg-secondary'}">${p.tbl_account_status === 'Y' ? 'Active' : 'Inactive'}</span></td>
                        </tr>
                    `;
                });
            }

            let downlineRows = '';
            if (downlines.length === 0) {
                downlineRows = `<tr><td colspan="4" class="text-center text-muted py-3">No downline sub-agents under this agent.</td></tr>`;
            } else {
                downlines.forEach(d => {
                    downlineRows += `
                        <tr>
                            <td><strong>${escapeHtml(d.name || d.username || 'N/A')}</strong> (${escapeHtml(d.agent_code || d.code || 'N/A')})</td>
                            <td>Level: ${d.rank_level || d.level || '?'}</td>
                            <td><span class="badge ${d.status === 'active' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(d.status || 'unknown')}</span></td>
                            <td>₹ ${(parseFloat(d.current_credit || d.credit_balance || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }

            const bodyHtml = `
                <ul class="nav nav-tabs nav-tabs-glass" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOverview">Overview & Reason</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFinancials">Financial Breakdown</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPlayers">Players (${players.length})</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDownlines">Downlines (${downlines.length})</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRaw">Raw Technical Metadata</button>
                    </li>
                </ul>

                <div class="tab-content pt-2">
                    <!-- Tab 1: Overview & Reason -->
                    <div class="tab-pane fade show active" id="tabOverview">
                        <div class="p-3 mb-3" style="background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239, 68, 68, 0.3); border-radius:12px;">
                            <label style="font-size:11px; font-weight:800; text-transform:uppercase; color:#fca5a5;">Reason for Account Deletion</label>
                            <p class="mb-0 fs-6 text-white font-weight-bold" style="white-space:pre-wrap;">${escapeHtml(arch.deletion_reason || 'N/A')}</p>
                            <div class="mt-2" style="font-size:11px; color:#cbd5e1;">
                                Executed by Admin: <strong>${escapeHtml(arch.deleted_by_admin || 'System Admin')}</strong> on <strong>${escapeHtml(arch.deleted_at)}</strong>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3" style="background:rgba(30, 41, 59, 0.5); border-radius:12px; border:1px solid rgba(255,255,255,0.08);">
                                    <h6 style="color:#38bdf8; font-weight:700;">Identity & Contact Info</h6>
                                    <div style="font-size:13px; line-height:1.8;">
                                        <div><strong>Full Name:</strong> ${escapeHtml(arch.name)}</div>
                                        <div><strong>Agent Code:</strong> ${escapeHtml(arch.agent_code)}</div>
                                        <div><strong>Username:</strong> ${escapeHtml(arch.username)}</div>
                                        <div><strong>Email:</strong> ${escapeHtml(arch.email || 'N/A')}</div>
                                        <div><strong>Rank Level:</strong> Level ${escapeHtml(arch.rank_level || '1')}</div>
                                        <div><strong>Joined Platform:</strong> ${escapeHtml(arch.created_at || 'N/A')}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3" style="background:rgba(30, 41, 59, 0.5); border-radius:12px; border:1px solid rgba(255,255,255,0.08);">
                                    <h6 style="color:#34d399; font-weight:700;">Final Financial Standing</h6>
                                    <div style="font-size:13px; line-height:1.8;">
                                        <div><strong>Credit Balance at Deletion:</strong> ₹ ${(parseFloat(arch.current_credit || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                        <div><strong>Exposed Risk Credit:</strong> ₹ ${(parseFloat(arch.exposed_credit || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                        <div><strong>Lifetime Turnover Commission:</strong> ₹ ${(parseFloat(arch.total_turnover_commission || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                        <div><strong>Lifetime Net PnL:</strong> ₹ ${(parseFloat(arch.total_net_pnl || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                        <div><strong>Total Net Earnings:</strong> ₹ ${(parseFloat(arch.total_net_earnings || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Financials -->
                    <div class="tab-pane fade" id="tabFinancials">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-bordered text-sm">
                                <thead>
                                    <tr>
                                        <th>Financial Metric</th>
                                        <th>Amount Recorded</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Final Credit Balance</td>
                                        <td class="text-info font-weight-bold">₹ ${(parseFloat(fin.current_credit || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                        <td>Remaining agent credit balance before deletion.</td>
                                    </tr>
                                    <tr>
                                        <td>Exposed Risk Credit</td>
                                        <td class="text-warning font-weight-bold">₹ ${(parseFloat(fin.exposed_credit || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                        <td>Risk exposure tied to pending bets or downline allocations.</td>
                                    </tr>
                                    <tr>
                                        <td>Lifetime Turnover Commission</td>
                                        <td class="text-cyan font-weight-bold">₹ ${(parseFloat(fin.total_turnover_commission || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                        <td>Cumulative commission earned from player betting volumes.</td>
                                    </tr>
                                    <tr>
                                        <td>Lifetime Net P&L Settlement</td>
                                        <td class="text-warning font-weight-bold">₹ ${(parseFloat(fin.total_net_pnl || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                        <td>Cumulative profit/loss settlement shared by the agent.</td>
                                    </tr>
                                    <tr style="background:rgba(52, 211, 153, 0.15);">
                                        <td><strong>TOTAL NET EARNINGS</strong></td>
                                        <td class="text-success font-weight-bold fs-6">₹ ${(parseFloat(fin.total_net_earnings || 0)).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                        <td>Sum of Turnover Commission and Net P&L.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 3: Players -->
                    <div class="tab-pane fade" id="tabPlayers">
                        <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                            <table class="table table-dark table-hover table-sm" style="font-size:12px;">
                                <thead>
                                    <tr>
                                        <th>Player Username & ID</th>
                                        <th>Full Name</th>
                                        <th>Mobile</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${playersRows}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 4: Downlines -->
                    <div class="tab-pane fade" id="tabDownlines">
                        <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                            <table class="table table-dark table-hover table-sm" style="font-size:12px;">
                                <thead>
                                    <tr>
                                        <th>Agent Name & Code</th>
                                        <th>Hierarchy Level</th>
                                        <th>Status</th>
                                        <th>Credit Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${downlineRows}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 5: Raw Technical Metadata -->
                    <div class="tab-pane fade" id="tabRaw">
                        <pre style="background:#020617; color:#38bdf8; padding:16px; border-radius:12px; font-size:11px; max-height:400px; overflow:auto;">${escapeHtml(JSON.stringify(snap, null, 2))}</pre>
                    </div>
                </div>
            `;

            document.getElementById('snapshotModalBody').innerHTML = bodyHtml;

            const restoreBtn = document.getElementById('modalRestoreBtn');
            if (arch.status === 'restored') {
                restoreBtn.className = 'd-none';
            } else {
                restoreBtn.className = 'btn btn-success btn-sm ms-auto';
                restoreBtn.onclick = () => {
                    const snapModal = bootstrap.Modal.getInstance(document.getElementById('snapshotModal'));
                    if (snapModal) snapModal.hide();
                    openRestoreModal(arch.id);
                };
            }

            const modal = new bootstrap.Modal(document.getElementById('snapshotModal'));
            modal.show();
        }

        function openRestoreModal(archiveId) {
            const item = rawData.find(x => x.id === archiveId);
            if (!item) return;

            document.getElementById('restoreArchiveId').value = archiveId;
            document.getElementById('restoreAgentTargetText').innerText = `${item.name} (${item.agent_code}) - ${item.email || 'No Email'}`;

            // Reset options
            document.querySelectorAll('input[name="restoreTiming"]').forEach(r => r.checked = false);
            document.querySelector('input[name="restoreTiming"][value="immediate"]').checked = true;
            toggleCustomDatetime(false);

            // Pre-fill datetime-local input with current time + 1 hour formatted
            const now = new Date();
            now.setHours(now.getHours() + 1);
            const pad = n => String(n).padStart(2, '0');
            const localIso = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
            document.getElementById('customDatetimeInput').value = localIso;

            const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
            modal.show();
        }

        function toggleCustomDatetime(show) {
            const container = document.getElementById('customDatetimeContainer');
            if (show) {
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        }

        function submitRestorationPlan() {
            const archiveId = parseInt(document.getElementById('restoreArchiveId').value);
            const selectedTiming = document.querySelector('input[name="restoreTiming"]:checked')?.value || 'immediate';
            const customDatetime = document.getElementById('customDatetimeInput').value;

            if (selectedTiming === 'custom' && !customDatetime) {
                Swal.fire('Input Error', 'Please select a valid custom date & time range.', 'warning');
                return;
            }

            const restoreModalEl = document.getElementById('restoreModal');
            const modalInst = bootstrap.Modal.getInstance(restoreModalEl);

            Swal.fire({
                title: 'Confirm Restoration',
                text: selectedTiming === 'immediate' ? 
                      'Are you sure you want to restore this agent account immediately?' : 
                      `Schedule restoration under '${selectedTiming}' mode?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Proceed',
                cancelButtonText: 'Cancel'
            }).then((res) => {
                if (!res.isConfirmed) return;

                if (modalInst) modalInst.hide();

                fetch('api_deleted.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'schedule_restore',
                        archive_id: archiveId,
                        timing_type: selectedTiming,
                        custom_datetime: customDatetime
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        loadDeletedAccounts();
                    } else {
                        Swal.fire('Restoration Error', res.message || 'Operation failed', 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
            });
        }

        function cancelSchedule(archiveId) {
            Swal.fire({
                title: 'Cancel Scheduled Restoration?',
                text: 'Are you sure you want to cancel the scheduled restoration for this account?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel Schedule',
                cancelButtonText: 'No'
            }).then((res) => {
                if (!res.isConfirmed) return;

                fetch('api_deleted.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'cancel_schedule',
                        archive_id: archiveId
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire('Canceled', res.message, 'success');
                        loadDeletedAccounts();
                    } else {
                        Swal.fire('Error', res.message || 'Failed to cancel schedule.', 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>
