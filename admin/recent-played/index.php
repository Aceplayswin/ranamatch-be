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

$f_username = mysqli_real_escape_string($conn, $_POST['f_username'] ?? $_GET['f_username'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');
$f_status = mysqli_real_escape_string($conn, $_POST['f_status'] ?? $_GET['f_status'] ?? '');

$content = 25;
$page_num = (int) (isset($_GET['page_num']) ? $_GET['page_num'] : 1);
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $content;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Recently Played</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

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
        }


        .text-muted {
            color: var(--text-dim) !important;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-bar {
            width: 3px;
            height: 16px;
            border-radius: 4px;
            background: linear-gradient(180deg, #3b82f6, #06b6d4);
            flex-shrink: 0;
        }

        .record-section {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 16px;
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
            color: var(--text-dim);
            padding: 0 16px 8px;
            border-bottom: 1px solid var(--border-dim);
        }

        .r-table tbody td {
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
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
            color: var(--text-main);
            border-color: var(--border-dim);
        }

        .advanced-filter-bar {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;
            background: rgba(255,255,255,0.02); padding: 12px 18px; border-radius: 12px;
            border: 1px solid var(--border-dim); margin-bottom: 0;
        }
        .filter-grp { display: flex; flex-direction: column; gap: 4px; }
        .filter-lbl { font-size: 8px; font-weight: 800; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-left: 2px; }
        .filter-inp-box { position: relative; display: flex; align-items: center; }
        .filter-inp-box i { position: absolute; left: 10px; font-size: 13px; color: var(--accent-blue); opacity: 0.6; }
        .f-inp {
            width:100%; height:32px; background: rgba(0,0,0,0.2) !important; border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius:6px !important; padding: 0 8px 0 30px !important; color:#fff !important; font-size:11px !important;
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

        .status-loss {
            background: rgba(244, 63, 94, 0.15);
            color: #f43f5e;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }
        
        .status-wait {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .status-cashout {
            background: rgba(168, 85, 247, 0.15);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.3);
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
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            border-radius: 10px;
            color: var(--text-dim);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-btn:hover {
            background: var(--table-row-hover);
            color: var(--text-main);
            border-color: var(--accent-blue);
        }

        .page-btn.active {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
        }

        .page-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-dim);
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
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
                        <span class="dash-breadcrumb">History & Records > Recently Played</span>
                        <span class="dash-title">Betting Historys</span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <button class="btn-modern btn-outline-modern" type="button" onclick="exportPDF('betting_history', 'betTable')">
                        <i class='bx bxs-file-pdf'></i> Export PDF
                    </button>
                    <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'">
                        <i class='bx bx-refresh'></i> Refresh Data
                    </button>
                </div>
            </div>

            <div style="padding: 10px 14px;">
                <div class="search-area" style="background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 12px; padding: 12px; margin-bottom: 16px;">
                    <!-- Micro Filter Bar -->
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
                            <button type="submit" class="btn-modern btn-primary-modern" style="height: 32px; width: 100%; justify-content: center; font-size: 11px; padding: 0 10px;">
                                <i class='bx bx-filter-alt'></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

            <div class="record-section" style="padding: 8px;">
                <div class="section-title">
                    <span class="title-bar"
                        style="background: linear-gradient(180deg, var(--accent-blue), #1d4ed8);"></span>
                    Real-Time Betting Logs
                </div>

                <table class="r-table" id="betTable">
                    <thead>
                        <tr>
                            <th width="80">No</th>
                            <th>User ID</th>
                            <th>Game Name</th>
                            <th>Bet Amount</th>
                            <th>Profit / Loss</th>
                            <th width="120">Status</th>
                            <th>Bet Time</th>
                            <th>Result Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $indexVal = 1;
                        
                        $where_clauses = ["1=1"];
                        if ($f_username != "") {
                            $where_clauses[] = "(m.tbl_user_id LIKE '%$f_username%' OR u.tbl_user_name LIKE '%$f_username%')";
                        }
                        if ($f_date_from != "" && $f_date_to != "") {
                            $where_clauses[] = "STR_TO_DATE(LEFT(m.tbl_time_stamp, 10), '%d-%m-%Y') BETWEEN '$f_date_from' AND '$f_date_to'";
                        }
                        if ($f_status != "") {
                            if ($f_status == 'WIN') {
                                $where_clauses[] = "(m.tbl_match_result = 'Win' OR m.tbl_match_status = 'Profit')";
                            } elseif ($f_status == 'PENDING') {
                                $where_clauses[] = "(m.tbl_match_status = 'Wait' OR m.tbl_match_status = 'Pending')";
                            } elseif ($f_status == 'LOSS') {
                                $where_clauses[] = "(m.tbl_match_status = 'Loss' OR m.tbl_match_result = 'Loss')";
                            }
                        }

                        $where_str = implode(" AND ", $where_clauses);
                        
                        $play_records_sql = "
                            SELECT m.*, u.tbl_user_name 
                            FROM tblmatchplayed m
                            LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id
                            WHERE $where_str 
                            ORDER BY m.tbl_updated_at DESC 
                            LIMIT $offset, $content";

                        $play_records_result = mysqli_query($conn, $play_records_sql) or die('Query execution failed');

                        if (mysqli_num_rows($play_records_result) > 0) {
                            while ($row = mysqli_fetch_assoc($play_records_result)) {
                                $match_status = strtolower($row['tbl_match_status']);
                                $is_profit = ($match_status == 'profit' || $match_status == 'won');
                                $is_wait = ($match_status == 'wait' || $match_status == 'pending');
                                ?>
                                <tr>
                                    <td style="color: var(--text-dim); font-size: 11px;"><?php echo $indexVal + $offset; ?></td>
                                    <td style="font-weight: 600; color: var(--accent-blue);">
                                        <a href="../users-data/view-activities.php?user-id=<?php echo urlencode($row['tbl_user_id']); ?>#match_section" style="color: inherit; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                            <?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                        </a>
                                        <div style="font-size: 9px; color: var(--text-dim);"><?php echo htmlspecialchars($row['tbl_user_id']); ?></div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div
                                                style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-blue);">
                                            </div>
                                            <?php echo htmlspecialchars($row['tbl_project_name']); ?>
                                        </div>
                                        <?php if(!empty($row['tbl_match_details'])) { ?>
                                            <div style="font-size: 10px; color: var(--text-dim); margin-left: 16px; margin-top: 2px;">
                                                <i class='bx bx-info-circle' style="font-size: 9px;"></i> <?php echo htmlspecialchars($row['tbl_match_details']); ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td style="font-weight: 800; color: var(--text-main);">
                                        ₹<?php echo number_format((float) $row['tbl_match_cost'], 2); ?></td>
                                    <td style="font-weight: 700; color: <?php echo $is_profit ? 'var(--accent-emerald)' : ($is_wait ? 'var(--text-dim)' : ($match_status == 'cashout' ? '#a855f7' : 'var(--accent-rose)')); ?>">
                                        <?php if ($match_status == 'loss' || $match_status == 'lost') {
                                            echo "-₹" . number_format((float)$row['tbl_match_cost'], 2);
                                        } else {
                                            echo "₹" . number_format((float)$row['tbl_match_profit'], 2);
                                        } ?>
                                        
                                        <?php if ($match_status == 'cashout') { 
                                            $net_loss = (float)$row['tbl_match_cost'] - (float)$row['tbl_match_profit'];
                                            if ($net_loss > 0) {
                                                echo "<br><span style='font-size: 10px; font-weight: 400; opacity: 0.8;'>(-₹".number_format($net_loss, 2).")</span>";
                                            }
                                        } ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $is_profit ? 'status-profit' : ($is_wait ? 'status-wait' : ($match_status == 'cashout' ? 'status-cashout' : 'status-loss')); ?>">
                                            <i class='bx <?php echo $is_profit ? 'bx-trending-up' : ($is_wait ? 'bx-time-five' : ($match_status == 'cashout' ? 'bx-money-withdraw' : 'bx-trending-down')); ?>'></i>
                                            <?php echo ucfirst($match_status); ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--text-dim); font-size: 11px; font-weight: 600; white-space: nowrap;">
                                        <?php echo htmlspecialchars($row['tbl_time_stamp']); ?>
                                    </td>
                                    <td style="color: var(--accent-emerald); font-size: 11px; font-weight: 600; white-space: nowrap;">
                                        <?php echo $row['tbl_result_time'] ? htmlspecialchars($row['tbl_result_time']) : '<span style="color:var(--text-dim)">Pending</span>'; ?>
                                    </td>
                                </tr>
                                <?php $indexVal++;
                            }
                        } else { ?>
                            <tr>
                                <td colspan="7" class="empty-state">No betting records found.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <?php
                $count_sql = "
                    SELECT count(*) as total 
                    FROM tblmatchplayed m
                    LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id
                    WHERE $where_str";
                $count_res = mysqli_query($conn, $count_sql);
                $count_row = mysqli_fetch_assoc($count_res);
                $total_records = (int) $count_row['total'];
                $total_page = ceil($total_records / $content);

                if ($total_records > 0) {
                    ?>
                    <div class="d-flex justify-content-between align-items-center mt-4" style="padding: 0 10px;">
                        <div style="font-size: 12px; color: var(--text-dim); font-weight: 600;">
                            Showing page <?php echo $page_num; ?> of <?php echo $total_page; ?> (Total:
                            <?php echo $total_records; ?> records)
                        </div>
                        <div class="pagination-container" style="margin: 0;">
                            <a href="index.php?page_num=<?php echo max(1, $page_num - 1); ?>"
                                class="page-btn <?php if ($page_num <= 1)
                                    echo 'disabled'; ?>">
                                <i class='bx bx-chevron-left'></i>
                            </a>

                            <?php
                            $start_p = max(1, $page_num - 2);
                            $end_p = min($total_page, $page_num + 2);
                            for ($i = $start_p; $i <= $end_p; $i++) {
                                $activeClass = ($page_num == $i) ? 'active' : '';
                                echo "<a href='index.php?page_num={$i}' class='page-btn {$activeClass}'>{$i}</a>";
                            }
                            ?>

                            <a href="index.php?page_num=<?php echo min($total_page, $page_num + 1); ?>"
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

    <script src="../script.js?v=03"></script>
</body>

</html>