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
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_users_data") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
}

$content = 15;
if (isset($_GET['page_num'])) {
    $page_num = $_GET['page_num'];
    $offset = ($page_num - 1) * $content;
} else {
    $page_num = 1;
    $offset = ($page_num - 1) * $content;
}

// Default to showing active accounts
$accountStatus = "true";
if (isset($_POST['order_type'])) {
    $accountStatus = $_POST['order_type'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Top User Balances</title>
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

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px 12px;
            border-bottom: 1px solid var(--border-dim);
            margin-bottom: 15px;
        }

        .dash-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-4px);
        }

        .dash-breadcrumb {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--status-info);
        }

        .dash-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
        }

        .actions-bar {
            padding: 24px 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .btn-modern {
            height: 38px;
            padding: 0 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary-modern {
            background: var(--accent-blue);
            color: #fff;
        }

        .btn-primary-modern:hover {
            background: #2563eb;
            transform: translateY(-2px);
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

        /* Filter Popover */
        .filter-options {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 16px;
            margin: 0 20px 16px;
        }

        .cus-checkbox-group {
            display: flex;
            gap: 24px;
            align-items: center;
            margin-bottom: 16px;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .r-table tbody td {
            padding: 10px 16px;
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

        /* Rank Badges */
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-dim);
            color: var(--text-dim);
        }

        .rank-1 {
            background: linear-gradient(135deg, #fbbf24, #d97706) !important;
            border: none !important;
            color: #451a03 !important;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.4);
        }

        .rank-2 {
            background: linear-gradient(135deg, #94a3b8, #475569) !important;
            border: none !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(148, 163, 184, 0.3);
        }

        .rank-3 {
            background: linear-gradient(135deg, #b45309, #78350f) !important;
            border: none !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(180, 83, 9, 0.3);
        }

        .tag {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tag-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-emerald);
        }

        .tag-danger {
            background: rgba(244, 63, 94, 0.1);
            color: var(--accent-rose);
        }

        .tag-dark {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-dim);
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding: 0 10px;
        }

        .page-info {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hide_view {
            display: none !important;
        }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <!-- Header -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="back-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
                    <div>
                        <span class="dash-breadcrumb">Analytics > Wealth Management</span>
                        <h1 class="dash-title">Top User Balances</h1>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-modern btn-outline-modern filter_btn"><i class='bx bx-filter'></i> Advanced
                        Filter</button>
                    <button class="btn-modern btn-primary-modern" onclick="exportPDF('top-balances', 'table')"><i
                            class='bx bxs-file-pdf'></i> Export Data</button>
                </div>
            </div>

            <!-- Filter Area -->
            <div class="filter-options hide_view">
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <p
                        style="font-size: 11px; font-weight: 800; color: var(--accent-blue); text-transform: uppercase; margin-bottom: 16px; letter-spacing: 1px;">
                        Account Status Filter</p>
                    <div class="cus-checkbox-group">
                        <label class="cus-checkbox">
                            <input type="checkbox" name="order_type" value="true" <?php if ($accountStatus == "true") {
                                echo 'checked';
                            } ?>>
                            <span>Show Active Players</span>
                        </label>
                        <label class="cus-checkbox">
                            <input type="checkbox" name="order_type" value="ban" <?php if ($accountStatus == "ban") {
                                echo 'checked';
                            } ?>>
                            <span>Show Banned Players</span>
                        </label>
                        <label class="cus-checkbox">
                            <input type="checkbox" name="order_type" value="false" <?php if ($accountStatus == "false") {
                                echo 'checked';
                            } ?>>
                            <span>Show In-Active Players</span>
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn-modern btn-primary-modern"
                            style="height: 40px; font-size: 12px;">Apply Changes</button>
                        <button type="button" class="btn-modern btn-outline-modern filter_btn"
                            style="height: 40px; font-size: 12px;">Cancel</button>
                    </div>
                </form>
            </div>

            <div style="padding: 0 20px;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <p style="font-size: 14px; font-weight: 700; color: var(--text-main); margin: 0;">Ranking Overview
                    </p>
                    <a class="btn-modern btn-outline-modern" onclick="window.location.reload()"
                        style="height: 32px; font-size: 11px; padding: 0 12px;">
                        <i class='bx bx-refresh'></i> Refresh Feed
                    </a>
                </div>

                <table id="table" class="r-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Player Identity</th>
                            <th style="text-align: right;">Current Balance</th>
                            <th style="text-align: center;">Network Info</th>
                            <th style="text-align: right;">Registration</th>
                            <th style="width: 120px; text-align: center;">Account Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $indexVal = $offset + 1;
                        $paginationAvailable = false;

                        $user_records_sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='{$accountStatus}' ORDER BY tbl_balance DESC LIMIT {$offset},{$content}";
                        $user_records_result = mysqli_query($conn, $user_records_sql) or die('query failed');

                        if (mysqli_num_rows($user_records_result) > 0) {
                            $paginationAvailable = true;
                            while ($row = mysqli_fetch_assoc($user_records_result)) {

                                $uniq_id = $row['tbl_uniq_id'];
                                $request_status = $row['tbl_account_status'];
                                $balance = $row['tbl_balance'];
                                $username = $row['tbl_full_name'];

                                $data_sql = "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id='{$uniq_id}' ORDER BY id ASC LIMIT 1";
                                $data_query = mysqli_query($conn, $data_sql);
                                $ip = "N/A";
                                if ($data_row = mysqli_fetch_assoc($data_query)) {
                                    $ip = $data_row['tbl_device_ip'];
                                }

                                $rankClass = "";
                                if ($indexVal == 1)
                                    $rankClass = "rank-1";
                                else if ($indexVal == 2)
                                    $rankClass = "rank-2";
                                else if ($indexVal == 3)
                                    $rankClass = "rank-3";
                                ?>
                                <tr onclick="window.location.href='manager.php?id=<?php echo $uniq_id; ?>'">
                                    <td>
                                        <div class="rank-badge <?php echo $rankClass; ?>"><?php echo $indexVal; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 800; color: var(--text-main); line-height: 1.2;">
                                            <?php echo htmlspecialchars($row['tbl_user_name'] ?: $row['tbl_full_name']); ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--text-dim); margin-top: 2px;">
                                            <span style="opacity: 0.7;">Name:</span> <?php echo htmlspecialchars($row['tbl_full_name']); ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--accent-blue); font-family: monospace; opacity: 0.8;">
                                            ID: <?php echo $uniq_id; ?>
                                        </div>
                                    </td>
                                    <td
                                        style="text-align: right; font-weight: 800; color: var(--accent-emerald); font-size: 15px;">
                                        ₹<?php echo number_format($balance, 2); ?></td>
                                    <td style="text-align: center;">
                                        <div style="font-size: 12px; font-weight: 700;"><?php echo $row['tbl_mobile_num']; ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--text-dim);"><i class='bx bx-wifi'></i>
                                            <?php echo $ip; ?></div>
                                    </td>
                                    <td style="text-align: right; color: var(--text-dim); font-size: 12px; font-weight: 600;">
                                        <?php echo date("d M Y", strtotime($row['tbl_user_joined'])); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($request_status == 'true'): ?>
                                            <span class="tag tag-success">Active</span>
                                        <?php elseif ($request_status == 'ban'): ?>
                                            <span class="tag tag-danger">Banned</span>
                                        <?php else: ?>
                                            <span class="tag tag-dark">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $indexVal++;
                            }
                        } else { ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 60px; color: var(--text-dim);">
                                    <i class='bx bx-list-minus'
                                        style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                    No player data matches your search criteria.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php
                $count_sql = "SELECT id FROM tblusersdata WHERE tbl_account_status='{$accountStatus}'";
                $count_result = mysqli_query($conn, $count_sql);
                if (mysqli_num_rows($count_result) > 0) {
                    $total_records = mysqli_num_rows($count_result);
                    $total_page = ceil($total_records / $content);
                    ?>
                    <div class="pagination-container">
                        <div class="page-info">
                            Page <?php echo $page_num; ?> of <?php echo $total_page; ?> <span
                                style="margin-left: 10px; opacity: 0.5;">•</span> Total <?php echo $total_records; ?>
                            Players
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($page_num > 1): ?>
                                <a href="?page_num=<?php echo $page_num - 1; ?>&order_type=<?php echo $accountStatus; ?>"
                                    class="btn-modern btn-outline-modern">
                                    <i class='bx bx-chevron-left'></i> Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page_num < $total_page): ?>
                                <a href="?page_num=<?php echo $page_num + 1; ?>&order_type=<?php echo $accountStatus; ?>"
                                    class="btn-modern btn-outline-modern">
                                    Next <i class='bx bx-chevron-right'></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

        </div>
    </div>

    <script src="../script.js?v=1"></script>
    <script>
        document.querySelectorAll(".filter_btn").forEach(btn => {
            btn.addEventListener("click", () => {
                document.querySelector(".filter-options").classList.toggle("hide_view");
            });
        });

        const checkboxes = document.querySelectorAll('input[name="order_type"]');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                if (this.checked) {
                    checkboxes.forEach(other => {
                        if (other !== this) other.checked = false;
                    });
                }
            });
        });
    </script>
</body>

</html>