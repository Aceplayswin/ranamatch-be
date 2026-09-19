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
    <title><?php echo $APP_NAME; ?>: Agent Detail & Hierarchy Tree Console</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../../components/theme-variables.php"; ?>

        body {
            font-family: var(--font-body) !important;
            background-color: #030712 !important;
            min-height: 100vh;
            color: #f8fafc !important;
            margin: 0; padding: 0; overflow-x: hidden;
        }

        .agent-header-bar {
            padding: 16px 24px;
            background: #090d16;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 14px;
        }

        .nav-tabs-custom {
            display: flex; align-items: center; gap: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 24px; margin-top: 16px; margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent; border: none;
            color: #94a3b8; font-weight: 700; font-size: 13px;
            padding: 12px 18px; border-bottom: 2px solid transparent;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease; white-space: nowrap;
        }
        .tab-btn:hover { color: #f8fafc; }
        .tab-btn.active {
            color: #10b981 !important;
            border-bottom-color: #10b981 !important;
        }

        /* Glass Card Container */
        .detail-card-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px; padding: 20px;
            backdrop-filter: blur(12px);
        }

        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 13px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #94a3b8; font-weight: 600; }
        .info-val { color: #ffffff; font-weight: 700; font-family: inherit; }

        /* Metric Box Grid */
        .metric-card {
            background: #090e1a;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px; padding: 16px 20px;
        }
        .metric-card.highlight {
            border-color: rgba(245, 158, 11, 0.4);
        }
        .metric-title { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: none; }
        .metric-val { font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 4px; }

        /* Form Inputs */
        .dark-field-label {
            font-size: 10px !important; font-weight: 800 !important;
            text-transform: uppercase !important; letter-spacing: 1px !important;
            color: #94a3b8 !important; margin-bottom: 6px !important; display: block;
        }
        .dark-input, .dark-select {
            width: 100% !important; background-color: #020617 !important;
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            border-radius: 10px !important; padding: 10px 14px !important;
            color: #ffffff !important; font-size: 13px !important; font-weight: 600 !important;
            outline: none !important; transition: border-color 0.2s ease;
        }
        .dark-select option { background-color: #0f172a !important; color: #ffffff !important; }

        .btn-save-purple {
            background: #6366f1 !important; color: #ffffff !important;
            font-weight: 800 !important; font-size: 13px !important;
            padding: 10px 24px !important; border-radius: 10px !important;
            border: none !important; cursor: pointer;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3) !important;
        }
        .btn-save-purple:hover { background: #4f46e5 !important; }

        .tag {
            padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center;
        }
        .tag-success { background: rgba(16, 185, 129, 0.18); color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.4); }
        .tag-purple { background: rgba(168, 85, 247, 0.18); color: #c084fc !important; border: 1px solid rgba(168, 85, 247, 0.4); }
        .tag-blue { background: rgba(56, 189, 248, 0.18); color: #38bdf8 !important; border: 1px solid rgba(56, 189, 248, 0.4); }

        /* Empty State Card */
        .empty-state-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px dashed rgba(255, 255, 255, 0.12);
            border-radius: 20px; padding: 60px 20px; text-align: center;
        }
        .empty-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 26px; color: #64748b; margin-bottom: 14px;
        }

        /* Activity Table */
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(15, 23, 42, 0.85); padding: 14px 18px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #94a3b8 !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .r-table td {
            padding: 12px 18px; font-size: 13px; font-weight: 600;
            color: #ffffff !important; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .r-table tr:hover td { background: rgba(255, 255, 255, 0.03); }

        /* Visual Tree Model Styling */
        .tree-model-wrapper {
            display: flex; flex-direction: column; align-items: center;
            padding: 20px 0; overflow-x: auto;
        }
        .tree-card-node {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 14px; padding: 14px 18px; min-width: 260px; max-width: 320px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            position: relative; z-index: 2; transition: all 0.2s ease;
        }
        .tree-card-node:hover {
            border-color: #38bdf8;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(56, 189, 248, 0.2);
        }
        .tree-card-node.root-card {
            border-color: #10b981;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25);
        }
        .tree-children-row {
            display: flex; gap: 24px; margin-top: 32px; position: relative;
            justify-content: center;
        }
        .tree-children-row::before {
            content: ''; position: absolute; top: -16px; left: 10%; right: 10%;
            height: 2px; background: rgba(56, 189, 248, 0.3); z-index: 1;
        }
        .tree-branch-col {
            display: flex; flex-direction: column; align-items: center; position: relative;
        }
        .tree-branch-col::before {
            content: ''; position: absolute; top: -16px; width: 2px; height: 16px;
            background: rgba(56, 189, 248, 0.3); z-index: 1;
        }
        .tree-stem-down {
            width: 2px; height: 16px; background: rgba(56, 189, 248, 0.3); margin: 0 auto;
        }

        /* Premium Modern Credit Modal Styling */
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
<body style="background-color: #030712 !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar p-0">
        
        <!-- Header Bar -->
        <div class="agent-header-bar">
            <div class="d-flex align-items-center gap-3">
                <a href="../" onclick="if(document.referrer && document.referrer !== location.href){ history.back(); return false; }" class="text-slate-400 hover:text-white text-xl" style="text-decoration: none;" title="Go Back">
                    <i class='bx bx-left-arrow-alt fs-3'></i>
                </a>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h2 class="mb-0 fw-bold text-white fs-4" id="agentHeaderTitle">Loading Agent...</h2>
                        <span class="tag tag-success" id="agentHeaderStatus">Active</span>
                        <span class="tag tag-purple" id="agentHeaderRank">Agent</span>
                    </div>
                    <div class="text-slate-400 text-xs mt-1 font-semibold" id="agentHeaderSub">
                        username &bull; email &bull; root account
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button onclick="openCreditModal()" class="btn btn-sm font-bold py-2 px-3 me-1" style="border-radius: 10px; font-size: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                    <i class='bx bx-wallet me-1'></i> Credit Adjustment
                </button>
                <button onclick="changeDetailAgentPassword()" class="btn btn-sm btn-outline-light font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.15);">
                    <i class='bx bx-key me-1'></i> Reset password
                </button>
                <button onclick="toggleDetailAgentStatus()" class="btn btn-sm btn-outline-light font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.15);" id="suspendBtn">
                    Suspend
                </button>
                <button onclick="deleteDetailAgent()" class="btn btn-sm btn-danger font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: #ef4444; border: none;">
                    Delete
                </button>
            </div>
        </div>

        <!-- Tab Navigation Bar -->
        <div class="nav-tabs-custom">
            <button class="tab-btn active" onclick="switchTab('profile', this)">
                <i class='bx bx-user'></i> Profile
            </button>
            <button class="tab-btn" onclick="switchTab('terms', this)">
                <i class='bx bx-slider-alt'></i> Terms & Position
            </button>
            <button class="tab-btn" onclick="switchTab('downline', this)">
                <i class='bx bx-git-repo-forked'></i> Downline & Hierarchy Tree
            </button>
            <button class="tab-btn" onclick="switchTab('players', this)">
                <i class='bx bx-group'></i> Players
            </button>
            <button class="tab-btn" onclick="switchTab('transfers', this)">
                <i class='bx bx-transfer'></i> Transfers
            </button>
            <button class="tab-btn" onclick="switchTab('settlements', this)">
                <i class='bx bx-scale'></i> Settlements
            </button>
            <button class="tab-btn" onclick="switchTab('activity', this)">
                <i class='bx bx-pulse'></i> Activity Log
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="px-4 pb-5">

            <!-- TAB 1: Profile -->
            <div id="tab-profile" class="tab-content-item">
                <div class="row g-4">
                    <!-- Left Col -->
                    <div class="col-lg-5">
                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Account</h6>
                            <div class="info-row"><span class="info-label">Username:</span><span class="info-val" id="profUsername">--</span></div>
                            <div class="info-row"><span class="info-label">Code:</span><span class="info-val text-cyan-code" id="profCode">--</span></div>
                            <div class="info-row"><span class="info-label">Email:</span><span class="info-val" id="profEmail">--</span></div>
                            <div class="info-row"><span class="info-label">Phone:</span><span class="info-val" id="profPhone">--</span></div>
                            <div class="info-row"><span class="info-label">Tree depth:</span><span class="info-val" id="profDepth">Level 1</span></div>
                            <div class="info-row"><span class="info-label">Joined:</span><span class="info-val" id="profJoined">--</span></div>
                            <div class="info-row"><span class="info-label">Last sign-in:</span><span class="info-val text-slate-500">—</span></div>
                        </div>

                        <div class="detail-card-box">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Restrictions</h6>
                            <div class="info-row"><span class="info-label">Betting</span><span class="info-val text-emerald-400">Open</span></div>
                            <div class="info-row"><span class="info-label">Downline logins</span><span class="info-val text-emerald-400">Open</span></div>
                            <div class="info-row"><span class="info-label">Password change pending</span><span class="info-val">No</span></div>
                        </div>
                    </div>

                    <!-- Right Col -->
                    <div class="col-lg-7">
                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Credit & exposure</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Balance</div>
                                        <div class="metric-val" id="profBalance">₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Free of open bets</div>
                                        <div class="metric-val" id="profFreeOpen">₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Own exposure</div>
                                        <div class="metric-val" id="profOwnExposure">₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card highlight">
                                        <div class="metric-title" style="color: #fbbf24;">Net exposure</div>
                                        <div class="metric-val" style="color: #fbbf24;" id="profNetExposure">₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Settled P&L</div>
                                        <div class="metric-val" id="profSettledPnl">₹0</div>
                                        <div class="text-slate-400 text-xs mt-1" id="profPnlBreakdown" style="font-size: 11px;">Direct: ₹0 | Downline: ₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Unsettled P&L</div>
                                        <div class="metric-val" id="profUnsettledPnl">₹0</div>
                                        <div class="text-slate-400 text-xs mt-1" style="font-size: 11px;">Open bet liabilities</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card" style="border-color: rgba(16, 185, 129, 0.3);">
                                        <div class="metric-title" style="color: #34d399;">Turnover Commission</div>
                                        <div class="metric-val text-emerald-400" id="profTurnoverComm">₹0</div>
                                        <div class="text-slate-400 text-xs mt-1" id="profCommBreakdown" style="font-size: 11px;">Direct: ₹0 | Downline: +₹0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card" style="border-color: rgba(56, 189, 248, 0.3);">
                                        <div class="metric-title" style="color: #38bdf8;">Net Real Revenue</div>
                                        <div class="metric-val" id="profRealRevenue">₹0</div>
                                        <div class="text-slate-400 text-xs mt-1" style="font-size: 11px;">Turnover Comm + Settled P&L</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-card-box">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Network</h6>
                            <div class="row g-3">
                                <div class="col-4">
                                    <div class="metric-card">
                                        <div class="metric-title">Direct</div>
                                        <div class="metric-val" id="netDirect">0</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="metric-card">
                                        <div class="metric-title">Whole subtree</div>
                                        <div class="metric-val" id="netSubtree">0</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="metric-card">
                                        <div class="metric-title">Players</div>
                                        <div class="metric-val" id="netPlayers">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Terms & Position -->
            <div id="tab-terms" class="tab-content-item d-none">
                <div class="detail-card-box max-w-4xl mx-auto" style="max-width: 750px;">
                    <form onsubmit="saveAgentTerms(event)">
                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Commercial terms</h6>
                            <p class="text-slate-400 text-xs mb-3">Partnership is this agent's share of the P&L their downline generates. Changes apply to future settlement; what has already been settled is not recalculated.</p>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">PARTNERSHIP (%)</label>
                                    <input type="number" id="termsPartnership" class="dark-input" value="25" min="0" max="100">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">COMMISSION (%)</label>
                                    <input type="number" id="termsCommission" class="dark-input" value="2" min="0" max="100">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Position in the tree</h6>
                            <p class="text-slate-400 text-xs mb-3">Moving an account moves everything beneath it. A move that would put this agent below one of its own downlines is refused.</p>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">UPLINE</label>
                                    <select id="termsUpline" class="dark-select">
                                        <option value="none">None — root of the tree</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">LEVEL</label>
                                    <select id="termsLevel" class="dark-select">
                                        <option value="agent">Agent</option>
                                        <option value="master_agent">Master Agent</option>
                                        <option value="super_agent">Super Agent</option>
                                        <option value="senior_super_agent">Senior Super Agent</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Contact</h6>
                            <div class="mb-3">
                                <label class="dark-field-label">DISPLAY NAME</label>
                                <input type="text" id="termsName" class="dark-input" placeholder="Display name">
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">EMAIL</label>
                                    <input type="email" id="termsEmail" class="dark-input" placeholder="Email">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">PHONE</label>
                                    <input type="text" id="termsPhone" class="dark-input" placeholder="Phone">
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-save-purple">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: Downline & Hierarchy Tree -->
            <div id="tab-downline" class="tab-content-item d-none">
                <!-- View Switcher -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="btn-group p-1 bg-slate-900 border border-slate-800 rounded-xl" style="border-radius: 12px; background: #090e1a;">
                        <button type="button" class="btn btn-sm text-white font-bold px-3 py-1.5 active" id="btnViewTree" onclick="toggleDownlineView('tree')" style="border-radius: 8px; font-size: 12px; background: #3b82f6;">
                            <i class='bx bx-git-repo-forked me-1'></i> Tree Model View
                        </button>
                        <button type="button" class="btn btn-sm text-slate-400 font-bold px-3 py-1.5" id="btnViewTable" onclick="toggleDownlineView('table')" style="border-radius: 8px; font-size: 12px; background: transparent;">
                            <i class='bx bx-table me-1'></i> Data Table View
                        </button>
                    </div>
                    <span class="badge bg-secondary font-mono" id="downlineCountBadge">0 Downlines</span>
                </div>

                <!-- 1. Tree Model Container -->
                <div id="downlineTreeModelBox" class="detail-card-box overflow-auto">
                    <div id="treeModelRenderArea" class="tree-model-wrapper">
                        <!-- Populated dynamically by renderVisualTree() -->
                    </div>
                </div>

                <!-- 2. Table Box Container -->
                <div id="downlineTableBox" class="d-none">
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr><th>Agent</th><th>Rank</th><th>Credit</th><th>Partnership</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="downlineTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div class="empty-state-box d-none" id="downlineEmptyBox">
                    <div class="empty-icon"><i class='bx bx-sitemap'></i></div>
                    <h6 class="fw-bold text-white mb-1">No downline accounts</h6>
                    <p class="text-slate-400 text-xs mb-0">Accounts this agent opens itself appear here.</p>
                </div>
            </div>

            <!-- TAB 4: Players -->
            <div id="tab-players" class="tab-content-item d-none">
                <div class="empty-state-box" id="playersEmptyBox">
                    <div class="empty-icon"><i class='bx bx-user'></i></div>
                    <h6 class="fw-bold text-white mb-1">No players on this account</h6>
                    <p class="text-slate-400 text-xs mb-0">Players sitting further down the tree belong to the account that opened them.</p>
                </div>
                <div id="playersTableBox" class="d-none">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- TAB 5: Transfers -->
            <div id="tab-transfers" class="tab-content-item d-none">
                <div class="empty-state-box" id="transfersEmptyBox">
                    <div class="empty-icon"><i class='bx bx-transfer'></i></div>
                    <h6 class="fw-bold text-white mb-1">No credit movements yet</h6>
                </div>
                <div id="transfersTableBox" class="d-none">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- TAB 6: Settlements -->
            <div id="tab-settlements" class="tab-content-item d-none">
                <div class="empty-state-box" id="settlementsEmptyBox">
                    <div class="empty-icon"><i class='bx bx-scale'></i></div>
                    <h6 class="fw-bold text-white mb-1">No settlement history yet</h6>
                    <p class="text-slate-400 text-xs mb-0">Commission settlements and settled P&L records will appear here.</p>
                </div>
                <div id="settlementsTableBox" class="d-none">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- TAB 7: Activity Log -->
            <div id="tab-activity" class="tab-content-item d-none">
                <div class="detail-card-box p-0 overflow-auto">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sl. No.</th>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="activityTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                    <div class="p-3 text-slate-400 text-xs fw-semibold border-top border-slate-800" id="activityFooter">
                        Showing all 0 entries
                    </div>
                </div>
            </div>

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
                        <label class="dark-field-label">TRANSACTION TYPE</label>
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
                        <label class="dark-field-label">QUICK AMOUNTS (₹)</label>
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
                        <label class="dark-field-label">AMOUNT (₹)</label>
                        <div class="position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-slate-400 fw-bold fs-6">₹</span>
                            <input type="number" step="any" min="0.01" id="creditAmountInput" class="dark-input ps-5 fs-6 fw-bold text-emerald-400" placeholder="0.00" oninput="updateCreditPreview()" required>
                        </div>
                    </div>

                    <!-- Remark / Reason -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="dark-field-label mb-0">REMARK / TRANSACTION REASON</label>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            <span class="reason-chip" onclick="selectRemarkChip('Opening Float Injection')">Opening Float</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Weekly P&L Settlement')">Weekly Settlement</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Bonus Credit Top-up')">Bonus Top-up</span>
                            <span class="reason-chip" onclick="selectRemarkChip('Float Clawback Correction')">Clawback Correction</span>
                        </div>
                        <textarea id="creditRemarkInput" class="dark-input" rows="2" placeholder="e.g. Platform float adjustment for Q3 launch"></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const urlParams = new URLSearchParams(window.location.search);
const agentId = urlParams.get('id') || urlParams.get('code') || urlParams.get('agent_code');
let agentData = null;

function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content-item').forEach(c => c.classList.add('d-none'));

    if (btn) btn.classList.add('active');
    const target = document.getElementById('tab-' + tabId);
    if (target) target.classList.remove('d-none');
}

function toggleDownlineView(viewMode) {
    const btnTree = document.getElementById('btnViewTree');
    const btnTable = document.getElementById('btnViewTable');
    const boxTree = document.getElementById('downlineTreeModelBox');
    const boxTable = document.getElementById('downlineTableBox');

    if (viewMode === 'tree') {
        btnTree.style.background = '#3b82f6'; btnTree.style.color = '#fff';
        btnTable.style.background = 'transparent'; btnTable.style.color = '#94a3b8';
        boxTree.classList.remove('d-none');
        boxTable.classList.add('d-none');
    } else {
        btnTable.style.background = '#3b82f6'; btnTable.style.color = '#fff';
        btnTree.style.background = 'transparent'; btnTree.style.color = '#94a3b8';
        boxTable.classList.remove('d-none');
        boxTree.classList.add('d-none');
    }
}

function renderTreeNodeHTML(node, isRoot = false) {
    const rankTag = (node.rank_level || 'agent').replace(/_/g, ' ').toUpperCase();
    const children = node.children || [];
    const hasChildren = children.length > 0;

    let html = `
        <div class="tree-branch-col">
            <div class="tree-card-node ${isRoot ? 'root-card' : ''}">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class='bx ${isRoot ? 'bx-user-pin text-emerald-400 fs-3' : 'bx-user-check text-cyan-400 fs-4'}'></i>
                        <div>
                            <div class="fw-bold text-white fs-6">${node.name || node.username}</div>
                            <div class="text-slate-400 font-mono" style="font-size:11px;">@${node.username} (${node.agent_code || ('AGT-' + node.id)})</div>
                        </div>
                    </div>
                    <span class="tag ${isRoot ? 'tag-success' : 'tag-purple'}">${rankTag}</span>
                </div>
                <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between text-xs">
                    <span class="text-emerald-400 fw-bold">₹ ${(node.current_credit || 0).toLocaleString('en-IN')}</span>
                    <span class="text-cyan-code fw-bold">${node.partnership_pct || 0}% Share</span>
                </div>
            </div>
    `;

    if (hasChildren) {
        html += `<div class="tree-stem-down"></div>`;
        html += `<div class="tree-children-row">`;
        children.forEach(child => {
            html += renderTreeNodeHTML(child, false);
        });
        html += `</div>`;
    }

    html += `</div>`;
    return html;
}

async function loadAgentConsole() {
    const fetchUrl = agentId ? `api_detail.php?id=${encodeURIComponent(agentId)}&code=${encodeURIComponent(agentId)}` : 'api_detail.php';

    try {
        const res = await fetch(fetchUrl);
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            agentData = data.data;
            const iden = agentData.identity;
            const cred = agentData.credit_exposure;
            const net = agentData.network;

            if (!agentId && iden.id) {
                history.replaceState(null, '', `?id=${iden.id}`);
            }

            // Header bar
            document.getElementById('agentHeaderTitle').innerText = iden.name;
            const statusLower = (iden.status || 'active').toLowerCase();
            const statusElem = document.getElementById('agentHeaderStatus');
            const suspendBtn = document.getElementById('suspendBtn');

            if (statusLower === 'suspended' || statusLower === 'locked') {
                statusElem.innerText = 'SUSPENDED';
                statusElem.className = 'tag tag-danger';
                if (suspendBtn) {
                    suspendBtn.innerText = 'Activate';
                    suspendBtn.className = 'btn btn-sm btn-outline-success font-bold py-2 px-3';
                    suspendBtn.style.color = '#34d399';
                    suspendBtn.style.borderColor = 'rgba(52, 211, 153, 0.4)';
                    suspendBtn.style.background = 'rgba(16, 185, 129, 0.15)';
                }
            } else {
                statusElem.innerText = 'Active';
                statusElem.className = 'tag tag-success';
                if (suspendBtn) {
                    suspendBtn.innerText = 'Suspend';
                    suspendBtn.className = 'btn btn-sm btn-outline-light font-bold py-2 px-3';
                    suspendBtn.style.color = '#ffffff';
                    suspendBtn.style.borderColor = 'rgba(255, 255, 255, 0.15)';
                    suspendBtn.style.background = 'rgba(255, 255, 255, 0.05)';
                }
            }
            document.getElementById('agentHeaderRank').innerText = (iden.rank_level || 'agent').replace(/_/g, ' ').toUpperCase();
            document.getElementById('agentHeaderSub').innerText = `${iden.username} \u2022 ${iden.email} \u2022 ${iden.parent_agent_name}`;

            // Profile Tab
            document.getElementById('profUsername').innerText = iden.username;
            document.getElementById('profCode').innerText = iden.agent_code;
            document.getElementById('profEmail').innerText = iden.email;
            document.getElementById('profPhone').innerText = iden.phone;
            document.getElementById('profDepth').innerText = iden.tree_depth || 'Level 1';
            document.getElementById('profJoined').innerText = iden.joined_formatted || iden.created_at;

            document.getElementById('profBalance').innerText = '₹' + cred.balance.toLocaleString('en-IN');
            document.getElementById('profFreeOpen').innerText = '₹' + cred.free_of_open_bets.toLocaleString('en-IN');
            document.getElementById('profOwnExposure').innerText = '₹' + cred.own_exposure.toLocaleString('en-IN');
            document.getElementById('profNetExposure').innerText = '₹' + cred.net_exposure.toLocaleString('en-IN');

            // Settled P&L with proper color and breakdown
            const pnlVal = cred.settled_pnl !== undefined ? cred.settled_pnl : 0;
            const pnlElem = document.getElementById('profSettledPnl');
            if (pnlElem) {
                pnlElem.innerText = (pnlVal < 0 ? '-' : (pnlVal > 0 ? '+' : '')) + '₹' + Math.abs(pnlVal).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                pnlElem.style.color = pnlVal < 0 ? '#f87171' : (pnlVal > 0 ? '#34d399' : '#ffffff');
            }
            const pnlSub = document.getElementById('profPnlBreakdown');
            if (pnlSub) {
                pnlSub.innerText = `Direct: ₹${(cred.direct_pnl || 0).toFixed(2)} | Downline: ₹${(cred.downline_pnl || 0).toFixed(2)}`;
            }

            // Turnover Commission
            const commVal = cred.turnover_commission !== undefined ? cred.turnover_commission : 0;
            const commElem = document.getElementById('profTurnoverComm');
            if (commElem) {
                commElem.innerText = '₹' + commVal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            }
            const commSub = document.getElementById('profCommBreakdown');
            if (commSub) {
                commSub.innerText = `Direct: ₹${(cred.direct_commission || 0).toFixed(2)} | Downline: +₹${(cred.downline_commission || 0).toFixed(2)}`;
            }

            // Net Real Revenue
            const revVal = cred.total_real_revenue !== undefined ? cred.total_real_revenue : (commVal + pnlVal);
            const revElem = document.getElementById('profRealRevenue');
            if (revElem) {
                revElem.innerText = (revVal < 0 ? '-' : (revVal > 0 ? '+' : '')) + '₹' + Math.abs(revVal).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                revElem.style.color = revVal < 0 ? '#f87171' : (revVal > 0 ? '#34d399' : '#ffffff');
            }

            document.getElementById('profUnsettledPnl').innerText = '₹' + cred.unsettled_pnl.toLocaleString('en-IN');

            document.getElementById('netDirect').innerText = net.direct;
            document.getElementById('netSubtree').innerText = net.whole_subtree;
            document.getElementById('netPlayers').innerText = net.players;

            // Terms Tab
            document.getElementById('termsPartnership').value = iden.partnership_pct;
            document.getElementById('termsCommission').value = iden.turnover_commission_pct;
            document.getElementById('termsLevel').value = iden.rank_level || 'agent';
            document.getElementById('termsName').value = iden.name;
            document.getElementById('termsEmail').value = iden.email;
            document.getElementById('termsPhone').value = iden.phone;

            // Downline Tab
            const downlines = agentData.downline_tree || [];
            document.getElementById('downlineCountBadge').innerText = `${downlines.length} Downline Accounts`;

            if (downlines.length === 0) {
                document.getElementById('downlineEmptyBox').classList.remove('d-none');
                document.getElementById('downlineTreeModelBox').classList.add('d-none');
                document.getElementById('downlineTableBox').classList.add('d-none');
            } else {
                document.getElementById('downlineEmptyBox').classList.add('d-none');
                
                // Render Visual Tree Model
                const rootTreeData = agentData.nested_tree_root;
                const renderArea = document.getElementById('treeModelRenderArea');
                renderArea.innerHTML = renderTreeNodeHTML(rootTreeData, true);

                // Render Data Table
                const tbody = document.getElementById('downlineTableBody');
                tbody.innerHTML = downlines.map(d => `
                    <tr>
                        <td><strong>${d.name || d.username}</strong> (@${d.username} - ${d.agent_code || ('AGT-' + d.id)})</td>
                        <td><span class="tag tag-purple">${(d.rank_level || 'agent').replace(/_/g, ' ').toUpperCase()}</span></td>
                        <td class="text-emerald-400 fw-bold">₹${parseFloat(d.current_credit || 0).toLocaleString('en-IN')}</td>
                        <td>${d.partnership_pct || 0}%</td>
                        <td><a href="?id=${d.id}" class="btn btn-xs btn-outline-info text-white font-bold" style="font-size:11px; padding: 2px 8px;">Inspect Node</a></td>
                    </tr>
                `).join('');
            }

            // Players Tab
            const players = agentData.assigned_players || [];
            if (players.length > 0) {
                document.getElementById('playersEmptyBox').classList.add('d-none');
                const box = document.getElementById('playersTableBox');
                box.classList.remove('d-none');
                box.innerHTML = `
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr><th>Player</th><th>Phone</th><th>Balance</th><th>Joined</th></tr>
                            </thead>
                            <tbody>
                                ${players.map(p => `
                                    <tr>
                                        <td><strong>${p.full_name || p.username}</strong> (@${p.username})</td>
                                        <td>${p.phone || 'N/A'}</td>
                                        <td class="text-emerald-400 fw-bold">₹${parseFloat(p.balance || 0).toLocaleString('en-IN')}</td>
                                        <td>${p.tbl_user_joined || '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            // Transfers Tab
            const transfers = agentData.recent_ledger || [];
            if (transfers.length > 0) {
                document.getElementById('transfersEmptyBox').classList.add('d-none');
                const box = document.getElementById('transfersTableBox');
                box.classList.remove('d-none');
                box.innerHTML = `
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Remark</th><th>Date & Time</th></tr>
                            </thead>
                            <tbody>
                                ${transfers.map(t => {
                                    const typeUpper = (t.transaction_type || '').toUpperCase();
                                    const isDeposit = typeUpper === 'INJECTION' || typeUpper === 'DEPOSIT' || typeUpper === 'TRANSFER_IN';
                                    const isWithdrawal = typeUpper === 'CLAWBACK' || typeUpper === 'WITHDRAWAL' || typeUpper === 'TRANSFER_OUT';
                                    const tagClass = isDeposit ? 'tag-success' : (isWithdrawal ? 'tag-danger' : 'tag-purple');
                                    return `
                                    <tr>
                                        <td><span class="tag ${tagClass}">${t.transaction_type}</span></td>
                                        <td class="fw-bold text-white">₹${parseFloat(t.amount || 0).toLocaleString('en-IN')}</td>
                                        <td class="text-slate-400 font-mono">₹${parseFloat(t.balance_before || 0).toLocaleString('en-IN')}</td>
                                        <td class="text-emerald-400 font-mono fw-bold">₹${parseFloat(t.balance_after || 0).toLocaleString('en-IN')}</td>
                                        <td class="text-slate-300">${t.remark || '—'}</td>
                                        <td class="text-slate-400 font-mono">${t.created_at || '—'}</td>
                                    </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            // Settlements Tab
            const settlements = agentData.settlements || [];
            if (settlements.length > 0) {
                const emptyBox = document.getElementById('settlementsEmptyBox');
                if (emptyBox) emptyBox.classList.add('d-none');
                const box = document.getElementById('settlementsTableBox');
                if (box) {
                    box.classList.remove('d-none');
                    box.innerHTML = `
                        <div class="detail-card-box p-0 overflow-auto">
                            <table class="r-table">
                                <thead>
                                    <tr><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Remark</th><th>Date & Time</th></tr>
                                </thead>
                                <tbody>
                                    ${settlements.map(s => {
                                        const isPnl = s.transaction_type === 'PNL_SETTLEMENT';
                                        const tagClass = isPnl ? 'tag-purple' : 'tag-success';
                                        const amt = parseFloat(s.amount || 0);
                                        const amtColor = amt < 0 ? 'text-danger' : 'text-emerald-400';
                                        const sign = amt < 0 ? '-' : '+';
                                        return `
                                        <tr>
                                            <td><span class="tag ${tagClass}">${(s.transaction_type || '').replace(/_/g, ' ')}</span></td>
                                            <td class="fw-bold ${amtColor}">${sign}₹${Math.abs(amt).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                            <td class="text-slate-400 font-mono">₹${parseFloat(s.balance_before || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                            <td class="text-white font-mono fw-bold">₹${parseFloat(s.balance_after || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                            <td class="text-slate-300">${s.remark || '—'}</td>
                                            <td class="text-slate-400 font-mono">${s.created_at || '—'}</td>
                                        </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            }

            // Activity Log Tab
            const logs = agentData.activity_logs || [];
            const tbody = document.getElementById('activityTableBody');
            tbody.innerHTML = '';

            if (logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-slate-500">No activity logs recorded.</td></tr>`;
                document.getElementById('activityFooter').innerText = `Showing all 0 entries`;
            } else {
                logs.forEach(l => {
                    const actLower = (l.action || '').toLowerCase();
                    let tagClass = 'tag-purple';
                    if (actLower.includes('deposit') || actLower.includes('injection') || actLower.includes('activated') || actLower.includes('received') || actLower.includes('allocated')) {
                        tagClass = 'tag-success';
                    } else if (actLower.includes('withdrawal') || actLower.includes('clawback') || actLower.includes('suspended') || actLower.includes('deleted') || actLower.includes('transferred')) {
                        tagClass = 'tag-danger';
                    }
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${l.sl_no}</td>
                        <td class="text-slate-300 font-mono">${l.date_time}</td>
                        <td class="text-white font-bold">${l.user}</td>
                        <td><span class="tag ${tagClass} font-mono" style="text-transform: none;">${l.action}</span></td>
                        <td class="text-cyan-code">${l.target}</td>
                        <td class="text-slate-400 font-mono">${l.ip_address}</td>
                    `;
                    tbody.appendChild(tr);
                });
                document.getElementById('activityFooter').innerText = `Showing all ${logs.length} entries`;
            }

        } else {
            document.getElementById('agentHeaderTitle').innerText = 'Agent Not Found';
            Swal.fire('Error', data.message || 'Agent detail not found.', 'error');
        }
    } catch (e) {
        console.error("Failed to load agent console:", e);
        document.getElementById('agentHeaderTitle').innerText = 'Error Loading Agent';
    }
}

async function saveAgentTerms(e) {
    e.preventDefault();
    if (!agentId) return;

    const partnership_pct = parseFloat(document.getElementById('termsPartnership').value || 25);
    const commission_pct = parseFloat(document.getElementById('termsCommission').value || 2);
    const rank_level = document.getElementById('termsLevel').value;
    const name = document.getElementById('termsName').value.trim();
    const email = document.getElementById('termsEmail').value.trim();
    const phone = document.getElementById('termsPhone').value.trim();

    try {
        const resp = await fetch('api_detail.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: agentId,
                name, email, phone, rank_level, partnership_pct, commission_pct
            })
        });
        const text = await resp.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            Swal.fire('Error', 'Server error: ' + text.replace(/<[^>]*>?/gm, ' ').trim().substring(0, 150), 'error');
            return;
        }

        if (data.status === 'success') {
            Swal.fire({
                title: 'Changes Saved!',
                text: 'Commercial terms and contact settings updated.',
                icon: 'success',
                background: '#0f172a',
                color: '#fff'
            });
            loadAgentConsole();
        } else {
            Swal.fire('Error', data.message || 'Failed to save changes.', 'error');
        }
    } catch (err) {
        Swal.fire('Error', err.message || 'Network error saving changes.', 'error');
    }
}

async function changeDetailAgentPassword() {
    if (!agentId) return;
    const { value: newPassword } = await Swal.fire({
        title: 'Reset Password',
        text: `Enter new password for agent ${agentData?.identity?.name || ('#' + agentId)}`,
        input: 'password',
        inputPlaceholder: 'Min 6 characters',
        showCancelButton: true,
        confirmButtonText: 'Update Password',
        confirmButtonColor: '#3b82f6',
        background: '#0f172a',
        color: '#ffffff'
    });

    if (newPassword && newPassword.length >= 6) {
        const res = await fetch('../api_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agent_id: agentId, new_password: newPassword })
        });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire('Updated', 'Agent password changed successfully.', 'success');
        } else {
            Swal.fire('Error', data.message || 'Failed to update password.', 'error');
        }
    }
}

async function toggleDetailAgentStatus() {
    if (!agentData) return;
    const currentStatus = agentData.identity.status || 'active';
    const nextStatus = currentStatus === 'active' ? 'suspended' : 'active';

    Swal.fire({
        title: `${nextStatus === 'suspended' ? 'Suspend' : 'Activate'} Agent?`,
        text: `Change account status to ${nextStatus}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirm'
    }).then(async (res) => {
        if (res.isConfirmed) {
            const resp = await fetch('../status/api_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: agentId, status: nextStatus })
            });
            const data = await resp.json();
            if (data.status === 'success') {
                Swal.fire('Status Updated', `Agent account is now ${nextStatus}.`, 'success');
                loadAgentConsole();
            }
        }
    });
}

async function deleteDetailAgent() {
    if (!agentData) return;
    Swal.fire({
        title: `Delete Agent ${agentData.identity.name}?`,
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
    }).then(async (res) => {
        if (res.isConfirmed && res.value) {
            const resp = await fetch('../api_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: agentId, reason: res.value.trim() })
            });
            const data = await resp.json();
            if (data.status === 'success') {
                Swal.fire({
                    title: 'Account Deleted & Archived',
                    text: data.message || 'Agent deleted permanently and archived.',
                    icon: 'success'
                }).then(() => {
                    window.location.href = '/admin/agents/';
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to delete agent', 'error');
            }
        }
    });
}

let currentCreditAgentId = null;
let currentCreditAgentBalance = 0;
let creditMode = 'deposit';

function openCreditModal(id, name, balance) {
    currentCreditAgentId = id || agentId;
    const targetName = name || (agentData ? agentData.identity.name : 'Agent');
    currentCreditAgentBalance = parseFloat(balance !== undefined ? balance : (agentData ? agentData.credit_exposure.balance : 0));
    
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
        const res = await fetch('../credit/api_credit.php', {
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
            if (typeof loadAgentConsole === 'function') loadAgentConsole();
        } else {
            Swal.fire('Failed', data.message || 'Failed to adjust credit balance.', 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Connection failed: ' + err.message, 'error');
    } finally {
        submitBtn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', loadAgentConsole);
</script>
</body>
</html>
