<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
session_cache_limiter("");
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") { header('location:../logout-account'); exit; }

$f_username = mysqli_real_escape_string($conn, $_POST['f_username'] ?? $_GET['f_username'] ?? '');
$f_game = mysqli_real_escape_string($conn, $_POST['f_game'] ?? $_GET['f_game'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');

$content = 15;
$page_num = (int) ($_GET['page_num'] ?? 1);
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $content;

$where = "WHERE LOWER(tbl_project_name) IN ('saba sports', 'lucksport', 'lucksportgaming')";
if($f_username != "") $where .= " AND tbl_user_id LIKE '%$f_username%'";
if($f_game != "") $where .= " AND tbl_project_name LIKE '%$f_game%'";
if ($f_date_from != "" && $f_date_to != "") {
    $where .= " AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y') BETWEEN STR_TO_DATE('$f_date_from', '%Y-%m-%d') AND STR_TO_DATE('$f_date_to', '%Y-%m-%d')";
}

$sql = "SELECT * FROM tblmatchplayed $where ORDER BY id DESC LIMIT $offset, $content";
$total_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tblmatchplayed $where"))['total'];
$total_pages = ceil($total_count / $content);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title>Sports Report</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body { font-family: 'DM Sans', sans-serif !important; background: var(--page-bg); color: var(--text-main); margin: 0; }
        .advanced-filter-card { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; background: rgba(255, 255, 255, 0.02); padding: 10px 15px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); margin-bottom: 20px; }
        .filter-input-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-dim); margin-left: 2px; opacity: 0.8; }
        .filter-input-wrapper { position: relative; display: flex; align-items: center; }
        .filter-input-wrapper i { position: absolute; left: 10px; font-size: 14px; color: var(--accent-blue); opacity: 0.7; }
        .filter-inp { width: 100%; height: 36px; background: rgba(0, 0, 0, 0.2) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 8px !important; padding: 0 10px 0 32px !important; color: #fff !important; font-size: 11px !important; font-weight: 500 !important; }
        .btn-filter-submit { width: 100%; height: 36px; background: linear-gradient(135deg, #06b6d4, #3b82f6); border: none; border-radius: 8px; color: #fff; font-weight: 800; font-size: 11px; display: flex; align-items: center; justify-content: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .r-table thead th { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #94a3b8; padding: 0 12px 6px; letter-spacing: 1px; }
        .r-table tbody td { padding: 10px 12px; font-size: 12px; font-weight: 600; color: var(--text-main); background: rgba(255, 255, 255, 0.02); border-top: 1px solid rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .r-table tbody td:first-child { border-radius: 10px 0 0 10px; border-left: 1px solid rgba(255, 255, 255, 0.05); }
        .r-table tbody td:last-child { border-radius: 0 10px 10px 0; border-right: 1px solid rgba(255, 255, 255, 0.05); }
        .r-table tr:hover td { background: rgba(255, 255, 255, 0.05); cursor: pointer; }
        .pag-box { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: rgba(255, 255, 255, 0.05); color: #fff; text-decoration: none; font-size: 11px; font-weight: 700; }
        .pag-box.active { background: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.4); }
    </style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-header-left">
                <span class="dash-breadcrumb">Intelligence > Reports</span>
                <h1 class="dash-title">Sports Turnover</h1>
            </div>
            <div class="dash-header-right">
                <button class="btn-modern btn-outline-modern" onclick="exportData('pdf')">
                    <i class='bx bxs-file-pdf' style="color: #f43f5e;"></i> Download PDF
                </button>
                <button class="btn-modern btn-outline-modern" onclick="exportData('excel')">
                    <i class='bx bx-cloud-download'></i> Bulk Export
                </button>
                <button class="btn-modern btn-outline-modern" onclick="window.location.href='view-sports.php'">
                    <i class='bx bx-refresh'></i> Refresh Data
                </button>
                <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'">
                    <i class='bx bx-arrow-back'></i> Back
                </button>
            </div>
        </div>

        <form method="GET" class="advanced-filter-card">
            <div class="filter-input-group">
                <span class="filter-label">Player ID</span>
                <div class="filter-input-wrapper">
                    <i class='bx bx-id-card'></i>
                    <input type="text" name="f_username" class="filter-inp" value="<?php echo $f_username; ?>" placeholder="Search UID..">
                </div>
            </div>
            <div class="filter-input-group">
                <span class="filter-label">Provider</span>
                <div class="filter-input-wrapper">
                    <i class='bx bx-game'></i>
                    <input type="text" name="f_game" class="filter-inp" value="<?php echo $f_game; ?>" placeholder="e.g. LuckSport">
                </div>
            </div>
            <div class="filter-input-group">
                <span class="filter-label">From Date</span>
                <div class="filter-input-wrapper">
                    <i class='bx bx-calendar'></i>
                    <input type="date" name="f_date_from" class="filter-inp" value="<?php echo $f_date_from; ?>">
                </div>
            </div>
            <div class="filter-input-group">
                <span class="filter-label">To Date</span>
                <div class="filter-input-wrapper">
                    <i class='bx bx-calendar-event'></i>
                    <input type="date" name="f_date_to" class="filter-inp" value="<?php echo $f_date_to; ?>">
                </div>
            </div>
            <div class="filter-action-area">
                <button type="submit" class="btn-filter-submit"><i class='bx bx-search-alt'></i> Apply Filters</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="r-table">
                <thead><tr><th>No</th><th>Player</th><th>Provider</th><th>Stake</th><th>Net P&L</th><th>Selection</th><th>Timestamp</th></tr></thead>
                <tbody>
                    <?php 
                    $res = mysqli_query($conn, $sql); 
                    $no = $offset + 1;
                    while($row = mysqli_fetch_assoc($res)): 
                    ?>
                    <tr>
                        <td class="text-dim"><?php echo $no++; ?></td>
                        <td class="text-info fw-bold">#<?php echo $row['tbl_user_id']; ?></td>
                        <td><div class="fw-bold"><?php echo strtoupper($row['tbl_project_name']); ?></div></td>
                        <td class="fw-bold">₹<?php echo number_format($row['tbl_match_cost'], 2); ?></td>
                        <td class="fw-bold <?php echo $row['tbl_match_profit']>=0?'text-success':'text-danger'; ?>">
                            <?php echo $row['tbl_match_profit']>=0 ? '+₹'.number_format($row['tbl_match_profit'], 2) : '-₹'.number_format(abs($row['tbl_match_profit']), 2); ?>
                        </td>
                        <td class="text-info"><?php echo $row['tbl_selection']; ?></td>
                        <td class="text-dim"><?php echo $row['tbl_time_stamp']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="small text-dim">Showing page <?php echo $page_num; ?> of <?php echo $total_pages; ?> (<?php echo $total_count; ?> records)</div>
            <div class="d-flex gap-2">
                <?php for($i=max(1, $page_num-2); $i<=min($total_pages, $page_num+2); $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&f_username=<?php echo $f_username; ?>&f_game=<?php echo $f_game; ?>&f_date_from=<?php echo $f_date_from; ?>&f_date_to=<?php echo $f_date_to; ?>" class="pag-box <?php echo $i==$page_num?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<script>
function exportData(format) {
    const f_username = document.querySelector('input[name="f_username"]').value;
    const f_game = document.querySelector('input[name="f_game"]').value;
    const f_date_from = document.querySelector('input[name="f_date_from"]').value;
    const f_date_to = document.querySelector('input[name="f_date_to"]').value;

    const baseUrl = `export.php?type=sports&f_username=${f_username}&f_game=${f_game}&f_date_from=${f_date_from}&f_date_to=${f_date_to}`;

    if (format === 'excel') {
        window.location.href = baseUrl + '&format=excel';
    } else if (format === 'pdf') {
        fetch(baseUrl + '&format=json')
            .then(response => response.json())
            .then(data => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'mm', 'a4');
                
                doc.setFontSize(18);
                doc.text('Sports Turnover Report', 14, 15);
                doc.setFontSize(10);
                doc.text('Generated on: ' + new Date().toLocaleString(), 14, 22);

                const tableData = data.map((row, index) => [
                    index + 1,
                    row.UserID,
                    row.Game,
                    row.Cost,
                    row.Profit,
                    row.Selection,
                    row.Time
                ]);

                doc.autoTable({
                    head: [['No', 'User ID', 'Provider', 'Stake', 'P&L', 'Selection', 'Time']],
                    body: tableData,
                    startY: 30,
                    theme: 'striped',
                    headStyles: { fillColor: [20, 184, 166], textColor: 255 },
                    styles: { fontSize: 8, cellPadding: 3 },
                    margin: { top: 30 }
                });

                doc.save('sports_full_report_' + new Date().getTime() + '.pdf');
            });
    }
}
</script>
</body>
</html>
