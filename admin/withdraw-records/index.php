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
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_withdraw") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
}

$f_username = mysqli_real_escape_string($conn, $_POST['f_username'] ?? $_GET['f_username'] ?? '');
$f_date_from = mysqli_real_escape_string($conn, $_POST['f_date_from'] ?? $_GET['f_date_from'] ?? '');
$f_date_to = mysqli_real_escape_string($conn, $_POST['f_date_to'] ?? $_GET['f_date_to'] ?? '');
$f_status = mysqli_real_escape_string($conn, $_POST['f_status'] ?? $_GET['f_status'] ?? 'pending');

$content = 25;
$page_num = (int) (isset($_GET['page_num']) ? $_GET['page_num'] : 1);
if ($page_num < 1)
    $page_num = 1;
$offset = ($page_num - 1) * $content;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Withdraw Records</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            overflow: hidden;
        }

        .cus-checkbox-group {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .cus-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-dim);
            font-size: 14px;
            font-weight: 600;
        }

        .cus-checkbox input {
            accent-color: var(--accent-blue);
            width: 18px;
            height: 18px;
        }

        /* Table Stylings */
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
        }

        .r-table tbody td {
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            background: var(--table-header-bg);
            border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }

        .r-table tbody td:first-child {
            border-radius: 12px 0 0 12px;
        }

        .r-table tbody td:last-child {
            border-radius: 0 12px 12px 0;
        }

        .r-table tr:hover td {
            background: var(--table-row-hover);
            color: var(--text-main);
            transform: scale(1.002);
            cursor: pointer;
        }

        .tag {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-block;
        }

        .tag-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-emerald);
        }

        .tag-danger {
            background: rgba(244, 63, 94, 0.1);
            color: var(--accent-rose);
        }

        .tag-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent-amber);
        }
        
        .tag-info {
            background: rgba(99, 102, 241, 0.1);
            color: var(--status-info);
        }

        .pagination-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 24px;
            padding: 0 10px;
        }

        .hide_view {
            display: none !important;
        }

        .advanced-filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid var(--border-dim);
            margin-bottom: 0;
        }

        .filter-grp {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-lbl {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-dim);
            letter-spacing: 0.5px;
            margin-left: 2px;
        }

        .filter-inp-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .filter-inp-box i {
            position: absolute;
            left: 10px;
            font-size: 13px;
            color: var(--accent-blue);
            opacity: 0.6;
        }

        .f-inp {
            width: 100%;
            height: 32px;
            background: rgba(0, 0, 0, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 6px !important;
            padding: 0 8px 0 30px !important;
            color: #fff !important;
            font-size: 11px !important;
        }

        .search-area {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        
        .btn-outline-modern { 
            background: var(--input-bg); 
            border: 1px solid var(--border-dim); 
            color: var(--text-main); 
        }
        .btn-outline-modern:hover { background: var(--table-row-hover); }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="back-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
                    <div>
                        <span class="dash-breadcrumb">Dashboard > Payments</span>
                        <h1 class="dash-title">Withdraw Records</h1>
                    </div>
                </div>
                <div class="dash-header-right">
                    <button class="btn-modern btn-outline-modern" onclick="exportPDF('withdraw-records', 'withdraw')"><i
                            class='bx bxs-file-pdf'></i> Export PDF</button>
                    <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'"><i
                            class='bx bx-refresh'></i> Refresh Data</button>
                </div>
            </div>

            <div style="padding: 10px 14px;">
                <div class="search-area">
                    <!-- Withdrawal Filter Bar -->
                    <form method="POST" class="advanced-filter-bar">
                        <div class="filter-grp">
                            <label class="filter-lbl">Username / ID</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-user'></i>
                                <input type="text" name="f_username" value="<?php echo $f_username; ?>" class="f-inp"
                                    placeholder="Search User...">
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
                            <label class="filter-lbl">Request Status</label>
                            <div class="filter-inp-box">
                                <i class='bx bx-list-check'></i>
                                <select name="f_status" class="f-inp" style="padding-left: 30px !important;">
                                    <option value="">All Records</option>
                                    <option value="pending" <?php if ($f_status == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="approve" <?php if ($f_status == 'approve') echo 'selected'; ?>>Approved</option>
                                    <option value="success" <?php if ($f_status == 'success') echo 'selected'; ?>>Success</option>
                                    <option value="rejected" <?php if ($f_status == 'rejected') echo 'selected'; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex align-items-end">
                            <button type="submit" class="btn-modern btn-primary-modern"
                                style="height: 32px; width: 100%; justify-content: center; font-size: 11px;">
                                <i class='bx bx-filter-alt'></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="padding: 0 14px;">
                <div class="d-flex align-items-center justify-content-between mb-2 mt-0">
                    <p style="font-size: 14px; font-weight: 700; color: var(--text-main); margin: 0;">
                        Withdrawal History
                    </p>
                </div>

                <table id="withdraw" class="r-table">
                    <thead>
                        <tr>
                            <th style="width: 60px">No</th>
                            <th style="width: 150px">User Id</th>
                            <th>Amount</th>
                            <th>Date & Time</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $indexVal = $offset + 1;

                        $where_clauses = ["1=1"];
                        if ($f_username != "") {
                            $where_clauses[] = "(r.tbl_user_id LIKE '%$f_username%' OR u.tbl_user_name LIKE '%$f_username%')";
                        }
                        if ($f_date_from != "" && $f_date_to != "") {
                            $where_clauses[] = "STR_TO_DATE(LEFT(r.tbl_time_stamp, 10), '%d-%m-%Y') BETWEEN '$f_date_from' AND '$f_date_to'";
                        }
                        if ($f_status != "") {
                            $where_clauses[] = "r.tbl_request_status = '{$f_status}'";
                        }

                        $where_str = implode(" AND ", $where_clauses);

                        $recharge_records_sql = "
                            SELECT r.*, u.tbl_user_name 
                            FROM tbluserswithdraw r
                            LEFT JOIN tblusersdata u ON r.tbl_user_id = u.tbl_uniq_id
                            WHERE $where_str 
                            ORDER BY r.id DESC 
                            LIMIT {$offset}, {$content}";

                        $recharge_records_result = mysqli_query($conn, $recharge_records_sql);

                        if ($recharge_records_result && mysqli_num_rows($recharge_records_result) > 0) {
                            while ($row = mysqli_fetch_assoc($recharge_records_result)) {
                                ?>
                                <tr onclick="window.location.href='manager.php?uniq-id=<?php echo $row['tbl_uniq_id']; ?>'">
                                    <td style="color: var(--text-dim);"><?php echo $indexVal; ?></td>
                                    <td style="font-weight: 600; color: var(--accent-blue);">
                                        <a href="../users-data/view-activities.php?user-id=<?php echo urlencode($row['tbl_user_id']); ?>"
                                            style="color: inherit; text-decoration: none;"
                                            onmouseover="this.style.textDecoration='underline'"
                                            onmouseout="this.style.textDecoration='none'">
                                            <?php echo htmlspecialchars($row['tbl_user_name'] ?? 'N/A'); ?>
                                        </a>
                                        <div style="font-size: 9px; color: var(--text-dim);">
                                            <?php echo htmlspecialchars($row['tbl_user_id']); ?></div>
                                    </td>
                                    <td style="color: var(--status-info); font-weight: 800;">
                                        ₹<?php echo number_format($row['tbl_withdraw_amount'], 2); ?></td>
                                    <td style="font-size: 12px;"><?php echo $row['tbl_time_stamp']; ?></td>
                                    <td style="text-align: center;">
                                        <?php
                                        $status = $row['tbl_request_status'];
                                        if($status == 'success') echo '<span class="tag tag-success">Success</span>';
                                        elseif($status == 'rejected') echo '<span class="tag tag-danger">Rejected</span>';
                                        elseif($status == 'pending') echo '<span class="tag tag-warning">Pending</span>';
                                        elseif($status == 'approve') echo '<span class="tag tag-info">Approved</span>';
                                        else echo '<span class="tag tag-dark">'.ucfirst($status).'</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php
                                $indexVal++;
                            }
                        } else { ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 60px; color: var(--text-dim);">
                                    <i class='bx bx-receipt'
                                        style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                    No withdrawal records found.
                                </td>
                            </tr>
                            <?php
                        } ?>
                    </tbody>
                </table>

                <?php
                $count_sql = "
                    SELECT count(*) as total 
                    FROM tbluserswithdraw r
                    LEFT JOIN tblusersdata u ON r.tbl_user_id = u.tbl_uniq_id
                    WHERE $where_str";
                $count_result = mysqli_query($conn, $count_sql);
                $count_row = mysqli_fetch_assoc($count_result);
                if ($count_row['total'] > 0) {
                    $total_records = (int) $count_row['total'];
                    $total_page = ceil($total_records / $content);
                    ?>
                    <div class="pagination-container">
                        <div class="d-flex align-items-center gap-3">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">Page <?php echo $page_num; ?> / <?php echo $total_page; ?></span>
                            <div class="d-flex gap-2">
                                <?php if ($page_num > 1): ?>
                                    <a href="?page_num=<?php echo $page_num - 1; ?><?php echo "&f_username=$f_username&f_date_from=$f_date_from&f_date_to=$f_date_to&f_status=$f_status"; ?>" class="btn-modern btn-outline-modern">
                                        <i class='bx bx-chevron-left'></i> Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page_num < $total_page): ?>
                                    <a href="?page_num=<?php echo $page_num + 1; ?><?php echo "&f_username=$f_username&f_date_from=$f_date_from&f_date_to=$f_date_to&f_status=$f_status"; ?>" class="btn-modern btn-outline-modern">
                                        Next <i class='bx bx-chevron-right'></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                } ?>
            </div>

        </div>
    </div>

    <script src="../script.js?v=1"></script>
</body>

</html>