<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); // Disable PHP's automatic session cache headers

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../logout-account');
}

$searched = "";
if (isset($_POST['submit'])) {
    $searched = mysqli_real_escape_string($conn, $_POST['searchinp']);
}

$f_username = mysqli_real_escape_string($conn, $_POST['f_username'] ?? $_GET['f_username'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');
$f_status = mysqli_real_escape_string($conn, $_POST['f_status'] ?? $_GET['f_status'] ?? '');

$content = 25;
$page_num = (int) (isset($_GET['page_num']) ? $_GET['page_num'] : 1);
if ($page_num < 1)
    $page_num = 1;
$offset = ($page_num - 1) * $content;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Sports Bet History</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        <?php include "../components/theme-variables.php"; ?>
    </style>
    <style>
        /* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }

        .dash-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .dash-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }


        .dash-breadcrumb {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 4px;
        }

        .dash-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-main);
            line-height: 1.2;
            display: block;
        }

        .search-area {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }

        .advanced-filter-bar {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;
            background: rgba(255,255,255,0.02); padding: 12px 18px; border-radius: 12px;
            border: 1px solid var(--border-dim); margin-bottom: 20px;
        }
        .filter-grp { display: flex; flex-direction: column; gap: 4px; }
        .filter-lbl { font-size: 8px; font-weight: 800; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-left: 2px; }
        .filter-inp-box { position: relative; display: flex; align-items: center; }
        .filter-inp-box i { position: absolute; left: 10px; font-size: 13px; color: var(--accent-blue); opacity: 0.6; }
        .f-inp {
            width:100%; height:32px; background: rgba(0,0,0,0.2) !important; border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius:6px !important; padding: 0 8px 0 30px !important; color:#fff !important; font-size:11px !important;
        }

        .btn-modern {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }

        .btn-outline-modern {
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            color: var(--text-dim);
        }

        .btn-outline-modern:hover {
            background: var(--table-row-hover);
            color: var(--text-main);
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-bar {
            width: 4px;
            height: 20px;
            border-radius: 4px;
            background: linear-gradient(180deg, #3b82f6, #06b6d4);
            flex-shrink: 0;
        }

        .record-section {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
            margin-bottom: 32px;
        }

        .r-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .r-table thead th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #94a3b8;
            padding: 0 16px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .r-table tbody td {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            background: var(--table-header-bg);
            border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }

        .r-table tbody td:first-child {
            border-radius: 12px 0 0 12px;
            border-left: 1px solid var(--border-dim);
        }

        .r-table tbody td:last-child {
            border-radius: 0 12px 12px 0;
            border-right: 1px solid var(--border-dim);
        }

        .r-table tr:hover td {
            background: var(--table-row-hover);
            color: var(--text-main);
            border-color: var(--accent-blue);
        }

        /* Aggressive Column Widths & Wrapping Fixes */
        .r-table {
            table-layout: fixed !important;
            min-width: 1400px !important;
        }

        .col-no {
            width: 50px;
        }

        .col-user {
            width: 140px;
        }

        .col-betid {
            width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .col-sport {
            width: 110px;
        }

        .col-details {
            width: 380px;
            white-space: normal !important;
            word-break: break-word !important;
        }

        .col-details-text {
            font-size: 11px;
            color: var(--text-main);
            line-height: 1.4;
            white-space: normal !important;
        }

        .col-odds {
            width: 80px;
            text-align: center;
        }

        .col-stake {
            width: 110px;
        }

        .col-pl {
            width: 110px;
        }

        .col-status {
            width: 110px;
        }

        .col-result {
            width: 100px;
        }

        .col-time {
            width: 150px;
            white-space: nowrap;
        }

        .r-table tbody td {
            vertical-align: top;
            /* Changed to top for better multi-line text alignment */
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-profit {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.status-loss { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .status-badge.status-tie { background: rgba(255, 255, 255, 0.05); color: var(--text-dim); border: 1px solid var(--border-dim); }
        .status-badge.status-cashout { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); }

        .status-wait {
            background: rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .status-tie {
            background: rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pagination-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
            gap: 8px;
        }

        .page-btn {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-dim);
            border-radius: 10px;
            color: var(--text-dim);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-btn:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #ffffff;
            border-color: var(--accent-blue);
        }

        .page-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            border-color: var(--accent-blue);
        }

        .page-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }

        .text-muted {
            color: #94a3b8 !important;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <div class="dash-header">
                <div class="dash-header-left">
                    <div>
                        <span class="dash-breadcrumb">History & Records > Sports Bet History</span>
                        <span class="dash-title">Sports Betting Logs</span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <button class="btn-modern btn-outline-modern" type="button" onclick="exportExcel('table', 'Sports-Bet-History.xlsx')">
                        <i class='bx bx-file'></i> Export Excel
                    </button>
                    <button class="btn-modern btn-outline-modern" type="button" onclick="exportPDF('sports-bet-history', 'table')">
                        <i class='bx bxs-file-pdf'></i> Export PDF
                    </button>
                    <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'">
                        <i class='bx bx-refresh'></i> Refresh Data
                    </button>
                </div>
            </div>

            <div style="padding: 10px 14px;">
                <div class="search-area">
                    <!-- Sports Filter Bar -->
                    <form method="POST" class="advanced-filter-bar">
                        <div class="filter-grp">
                            <label class="filter-lbl">Username / ID</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-user'></i>
                                <input type="text" name="f_username" value="<?php echo $f_username; ?>" class="f-inp" placeholder="Search User...">
                            </div>
                        </div>
                        <div class="filter-grp">
                            <label class="filter-lbl">From Date</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-calendar'></i>
                                <input type="date" name="f_date_from" value="<?php echo $f_date_from; ?>" class="f-inp">
                            </div>
                        </div>
                        <div class="filter-grp">
                            <label class="filter-lbl">To Date</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-calendar-event'></i>
                                <input type="date" name="f_date_to" value="<?php echo $f_date_to; ?>" class="f-inp">
                            </div>
                        </div>
                        <div class="filter-grp">
                            <label class="filter-lbl">Trans Status</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-list-check'></i>
                                <select name="f_status" class="f-inp" style="padding-left: 30px !important;">
                                    <option value="">All Status</option>
                                    <option value="WIN" <?php if ($f_status == 'WIN') echo 'selected'; ?>>WIN Only</option>
                                    <option value="PENDING" <?php if ($f_status == 'PENDING') echo 'selected'; ?>>PENDING Only</option>
                                    <option value="LOSS" <?php if ($f_status == 'LOSS') echo 'selected'; ?>>LOSS Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex align-items-end">
                            <button type="submit" class="btn-modern btn-primary-modern" style="height: 32px; width: 100%; justify-content: center; font-size: 11px;">
                                <i class='bx bx-filter-alt'></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <div class="record-section">
                    <div class="section-title">
                        <span class="title-bar"></span>
                        Sports Transaction History
                    </div>

                    <div class="w-100 ovflw-x-scroll hide-native-scrollbar">
                        <table id="table" class="r-table">
                            <thead>
                                <tr>
                                    <th class="col-no">No</th>
                                    <th class="col-user">Username & ID</th>
                                    <th class="col-betid">Bet ID</th>
                                    <th class="col-sport">Sport</th>
                                    <th class="col-details">Event Details & Selection</th>
                                    <th class="col-odds">Odds</th>
                                    <th class="col-stake">Stake</th>
                                    <th class="col-pl">Profit/Loss</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-result">Result</th>
                                    <th class="col-time">Bet Time</th>
                                    <th class="col-time">Result Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $indexVal = 1;
                                $footer_total_pl = 0;
                                $footer_profit_amount = 0;
                                $footer_loss_amount = 0;

                                $where_clauses = ["(LOWER(m.tbl_project_name) LIKE '%saba%' OR LOWER(m.tbl_project_name) LIKE '%luck%sports%' OR LOWER(m.tbl_project_name) LIKE '%wickets%' OR LOWER(m.tbl_project_name) LIKE '%esports%')"];

                                if ($f_username != "") {
                                    $where_clauses[] = "(m.tbl_user_id LIKE '%$f_username%' OR u.tbl_user_name LIKE '%$f_username%')";
                                }

                                if ($f_date_from != "" && $f_date_to != "") {
                                    $where_clauses[] = "STR_TO_DATE(LEFT(m.tbl_time_stamp, 10), '%d-%m-%Y') BETWEEN '$f_date_from' AND '$f_date_to'";
                                }

                                if ($f_status != "") {
                                    if ($f_status == 'WIN') {
                                        $where_clauses[] = "(m.tbl_match_result = 'Win' OR m.tbl_match_status = 'Profit' OR m.tbl_match_result = 'Won')";
                                    } elseif ($f_status == 'PENDING') {
                                        $where_clauses[] = "(m.tbl_match_status = 'Wait' OR m.tbl_match_result = 'Pending')";
                                    } elseif ($f_status == 'LOSS') {
                                        $where_clauses[] = "(m.tbl_match_status = 'Loss' OR m.tbl_match_result = 'Loss' OR m.tbl_match_result = 'Lost')";
                                    }
                                }

                                $where_str = implode(" AND ", $where_clauses);
                                $play_records_sql = "
                                    SELECT m.*, u.tbl_user_name 
                                    FROM tblmatchplayed m 
                                    LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id 
                                    WHERE $where_str
                                    ORDER BY m.tbl_updated_at DESC 
                                    LIMIT {$offset}, {$content}";

                                $play_records_result = mysqli_query($conn, $play_records_sql);

                                if (mysqli_num_rows($play_records_result) > 0) {
                                    while ($row = mysqli_fetch_assoc($play_records_result)) {
                                        $match_status_lower = strtolower($row['tbl_match_status'] ?? '');
                                        $match_result_lower = strtolower($row['tbl_match_result'] ?? '');
                                        
                                        $stake = floatval($row['tbl_match_cost']);
                                        $profit_loss = floatval($row['tbl_match_profit']);
                                        $username = $row['tbl_user_name'] ? $row['tbl_user_name'] : 'N/A';
                                        $user_id = $row['tbl_user_id'];
                                        $bet_id = $row['tbl_uniq_id'];
                                        $category = $row['tbl_project_name'];

                                        // Mapping potential column names (as per discovered schema)
                                        $details = isset($row['tbl_match_details']) ? $row['tbl_match_details'] : 'N/A';
                                        $bet_type = isset($row['tbl_bet_type']) ? $row['tbl_bet_type'] : 'Back';
                                        $odds = isset($row['tbl_odds']) ? $row['tbl_odds'] : 'N/A';
                                        $match_status = $row['tbl_match_status'];
                                        $game_result = $row['tbl_match_result'] ? $row['tbl_match_result'] : 'Pending';
                                        
                                        $is_profit = ($match_status_lower === 'profit' || $match_result_lower === 'won' || $match_result_lower === 'win');
                                        $is_loss = ($match_status_lower === 'loss' || $match_result_lower === 'lost' || $match_result_lower === 'loss');
                                        $is_pending = ($match_status_lower === 'wait' || $match_result_lower === 'pending');
                                        $is_tie = ($match_status_lower === 'tie' || $match_result_lower === 'tie' || $match_result_lower === 'draw');
                                        $is_cashout = ($match_result_lower === 'cashout' || $match_status_lower === 'cashout');

                                        if ($is_profit) {
                                            $footer_profit_amount += $profit_loss;
                                        } else if ($is_loss) {
                                            $footer_loss_amount += $stake;
                                        }
                                        $footer_total_pl += ($is_profit ? $profit_loss : ($is_loss ? -$stake : 0));

                                        $status_class = "status-wait";
                                        if ($is_profit)
                                            $status_class = "status-profit";
                                        else if ($is_loss)
                                            $status_class = "status-loss";
                                        else if ($is_tie)
                                            $status_class = "status-tie";

                                        $pl_color = "var(--text-dim)";
                                        if ($is_profit)
                                            $pl_color = "var(--accent-emerald)";
                                        else if ($is_loss)
                                            $pl_color = "var(--accent-rose)";
                                        ?>
                                        <tr>
                                            <td class="col-no" style="font-size: 11px; color: var(--text-dim);">
                                                <?php echo $indexVal + $offset; ?></td>
                                            <td class="col-user">
                                                <div style="font-weight: 700; color: var(--text-main);">
                                                    <a href="../users-data/view-activities.php?user-id=<?php echo urlencode($user_id); ?>#sports_section" style="color: inherit; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                                        <?php echo htmlspecialchars($username); ?>
                                                    </a>
                                                </div>
                                                <div style="font-size: 10px; color: var(--accent-blue); opacity: 0.8;">
                                                    <?php echo htmlspecialchars($user_id); ?></div>
                                            </td>
                                            <td class="col-betid"
                                                style="font-family: monospace; font-size: 11px; color: var(--accent-blue); cursor: pointer; text-decoration: underline;" onclick="ShowRoundDetails('<?php echo $bet_id; ?>', 'sports')">
                                                <?php echo htmlspecialchars($bet_id); ?></td>
                                            <td class="col-sport" style="font-weight: 600; color: var(--accent-amber);">
                                                <?php echo htmlspecialchars($category); ?></td>
                                            <td class="col-details">
                                                <div class="col-details-text"><?php echo htmlspecialchars($details); ?></div>
                                                <div
                                                    style="font-size: 10px; font-weight: 700; color: <?php echo ($bet_type == 'Lay') ? 'var(--accent-rose)' : 'var(--accent-blue)'; ?>; margin-top: 2px;">
                                                    <?php echo htmlspecialchars($bet_type); ?>
                                                </div>
                                            </td>
                                            <td class="col-odds" style="font-weight: 700; color: var(--text-main);">
                                                <?php echo $odds; ?></td>
                                            <td class="col-stake" style="font-weight: 600;">
                                                ₹<?php echo number_format($stake, 2); ?></td>
                                            <td class="col-pl" style="font-weight: 700; color: <?php echo $is_cashout ? '#a78bfa' : $pl_color; ?>;">
                                                ₹<?php echo number_format($is_loss ? -$stake : $profit_loss, 2); ?>
                                                <?php if($is_cashout): ?>
                                                    <div style="font-size: 10px; opacity: 0.8;">(-₹<?php echo number_format(max(0, $stake - $profit_loss), 2); ?>)</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-status">
                                                <span class="status-badge <?php echo $is_cashout ? 'status-cashout' : $status_class; ?>">
                                                    <?php echo $is_cashout ? "<i class='bx bxs-wallet' style='font-size:10px'></i> CASHOUT" : ucfirst($match_status); ?>
                                                </span>
                                            </td>
                                            <td class="col-result"
                                                style="font-weight: 600; font-size: 12px; color: <?php echo ($game_result == 'Pending') ? 'var(--text-dim)' : 'var(--text-main)'; ?>;">
                                                <?php echo ucfirst($game_result); ?>
                                            </td>
                                            <td class="col-time" style="font-size: 11px;"><?php echo htmlspecialchars($row['tbl_time_stamp']); ?></td>
                                            <td class="col-time" style="font-size: 11px; color: var(--accent-emerald);">
                                                <?php echo $row['tbl_result_time'] ? htmlspecialchars($row['tbl_result_time']) : '<span style="color:var(--text-dim)">Pending</span>'; ?>
                                            </td>
                                        </tr>
                                        <?php $indexVal++;
                                    }
                                } else {
                                    echo "<tr><td colspan='11' class='text-center py-5 text-muted'>No sports betting records found</td></tr>";
                                } ?>
                            </tbody>
                            <?php if ($indexVal > 1) { ?>
                                <tfoot style="background: var(--table-header-bg);">
                                    <tr style="font-weight: 700;">
                                        <td colspan="12">
                                            <div style="display: flex; align-items: center; gap: 30px; padding: 6px 0;">
                                                <div
                                                    style="font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">
                                                    Page Totals:</div>
                                                <div
                                                    style="font-size: 13px; color: <?php echo $footer_total_pl >= 0 ? 'var(--accent-emerald)' : 'var(--accent-rose)'; ?>;">
                                                    P/L: ₹<?php echo number_format($footer_total_pl, 2); ?></div>
                                                <div style="font-size: 13px; color: var(--accent-emerald);">Profit: +
                                                    ₹<?php echo number_format($footer_profit_amount, 2); ?></div>
                                                <div style="font-size: 13px; color: var(--accent-rose);">Loss: -
                                                    ₹<?php echo number_format(abs($footer_loss_amount), 2); ?></div>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            <?php } ?>
                        </table>
                    </div>

                    <?php
                    $count_sql = "
                        SELECT COUNT(*) as total 
                        FROM tblmatchplayed m 
                        LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id 
                        WHERE $where_str";
                    $count_result = mysqli_query($conn, $count_sql);
                    $count_row = mysqli_fetch_assoc($count_result);
                    $total_records = (int) $count_row['total'];
                    $total_page = ceil($total_records / $content);

                    if ($total_records > 0) {
                        ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div style="font-size: 12px; color: var(--text-dim); font-weight: 600;">
                                Showing page <?php echo $page_num; ?> of <?php echo $total_page; ?>
                                (<?php echo $total_records; ?> records)
                            </div>
                            <div class="pagination-container">
                                <a href="?page_num=<?php echo max(1, $page_num - 1); ?>"
                                    class="page-btn <?php if ($page_num <= 1)
                                        echo 'disabled'; ?>">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                                <?php
                                $sp = max(1, $page_num - 2);
                                $ep = min($total_page, $page_num + 2);
                                for ($i = $sp; $i <= $ep; $i++) {
                                    $act = ($page_num == $i) ? 'active' : '';
                                    echo "<a href='?page_num={$i}' class='page-btn {$act}'>{$i}</a>";
                                }
                                ?>
                                <a href="?page_num=<?php echo min($total_page, $page_num + 1); ?>"
                                    class="page-btn <?php if ($page_num >= $total_page)
                                        echo 'disabled'; ?>">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script.js?v=05"></script>
    <script>
        function ShowRoundDetails(id, type) {
            Swal.fire({
                title: 'Fetching intelligence...',
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(`../reports/get-bet-details.php?id=${id}&type=${type}&t=${new Date().getTime()}`)
                .then(response => response.json())
                .then(data => {
                    if(data.error) return Swal.fire('Data Error', data.error, 'error');

                    const isProfit = (data.tbl_match_status.toLowerCase() === 'profit' || data.tbl_match_result.toLowerCase() === 'win' || data.tbl_match_result.toLowerCase() === 'won');
                    const isLoss = (data.tbl_match_status.toLowerCase() === 'loss' || data.tbl_match_result.toLowerCase() === 'loss' || data.tbl_match_result.toLowerCase() === 'lost');
                    const isPending = (data.tbl_match_status.toLowerCase() === 'wait' || data.tbl_match_result.toLowerCase() === 'pending');
                    const isCashout = (data.tbl_match_result.toLowerCase().includes('cashout') || data.tbl_match_status.toLowerCase().includes('cashout'));
                    const netAmount = parseFloat(data.tbl_match_profit) - parseFloat(data.tbl_match_cost);
                    
                    const statusColorClass = isProfit ? 'text-success' : (isPending ? 'text-warning' : (netAmount === 0 ? 'text-white' : 'text-danger'));

                    const html = `
                    <div class="round-details-v2" style="background: #121212; padding: 0px; font-family: 'DM Sans', sans-serif; color: #fff;">
                        <!-- HEADER SECTION -->
                        <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px;">
                            <div style="font-size: 18px; font-weight: 900; color: white; display: flex; align-items: center; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--accent-amber); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: black; flex-shrink: 0;">
                                    <i class='bx bxs-wallet'></i>
                                </div>
                                <span>ROUND DETAILS (${data.tbl_project_name.toUpperCase()})</span>
                            </div>
                            <div style="font-size: 10px; color: #64748b; font-weight: 700; margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; margin-left: 44px;">
                                TRANSACTION & SETTLEMENT REPORT
                            </div>
                        </div>

                        <div class="row g-4" style="padding: 0 25px 25px 25px;">
                            <!-- ROUND DETAILS -->
                            <div class="col-md-4">
                                <div class="panel-header">
                                    <i class='bx bx-info-circle text-warning'></i> ROUND DETAILS
                                </div>
                                <div class="panel-content">
                                    <div class="data-row"><i class='bx bx-circle'></i> <span class="lbl">PROVIDER</span> <span class="val">${data.tbl_project_name}</span></div>
                                    <div class="data-row"><i class='bx bx-hash'></i> <span class="lbl">ROUND ID</span> <span class="val" style="font-size: 10px; color: #aaa; word-break: break-all; white-space: normal; height: auto;">${data.tbl_period_id || data.tbl_uniq_id}</span></div>
                                    <div class="data-row"><i class='bx bx-play-circle'></i> <span class="lbl">GAME TYPE</span> <span class="val">${data.tbl_project_name}</span></div>
                                    <div class="data-row"><i class='bx bx-info-circle'></i> <span class="lbl">MATCH DETAILS</span> <span class="val" style="word-break: break-word; white-space: normal; height: auto;">${data.tbl_match_details || data.tbl_project_name}</span></div>
                                    <div class="data-row"><i class='bx bx-target-lock'></i> <span class="lbl">SELECTION</span> <span class="val" style="word-break: break-word; white-space: normal; height: auto; color: #3b82f6;">${data.tbl_bet_type || data.tbl_selection || '-'}</span></div>
                                    <div class="data-row"><i class='bx bx-calendar'></i> <span class="lbl">BET PLACED AT</span> <span class="val">${data.tbl_time_stamp}</span></div>
                                    <div class="data-row"><i class='bx bx-time'></i> <span class="lbl">SETTLED AT</span> <span class="val">${data.tbl_updated_at}</span></div>
                                    <div class="data-row"><i class='bx bx-check-circle'></i> <span class="lbl">STATUS</span> <span class="val text-uppercase ${statusColorClass}">${netAmount === 0 ? 'TIE / REFUND' : ((data.tbl_match_result == '0' || data.tbl_match_status.toLowerCase() == 'loss') ? 'LOSS' : (data.tbl_match_result == '1' || data.tbl_match_status.toLowerCase() == 'profit' ? 'WON' : (data.tbl_match_result || data.tbl_match_status)))}</span></div>
                                    <div class="data-row"><i class='bx bx-hash'></i> <span class="lbl">BETS TRANSACTIONID</span> <span class="val" style="font-size: 9px; word-break: break-all; white-space: normal; height: auto;">${data.tbl_uniq_id}</span></div>
                                    <div class="data-row"><i class='bx bx-play-circle'></i> <span class="lbl">BETS PLAYMODE</span> <span class="val">RealMoney</span></div>
                                    <div class="data-row"><i class='bx bx-devices'></i> <span class="lbl">BETS CHANNEL</span> <span class="val">Desktop/Mobile</span></div>
                                </div>
                            </div>

                            <!-- BET DETAILS -->
                            <div class="col-md-4">
                                <div class="panel-header">
                                    <i class='bx bx-dollar-circle text-warning'></i> BET DETAILS
                                </div>
                                <div class="panel-content bg-panel">
                                    <div class="data-row border-b"><i class='bx bx-target-lock'></i> <span class="lbl">CHOICE / SELECTION</span> <span class="val" style="word-break: break-word; white-space: normal; height: auto; color: #3b82f6;">${data.tbl_bet_type || data.tbl_selection || '-'}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-dollar'></i> <span class="lbl">STAKE</span> <span class="val">₹${parseFloat(data.tbl_match_cost).toFixed(2)}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-trophy'></i> <span class="lbl">PAYOUT</span> <span class="val">₹${parseFloat(data.tbl_match_profit).toFixed(2)}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-trending-up'></i> <span class="lbl">ODDS</span> <span class="val" style="color: #fbbf24; font-weight: 800;">${data.tbl_odds || '-'}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-calendar'></i> <span class="lbl">BET TIME</span> <span class="val">${data.tbl_time_stamp}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-info-circle'></i> <span class="lbl">DESCRIPTION</span> <span class="val">${data.tbl_project_name}</span></div>
                                    <div class="data-row"><i class='bx bx-hash'></i> <span class="lbl">TRANSACTIONID</span> <span class="val" style="font-size: 9px;">${data.tbl_uniq_id}</span></div>
                                </div>
                            </div>

                            <!-- RESULTS -->
                            <div class="col-md-4">
                                <div class="panel-header">
                                    <i class='bx bx-trophy text-warning'></i> RESULTS
                                </div>
                                <div class="panel-content bg-panel" style="min-height: 250px; display: flex; flex-direction: column;">
                                    <div class="data-row border-b"><i class='bx bx-hash'></i> <span class="lbl">OUTCOME NUMBER</span> <span class="val">-</span></div>
                                    <div class="data-row border-b"><i class='bx bx-info-circle'></i> <span class="lbl">OUTCOME TYPE</span> <span class="val">-</span></div>
                                    <div class="data-row border-b"><i class='bx bx-info-circle'></i> <span class="lbl">OUTCOME COLOR</span> <span class="val">-</span></div>
                                    <div class="data-row border-b"><i class='bx bx-hash text-warning'></i> <span class="lbl">TOTAL WAGER</span> <span class="val">₹${parseFloat(data.tbl_match_cost).toFixed(2)}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-download text-success'></i> <span class="lbl">AMOUNT RECEIVED</span> <span class="val">₹${parseFloat(data.tbl_match_profit).toFixed(2)}</span></div>
                                    <div class="data-row border-b"><i class='bx bx-trending-down text-danger'></i> <span class="lbl">AMOUNT LOST</span> <span class="val">₹${Math.max(0, parseFloat(data.tbl_match_cost) - parseFloat(data.tbl_match_profit)).toFixed(2)}</span></div>
                                    
                                    <div style="margin-top: auto; padding: 20px 0;">
                                        ${isCashout ? `
                                            <div class="cashout-box">
                                                <div class="cashout-lbl">CASHOUT PAYOUT</div>
                                                <div class="cashout-val">₹${parseFloat(data.tbl_match_profit).toFixed(2)}</div>
                                                <div class="cashout-net-loss">NET LOSS: -₹${Math.abs(netAmount).toFixed(2)}</div>
                                            </div>
                                        ` : (isPending ? `
                                            <div class="net-badge" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                                <span class="net-lbl" style="color: #94a3b8;">STATUS:</span>
                                                <span class="net-val" style="color: #fbbf24;">WAITING FOR RESULT</span>
                                            </div>
                                        ` : (netAmount === 0 ? `
                                            <div class="net-badge" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                                <span class="net-lbl" style="color: #94a3b8;">RESULT:</span>
                                                <span class="net-val" style="color: #ffffff;">TIE / REFUND</span>
                                            </div>
                                        ` : `
                                            <div class="net-badge ${isProfit ? 'bg-profit' : 'bg-loss'}">
                                                <span class="net-lbl">${isProfit ? 'NET PROFIT' : 'NET LOSS'}:</span>
                                                <span class="net-val">${isProfit ? '+' : '-'}₹${Math.abs(netAmount).toFixed(2)}</span>
                                            </div>
                                        `))}
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <style>
                            .round-details-v2 .panel-header { font-family: 'Archivo Black', sans-serif; font-size: 13px; letter-spacing: 1px; color: #fff; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 2px solid #333; display: flex; align-items: center; gap: 8px; }
                            .round-details-v2 .panel-content { display: flex; flex-direction: column; gap: 0px; }
                            .round-details-v2 .bg-panel { background: #1a1a1a; padding: 12px; border-radius: 12px; }
                            .round-details-v2 .data-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
                            .round-details-v2 .data-row i { font-size: 13px; color: #555; width: 16px; }
                            .round-details-v2 .lbl { font-size: 9px; font-weight: 800; color: #888; letter-spacing: 0.5px; flex-shrink: 0; width: 120px; }
                            .round-details-v2 .val { font-size: 11px; font-weight: 700; color: #fff; text-align: right; margin-left: auto; word-break: break-all; line-height: 1.4; }
                            .round-details-v2 .border-b { border-bottom: 1px solid #222; }
                            .round-details-v2 .net-badge { display: flex; align-items: center; justify-content: center; gap: 15px; padding: 10px 20px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
                            .round-details-v2 .bg-loss { background: linear-gradient(135deg, #2c1619 0%, #1a1a1a 100%); border-color: rgba(244, 63, 94, 0.2); }
                            .round-details-v2 .bg-profit { background: linear-gradient(135deg, #162c21 0%, #1a1a1a 100%); border-color: rgba(16, 185, 129, 0.2); }
                            .round-details-v2 .net-lbl { font-size: 10px; font-weight: 900; color: #f43f5e; letter-spacing: 1px; }
                            .round-details-v2 .bg-profit .net-lbl { color: #10b981; }
                            .round-details-v2 .net-val { font-size: 20px; font-weight: 900; color: #f43f5e; font-family: 'Archivo Black', sans-serif; }
                            .round-details-v2 .bg-profit .net-val { color: #10b981; }
                            
                            /* CASHOUT SPECIFIC STYLES */
                            .round-details-v2 .cashout-box { background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 40px; padding: 30px 20px; text-align: center; }
                            .round-details-v2 .cashout-lbl { font-size: 11px; font-weight: 900; color: #a78bfa; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; opacity: 0.8; }
                            .round-details-v2 .cashout-val { font-size: 56px; font-weight: 900; color: #a78bfa; font-family: 'Archivo Black', sans-serif; line-height: 1; margin-bottom: 15px; }
                            .round-details-v2 .cashout-net-loss { display: inline-block; background: #e11d48; color: #fff; font-size: 11px; font-weight: 900; padding: 6px 20px; border-radius: 50px; letter-spacing: 0.5px; text-transform: uppercase; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4); }
                        </style>
                    </div>
                    `;

                    Swal.fire({
                        html: html,
                        width: '1200px',
                        background: '#111',
                        showConfirmButton: false,
                        showCloseButton: true,
                        padding: '0',
                        customClass: {
                            popup: 'round-details-v2-popup',
                            container: 'round-details-popup'
                        },
                        didOpen: () => {
                            const popup = Swal.getPopup();
                            if(popup) popup.style.borderRadius = '24px';
                        }
                    });
                })
                .catch(err => {
                    Swal.fire('Fetch Error', 'Could not communicate with the server: ' + err.message, 'error');
                });
        }

        function exportExcel(tableID, filename = '') {
            const table = document.getElementById(tableID);
            const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet 1" });
            return XLSX.writeFile(wb, filename || 'Export.xlsx');
        }
    </script>
</body>

</html>