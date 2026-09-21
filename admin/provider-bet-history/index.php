<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../logout-account');
}

// Request Filter Values
$f_provider = mysqli_real_escape_string($conn, $_POST['f_provider'] ?? $_GET['f_provider'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');
$f_search = mysqli_real_escape_string($conn, $_POST['f_search'] ?? $_GET['f_search'] ?? '');

// Fetch All Unique Providers for Filter Dropdown
$providers_list = [];
$prov_sql = "SELECT DISTINCT tbl_project_name FROM tblmatchplayed WHERE tbl_project_name IS NOT NULL AND TRIM(tbl_project_name) != '' ORDER BY tbl_project_name ASC";
$prov_res = mysqli_query($conn, $prov_sql);
if ($prov_res) {
    while ($p_row = mysqli_fetch_assoc($prov_res)) {
        $providers_list[] = $p_row['tbl_project_name'];
    }
}

// Build SQL Where Clauses
$where_clauses = ["m.tbl_project_name IS NOT NULL AND TRIM(m.tbl_project_name) != ''"];

if ($f_provider != "") {
    $where_clauses[] = "m.tbl_project_name = '$f_provider'";
}

if ($f_search != "") {
    $where_clauses[] = "m.tbl_project_name LIKE '%$f_search%'";
}

if ($f_date_from != "" && $f_date_to != "") {
    $where_clauses[] = "(
        (m.tbl_time_stamp LIKE '__-__-____%' AND STR_TO_DATE(LEFT(m.tbl_time_stamp, 10), '%d-%m-%Y') BETWEEN '$f_date_from' AND '$f_date_to')
        OR (m.tbl_time_stamp LIKE '____-__-__%' AND LEFT(m.tbl_time_stamp, 10) BETWEEN '$f_date_from' AND '$f_date_to')
    )";
}

$where_str = implode(" AND ", $where_clauses);

// Fast Provider Aggregation Query
$provider_summary_sql = "
    SELECT 
        m.tbl_project_name AS provider_name,
        COUNT(*) AS total_bets_count,
        SUM(CAST(m.tbl_match_cost AS DECIMAL(16,2))) AS total_bet_done,
        SUM(CASE 
            WHEN LOWER(m.tbl_match_status) IN ('profit', 'win', 'won', 'cashout') OR LOWER(m.tbl_match_result) IN ('profit', 'win', 'won', 'cashout') 
            THEN CAST(m.tbl_match_profit AS DECIMAL(16,2)) 
            ELSE 0 
        END) AS total_user_win,
        SUM(CASE 
            WHEN LOWER(m.tbl_match_status) IN ('loss', 'lost') OR LOWER(m.tbl_match_result) IN ('loss', 'lost') 
            THEN CAST(m.tbl_match_cost AS DECIMAL(16,2)) 
            ELSE 0 
        END) AS total_user_loss
    FROM tblmatchplayed m
    WHERE $where_str
    GROUP BY m.tbl_project_name
    ORDER BY total_bet_done DESC";

$provider_summary_res = mysqli_query($conn, $provider_summary_sql);

// Calculate Grand Totals across providers
$grand_total_bets_count = 0;
$grand_total_bet_done = 0;
$grand_total_user_win = 0;
$grand_total_user_loss = 0;

$summary_rows = [];
if ($provider_summary_res && mysqli_num_rows($provider_summary_res) > 0) {
    while ($s_row = mysqli_fetch_assoc($provider_summary_res)) {
        $s_row['total_bets_count'] = intval($s_row['total_bets_count']);
        $s_row['total_bet_done'] = floatval($s_row['total_bet_done']);
        $s_row['total_user_win'] = floatval($s_row['total_user_win']);
        $s_row['total_user_loss'] = floatval($s_row['total_user_loss']);
        $s_row['house_net'] = $s_row['total_user_loss'] - $s_row['total_user_win'];
        
        $grand_total_bets_count += $s_row['total_bets_count'];
        $grand_total_bet_done += $s_row['total_bet_done'];
        $grand_total_user_win += $s_row['total_user_win'];
        $grand_total_user_loss += $s_row['total_user_loss'];
        
        $summary_rows[] = $s_row;
    }
}
$grand_house_net = $grand_total_user_loss - $grand_total_user_win;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Provider Bet History</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        <?php include "../components/theme-variables.php"; ?>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: var(--text-main);
            margin: 0; padding: 0;
            overflow-x: hidden;
        }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-dim);
            padding-bottom: 16px;
        }

        .dash-breadcrumb {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: block;
            margin-bottom: 4px;
        }

        .dash-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-main);
            line-height: 1.2;
        }

        .search-area {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }

        .advanced-filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .filter-grp { display: flex; flex-direction: column; gap: 4px; }
        .filter-lbl { font-size: 9px; font-weight: 800; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-left: 2px; }
        .filter-inp-box { position: relative; display: flex; align-items: center; }
        .filter-inp-box i { position: absolute; left: 10px; font-size: 14px; color: var(--accent-blue); opacity: 0.7; }
        .f-inp {
            color-scheme: dark;
            width: 100%; height: 36px;
            background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important;
            border-radius: 8px !important;
            padding: 0 10px 0 32px !important;
            color: var(--text-main) !important;
            font-size: 12px !important;
        }

        .btn-modern {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            height: 36px;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-outline-modern {
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            color: var(--text-dim);
        }

        .record-section {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 14px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .title-bar {
            width: 3px; height: 16px; border-radius: 4px;
            background: linear-gradient(180deg, #3b82f6, #06b6d4);
            flex-shrink: 0;
        }

        .r-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .r-table thead th {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1.2px; text-transform: uppercase;
            color: var(--text-dim);
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-dim);
        }

        .r-table tbody td {
            padding: 14px;
            font-size: 13px; font-weight: 500;
            color: var(--text-main);
            background: var(--table-header-bg);
            border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }

        .r-table tbody td:first-child {
            border-radius: 10px 0 0 10px;
            border-left: 1px solid var(--border-dim);
        }

        .r-table tbody td:last-child {
            border-radius: 0 10px 10px 0;
            border-right: 1px solid var(--border-dim);
        }

        .r-table tr:hover td {
            background: var(--table-row-hover);
        }

        .badge-profit {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 700;
        }

        .badge-loss {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 700;
        }

        .badge-neutral {
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 700;
        }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <!-- Dashboard Header -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div>
                        <span class="dash-breadcrumb">History & Records > Provider Bet Records</span>
                        <span class="dash-title">Provider Performance & Bet Summary</span>
                    </div>
                </div>
                <div class="dash-header-right d-flex gap-2">
                    <button class="btn-modern btn-outline-modern" type="button" onclick="exportExcel('providerTable', 'Provider-Bet-Summary.xlsx')">
                        <i class='bx bx-file'></i> Export Excel
                    </button>
                    <a href="index.php" class="btn-modern btn-outline-modern">
                        <i class='bx bx-refresh'></i> Refresh
                    </a>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="search-area">
                <form method="GET" action="index.php" class="advanced-filter-bar">
                    <div class="filter-grp">
                        <label class="filter-lbl">Select Game Provider</label>
                        <div class="filter-inp-box">
                            <i class='bx bx-layer'></i>
                            <select name="f_provider" class="f-inp" style="padding-left: 32px !important;">
                                <option value="">All Providers</option>
                                <?php foreach ($providers_list as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov); ?>" <?php if ($f_provider === $prov) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($prov); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-grp">
                        <label class="filter-lbl">Search Provider/Game</label>
                        <div class="filter-inp-box">
                            <i class='bx bx-search'></i>
                            <input type="text" name="f_search" value="<?php echo htmlspecialchars($f_search); ?>" class="f-inp" placeholder="e.g. Spribe, Saba...">
                        </div>
                    </div>

                    <div class="filter-grp">
                        <label class="filter-lbl">From Date</label>
                        <div class="filter-inp-box">
                            <i class='bx bx-calendar'></i>
                            <input type="date" name="f_date_from" value="<?php echo htmlspecialchars($f_date_from); ?>" class="f-inp">
                        </div>
                    </div>

                    <div class="filter-grp">
                        <label class="filter-lbl">To Date</label>
                        <div class="filter-inp-box">
                            <i class='bx bx-calendar-event'></i>
                            <input type="date" name="f_date_to" value="<?php echo htmlspecialchars($f_date_to); ?>" class="f-inp">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-modern btn-primary-modern flex-fill justify-content-center">
                            <i class='bx bx-filter-alt'></i> Apply Filter
                        </button>
                        <a href="index.php" class="btn-modern btn-outline-modern justify-content-center">Reset</a>
                    </div>
                </form>

                <!-- Quick Date Presets -->
                <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                    <span class="filter-lbl">Quick Presets:</span>
                    <a href="?f_date_from=<?php echo date('Y-m-d'); ?>&f_date_to=<?php echo date('Y-m-d'); ?>&f_provider=<?php echo urlencode($f_provider); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-d') && $f_date_to == date('Y-m-d')) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px;">Today</a>
                    <a href="?f_date_from=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&f_date_to=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&f_provider=<?php echo urlencode($f_provider); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-d', strtotime('-1 day')) && $f_date_to == date('Y-m-d', strtotime('-1 day'))) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px;">Yesterday</a>
                    <a href="?f_date_from=<?php echo date('Y-m-01'); ?>&f_date_to=<?php echo date('Y-m-d'); ?>&f_provider=<?php echo urlencode($f_provider); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-01') && $f_date_to == date('Y-m-d')) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px;">This Month</a>
                    <a href="?f_provider=<?php echo urlencode($f_provider); ?>" class="btn btn-sm btn-outline-primary <?php if (empty($f_date_from) && empty($f_date_to)) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px;">All Time</a>
                </div>
            </div>

            <!-- KPI Cards Overview -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded-3" style="background: var(--panel-bg); border-color: var(--border-dim) !important;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Active Providers</div>
                        <div class="h3 mb-0 fw-bold" style="color: var(--accent-blue);"><?php echo count($summary_rows); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;"><?php echo number_format($grand_total_bets_count); ?> Total Bets</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded-3" style="background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.2) !important;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total Bet Done</div>
                        <div class="h3 mb-0 fw-bold" style="color: #3b82f6;">&#8377;<?php echo number_format($grand_total_bet_done, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Total Stakes Volume</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded-3" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2) !important;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total User Win</div>
                        <div class="h3 mb-0 fw-bold" style="color: #10b981;">&#8377;<?php echo number_format($grand_total_user_win, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Total Winning Payouts</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded-3" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2) !important;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total User Loss</div>
                        <div class="h3 mb-0 fw-bold" style="color: #ef4444;">&#8377;<?php echo number_format($grand_total_user_loss, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Total Lost Stakes</div>
                    </div>
                </div>
            </div>

            <!-- Provider Aggregations Table -->
            <div class="record-section">
                <div class="section-title">
                    <span class="title-bar"></span>
                    Game Provider Statistics Breakdown
                </div>

                <div class="w-100 overflow-x-auto hide-native-scrollbar">
                    <table id="providerTable" class="r-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Game Provider</th>
                                <th style="width: 12%; text-align: center;">Total Bets</th>
                                <th style="width: 18%; text-align: right;">Total Bet Done (₹)</th>
                                <th style="width: 18%; text-align: right;">Total User Win (₹)</th>
                                <th style="width: 18%; text-align: right;">Total User Loss (₹)</th>
                                <th style="width: 18%; text-align: right;">Net House Profit (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($summary_rows)): 
                                $sl_num = 1;
                                foreach ($summary_rows as $row): 
                                    $h_net = $row['house_net'];
                            ?>
                                <tr>
                                    <td><span class="text-muted fw-bold"><?php echo $sl_num++; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class='bx bx-layer' style="color: var(--accent-blue); font-size: 18px;"></i>
                                            <span class="fw-bold"><?php echo htmlspecialchars($row['provider_name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge-neutral"><?php echo number_format($row['total_bets_count']); ?></span>
                                    </td>
                                    <td style="text-align: right;" class="fw-bold">&#8377;<?php echo number_format($row['total_bet_done'], 2); ?></td>
                                    <td style="text-align: right; color: #10b981;" class="fw-bold">&#8377;<?php echo number_format($row['total_user_win'], 2); ?></td>
                                    <td style="text-align: right; color: #ef4444;" class="fw-bold">&#8377;<?php echo number_format($row['total_user_loss'], 2); ?></td>
                                    <td style="text-align: right;">
                                        <?php if ($h_net >= 0): ?>
                                            <span class="badge-profit">+&#8377;<?php echo number_format($h_net, 2); ?></span>
                                        <?php else: ?>
                                            <span class="badge-loss">&#8377;<?php echo number_format($h_net, 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No provider betting records found for the selected filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: rgba(255,255,255,0.05); font-weight: 700;">
                                <td colspan="2" class="text-uppercase" style="padding: 14px; font-size: 12px; color: var(--text-main);">Grand Total Summary</td>
                                <td style="text-align: center; padding: 14px; color: var(--text-main);"><?php echo number_format($grand_total_bets_count); ?></td>
                                <td style="text-align: right; padding: 14px; color: #3b82f6;">&#8377;<?php echo number_format($grand_total_bet_done, 2); ?></td>
                                <td style="text-align: right; padding: 14px; color: #10b981;">&#8377;<?php echo number_format($grand_total_user_win, 2); ?></td>
                                <td style="text-align: right; padding: 14px; color: #ef4444;">&#8377;<?php echo number_format($grand_total_user_loss, 2); ?></td>
                                <td style="text-align: right; padding: 14px;">
                                    <?php if ($grand_house_net >= 0): ?>
                                        <span class="badge-profit">+&#8377;<?php echo number_format($grand_house_net, 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge-loss">&#8377;<?php echo number_format($grand_house_net, 2); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Recent Individual Bets by Selected Filter -->
            <div class="record-section">
                <div class="section-title">
                    <span class="title-bar"></span>
                    Recent Detailed Bets Log (Top 50)
                </div>
                <div class="w-100 overflow-x-auto hide-native-scrollbar">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th style="width: 6%;">No</th>
                                <th>User ID / Name</th>
                                <th>Provider / Game</th>
                                <th style="width: 15%; text-align: right;">Stake Cost (₹)</th>
                                <th style="width: 15%; text-align: right;">Win Payout (₹)</th>
                                <th style="width: 12%; text-align: center;">Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_sql = "
                                SELECT m.*, u.tbl_full_name 
                                FROM tblmatchplayed m 
                                LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id 
                                WHERE $where_str 
                                ORDER BY m.id DESC LIMIT 50";
                            $recent_res = mysqli_query($conn, $recent_sql);
                            if ($recent_res && mysqli_num_rows($recent_res) > 0) {
                                $r_num = 1;
                                while ($r_row = mysqli_fetch_assoc($recent_res)) {
                                    $r_status = strtolower($r_row['tbl_match_status'] ?? '');
                                    $is_win = (in_array($r_status, ['profit', 'win', 'won', 'cashout']));
                                    $is_loss = (in_array($r_status, ['loss', 'lost']));
                            ?>
                                    <tr>
                                        <td><span class="text-muted fw-bold"><?php echo $r_num++; ?></span></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($r_row['tbl_full_name'] ?? 'User #'.$r_row['tbl_user_id']); ?></div>
                                            <div class="small text-muted" style="font-size: 11px;">ID: <?php echo htmlspecialchars($r_row['tbl_user_id']); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-bold" style="color: var(--accent-blue);"><?php echo htmlspecialchars($r_row['tbl_project_name']); ?></span>
                                        </td>
                                        <td style="text-align: right;" class="fw-bold">&#8377;<?php echo number_format(floatval($r_row['tbl_match_cost']), 2); ?></td>
                                        <td style="text-align: right; color: #10b981;" class="fw-bold">&#8377;<?php echo number_format(floatval($r_row['tbl_match_profit']), 2); ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($is_loss): ?>
                                                <span class="badge-loss">&#9660; LOSS</span>
                                            <?php elseif ($is_win): ?>
                                                <span class="badge-profit">&#9650; WIN</span>
                                            <?php else: ?>
                                                <span class="badge-neutral"><?php echo strtoupper(htmlspecialchars($r_row['tbl_match_status'])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($r_row['tbl_time_stamp']); ?></td>
                                    </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No individual bet records found.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        function exportExcel(tableID, filename = '') {
            let downloadLink;
            let dataType = 'application/vnd.ms-excel';
            let tableSelect = document.getElementById(tableID);
            let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            filename = filename ? filename + '.xls' : 'excel_data.xls';
            downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            if (navigator.msSaveOrOpenBlob) {
                let blob = new Blob(['\ufeff' + tableHTML], { type: dataType });
                navigator.msSaveOrOpenBlob(blob, filename);
            } else {
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html>
