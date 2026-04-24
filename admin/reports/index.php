<?php
header("Cache-Control: no cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_users_data") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Downloadable Reports</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
    body {
        font-family: var(--font-body) !important;
        background-color: var(--page-bg) !important;
        min-height: 100vh; color: var(--text-main); margin: 0; padding: 0;
    }

    .dash-header { margin-bottom: 15px !important; padding-bottom: 10px !important; border-bottom: 1px solid var(--border-dim); }
    .dash-title h1 { font-size: 20px !important; font-weight: 800; margin: 0; }
    .dash-breadcrumb { font-size: 9px !important; opacity: 0.7; margin-bottom: 0px !important; display: block; }

    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 16px;
        padding: 10px 0;
    }

    .report-card {
        background: var(--panel-bg);
        border: 1px solid var(--border-dim);
        border-radius: 16px;
        padding: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .report-card:hover {
        transform: translateY(-3px);
        border-color: var(--accent-blue);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .report-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-bottom: 12px;
        background: rgba(59, 130, 246, 0.08);
        color: var(--accent-blue);
    }

    .report-title {
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 2px;
        color: var(--text-main);
    }

    .report-desc {
        font-size: 10px;
        color: var(--text-dim);
        line-height: 1.3;
        margin-bottom: 12px;
        opacity: 0.7;
    }

    .btn-group-export {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px;
    }

    .download-btn {
        padding: 6px;
        border-radius: 6px;
        background: var(--input-bg);
        border: 1px solid var(--border-dim);
        color: var(--text-main);
        font-weight: 700;
        font-size: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .download-btn:hover {
        background: var(--accent-blue);
        color: #fff;
        border-color: var(--accent-blue);
    }

    .download-btn.btn-view {
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-blue);
        border-color: rgba(59, 130, 246, 0.2);
    }

    .download-btn.btn-view:hover {
        background: var(--accent-blue);
        color: #fff;
    }

    .download-btn.btn-pdf:hover {
        background: var(--accent-rose);
        border-color: var(--accent-rose);
    }

    .badge-premium {
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 7px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 2px 6px;
        background: rgba(16, 185, 129, 0.1);
        color: var(--accent-emerald);
        border-radius: 6px;
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Intelligence Center</span>
                <h1>Reports</h1>
            </div>
        </div>

        <div class="reports-grid">
            <!-- Players Data -->
            <div class="report-card">
                <div>
                    <div class="report-icon"><i class='bx bx-user'></i></div>
                    <div class="report-title">Players Data</div>
                    <div class="report-desc">All registered players and joining dates.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-players.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('players', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('players', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Chargeback Data -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(244, 63, 94, 0.1); color: var(--accent-rose);"><i class='bx bx-error-alt'></i></div>
                    <div class="report-title">Chargeback Data</div>
                    <div class="report-desc">Disputed transactions and reversals.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-chargeback.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('chargeback', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('chargeback', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Fraud/Banned Players Data -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-amber);"><i class='bx bx-user-x'></i></div>
                    <div class="report-title">Fraud/Banned Players</div>
                    <div class="report-desc">Accounts flagged for suspicious activity.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-banned.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('banned', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('banned', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Deposits Data -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald);"><i class='bx bx-trending-up'></i></div>
                    <div class="report-title">Deposits Data</div>
                    <div class="report-desc">Log of all successful wallet recharges.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-deposits.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('deposits', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('deposits', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Player Bonus Data -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(139, 92, 246, 0.1); color: var(--accent-purple);"><i class='bx bx-gift'></i></div>
                    <div class="report-title">Player Bonus Data</div>
                    <div class="report-desc">Bonus redemptions and wagering.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-bonus.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('bonus', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('bonus', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(100, 116, 139, 0.1); color: #64748b;"><i class='bx bx-history'></i></div>
                    <div class="report-title">Transaction History</div>
                    <div class="report-desc">Unified ledger of every credit and debit.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-transactions.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('transactions', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('transactions', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- SportsReport -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(20, 184, 166, 0.1); color: var(--accent-teal);"><i class='bx bx-football'></i></div>
                    <div class="report-title">Sports Report</div>
                    <div class="report-desc">Turnover and profit/loss metrics.</div>
                </div>
                <div class="btn-group-export">
                    <button class="download-btn btn-view" onclick="window.location.href='view-sports.php'"><i class='bx bx-show'></i> View</button>
                    <button class="download-btn" onclick="exportData('sports', 'excel')"><i class='bx bx-spreadsheet'></i> Excel</button>
                    <button class="download-btn btn-pdf" onclick="exportData('sports', 'pdf')"><i class='bx bxs-file-pdf'></i> PDF</button>
                </div>
            </div>

            <!-- Player Based Report -->
            <div class="report-card">
                <div>
                    <div class="report-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--accent-pink);"><i class='bx bx-search-alt'></i></div>
                    <div class="report-title">Player Based Report</div>
                    <div class="report-desc">Focused report for a specific player ID.</div>
                </div>
                <div class="btn-group-export" style="grid-template-columns: 1fr;">
                    <button class="download-btn btn-view" onclick="ShowPlayerReportModal()"><i class='bx bx-file-find'></i> Select Player</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Simple Player Search Modal -->
<div class="modal fade" id="playerReportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 24px;">
      <div class="modal-body p-4 position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="font-size: 10px;"></button>
        <h5 class="report-title mb-4">Generate Player Report</h5>
        <div class="mb-3">
          <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);">Enter Player Unique ID / Mobile</label>
          <input type="text" id="target_player_id" class="form-control" style="background: var(--input-bg); border: 1px solid var(--border-dim); color: #fff; height: 50px; border-radius: 12px;" placeholder="Example: 123456" onkeyup="LookupPlayerInfo(this.value)">
        </div>
        <div id="player_preview_area" class="mb-4" style="display: none; background: rgba(255,255,255,0.03); padding: 12px; border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1);">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent-blue); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class='bx bxs-user-check'></i>
                </div>
                <div>
                    <div id="prev_username" style="font-weight: 800; font-size: 14px; color: #fff;">Username</div>
                    <div id="prev_details" style="font-size: 10px; color: var(--text-dim);">ID: #123456 | Mob: 999...</div>
                </div>
            </div>
        </div>
        <div class="btn-group-export">
          <button class="download-btn" id="btn_gen_excel" style="height: 44px; opacity: 0.5;" onclick="GeneratePlayerBasedReport('excel')" disabled>Excel</button>
          <button class="download-btn btn-pdf" id="btn_gen_pdf" style="height: 44px; opacity: 0.5;" onclick="GeneratePlayerBasedReport('pdf')" disabled>PDF</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function exportData(type, format) {
        if (format === 'excel') {
            window.location.href = 'export.php?type=' + type + '&format=excel';
        } else {
            // PDF Generation via AJAX fetch and jsPDF
            generatePDF(type);
        }
    }

    async function generatePDF(type, uid = '') {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Fetch data from export.php as JSON
        const response = await fetch(`export.php?type=${type}&format=json${uid ? '&uid='+uid : ''}`);
        const data = await response.json();
        
        if (!data || data.length === 0) {
            alert("No records found to generate PDF.");
            return;
        }

        const headers = Object.keys(data[0]);
        const rows = data.map(item => Object.values(item));

        doc.setFontSize(18);
        doc.text(`${type.toUpperCase()} REPORT`, 14, 22);
        doc.setFontSize(11);
        doc.setTextColor(100);
        doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 30);

        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 40,
            theme: 'striped',
            headStyles: { fillColor: [6, 182, 212] },
            styles: { fontSize: 8 }
        });

        doc.save(`${type}_report_${Date.now()}.pdf`);
    }

    function ShowPlayerReportModal() {
        const modal = new bootstrap.Modal(document.getElementById('playerReportModal'));
        modal.show();
    }

    function GeneratePlayerBasedReport(format) {
        const input = document.getElementById('target_player_id');
        const uid = input.dataset.verifiedId || input.value;
        if(uid.trim() === "") {
            alert("Please enter a valid User ID");
            return;
        }
        if (format === 'excel') {
            window.location.href = 'export.php?type=player_based&format=excel&uid=' + uid;
        } else {
            generatePDF('player_based', uid);
        }
    }

    let lookupTimer;
    function LookupPlayerInfo(val) {
        clearTimeout(lookupTimer);
        const preview = document.getElementById('player_preview_area');
        const btnExcel = document.getElementById('btn_gen_excel');
        const btnPdf = document.getElementById('btn_gen_pdf');
        const input = document.getElementById('target_player_id');
        
        if (val.length < 4) {
            preview.style.display = 'none';
            btnExcel.disabled = true; btnExcel.style.opacity = '0.5';
            btnPdf.disabled = true; btnPdf.style.opacity = '0.5';
            return;
        }

        lookupTimer = setTimeout(() => {
            fetch(`get-user-info.php?id=${val}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        preview.style.display = 'block';
                        document.getElementById('prev_username').innerText = data.username;
                        document.getElementById('prev_details').innerText = `ID: #${data.userid} | Mob: ${data.mobile}`;
                        input.dataset.verifiedId = data.userid;
                        btnExcel.disabled = false; btnExcel.style.opacity = '1';
                        btnPdf.disabled = false; btnPdf.style.opacity = '1';
                    } else {
                        preview.style.display = 'none';
                        btnExcel.disabled = true; btnExcel.style.opacity = '0.5';
                        btnPdf.disabled = true; btnPdf.style.opacity = '0.5';
                    }
                });
        }, 400);
    }
</script>
</body>
</html>
</body>
</html>
