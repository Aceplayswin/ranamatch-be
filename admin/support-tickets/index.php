<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter(""); 

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

// Fetch Filters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search_username = $_GET['username'] ?? '';
$search_userid = $_GET['userid'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Self-Healing Database Migration
$create_tables = [
    "CREATE TABLE IF NOT EXISTS tbl_support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id VARCHAR(50) NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        profile_id VARCHAR(100),
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        priority VARCHAR(20) DEFAULT 'Medium',
        status ENUM('open','in_progress','closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS tbl_ticket_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id VARCHAR(50) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS tbl_ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id VARCHAR(50) NOT NULL,
        sender_type ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($create_tables as $sql) {
    mysqli_query($conn, $sql);
}

// Check for missing columns (migration - compatible with older MySQL versions)
$checkPriority = mysqli_query($conn, "SHOW COLUMNS FROM `tbl_support_tickets` LIKE 'priority'");
if (mysqli_num_rows($checkPriority) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_support_tickets ADD COLUMN priority VARCHAR(20) DEFAULT 'Medium' AFTER message");
}

$checkProfile = mysqli_query($conn, "SHOW COLUMNS FROM `tbl_support_tickets` LIKE 'profile_id'");
if (mysqli_num_rows($checkProfile) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_support_tickets ADD COLUMN profile_id VARCHAR(100) AFTER user_id");
}

$where_clauses = [];
if (!empty($status_filter)) $where_clauses[] = "t.status = '$status_filter'";
if (!empty($priority_filter)) $where_clauses[] = "t.priority = '$priority_filter'";
if (!empty($search_username)) $where_clauses[] = "u.tbl_user_name LIKE '%$search_username%'";
if (!empty($search_userid)) $where_clauses[] = "t.user_id LIKE '%$search_userid%'";

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $where_clauses[] = "DATE(t.created_at) >= '$start_date'";
} elseif (!empty($end_date)) {
    $where_clauses[] = "DATE(t.created_at) <= '$end_date'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$query = "SELECT t.*, u.tbl_user_name 
          FROM tbl_support_tickets t 
          LEFT JOIN tblusersdata u ON t.user_id = u.tbl_uniq_id 
          $where_sql 
          ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Support Tickets | Admin Panel</title>
    <?php include "../header_contents.php"; ?>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #FFA000;
            --brand-gradient: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            --accent-blue: #06b6d4;
        }

        * { font-family: 'DM Sans', sans-serif !important; }

        .admin-main-content {
            background-color: var(--page-bg) !important;
            background-image: radial-gradient(var(--border-dim) 1px, transparent 1px) !important;
            background-size: 26px 26px !important;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.015);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .filter-bar-modern {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            background: rgba(255, 255, 255, 0.01);
            padding: 15px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            margin-bottom: 20px;
            align-items: flex-end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 9px; font-weight: 900; text-transform: uppercase; color: var(--brand); letter-spacing: 1.5px; opacity: 0.8; }
        
        .filter-input {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
            border-radius: 8px !important;
            padding: 8px 12px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            outline: none !important;
            transition: all 0.3s ease !important;
            min-width: 140px;
        }
        .filter-input:focus { border-color: var(--brand) !important; box-shadow: 0 0 15px rgba(255, 160, 0, 0.15) !important; }

        .btn-refresh {
            background: rgba(255, 255, 255, 0.15) !important;
            border: 2px solid #ffffff !important;
            color: #ffffff !important;
            width: 42px !important; height: 42px !important;
            border-radius: 50% !important;
            display: flex !important; align-items: center !important; justify-content: center !important;
            cursor: pointer !important; transition: all 0.3s ease !important;
            box-shadow: 0 0 15px rgba(255,255,255,0.1) !important;
            z-index: 100 !important;
        }
        .btn-refresh:hover { 
            background: #FFA000 !important; 
            border-color: #FFA000 !important;
            color: #000 !important; 
            transform: rotate(180deg) scale(1.1) !important;
            box-shadow: 0 0 25px rgba(255, 160, 0, 0.5) !important;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-open { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-in_progress { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-closed { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

        .priority-badge { font-size: 9px; font-weight: 800; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; }
        .priority-High { color: #ef4444; border: 1px solid #ef4444; }
        .priority-Medium { color: #f59e0b; border: 1px solid #f59e0b; }
        .priority-Low { color: #10b981; border: 1px solid #10b981; }

        .r-table th { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 900; opacity: 0.6; }
        .r-table td { font-size: 12px; font-weight: 500; color: #cbd5e1; }

        .btn-action-modern {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-size: 10px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            text-decoration: none !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .btn-action-modern:hover { background: rgba(255,255,255,0.15) !important; transform: translateY(-2px) !important; }
        
        .btn-primary-modern {
            background: #FFA000 !important;
            color: #000000 !important;
            border: 1px solid #ffffff !important;
            height: 40px !important;
            padding: 0 28px !important;
            font-weight: 900 !important;
            box-shadow: 0 8px 30px rgba(255, 160, 0, 0.4) !important;
            cursor: pointer !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
        }
        .btn-primary-modern:hover {
            background: #FFB300 !important;
            transform: translateY(-4px) !important;
            box-shadow: 0 15px 40px rgba(255, 160, 0, 0.6) !important;
        }
    </style>
</head>

<body>

    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>

        <div class="admin-main-content">
            <div class="dash-header">
                <div class="header-title-area">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(255, 160, 0, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class='bx bx-support' style='color: var(--brand); font-size: 20px;'></i>
                    </div>
                    <div class="ms-3">
                        <h2 style="font-weight: 900; letter-spacing: -1px; font-size: 20px; color: #fff;">Support <span style="color: var(--brand);">Ticket</span></h2>
                        <p style="opacity: 0.5; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;">Monitor and manage user inquiries</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="location.reload()" class="btn-refresh" title="Refresh Data">
                        <i class='bx bx-refresh' style="font-size: 24px;"></i>
                    </button>
                </div>
            </div>

            <form method="GET" class="filter-bar-modern mt-4">
                <div class="filter-group">
                    <span class="filter-label">Status</span>
                    <select name="status" class="filter-input">
                        <option value="">All Status</option>
                        <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Priority</span>
                    <select name="priority" class="filter-input">
                        <option value="">All Priorities</option>
                        <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                        <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Username</span>
                    <input type="text" name="username" class="filter-input" placeholder="Search username..." value="<?php echo htmlspecialchars($search_username); ?>">
                </div>
                <div class="filter-group">
                    <span class="filter-label">User ID</span>
                    <input type="text" name="userid" class="filter-input" placeholder="Search UID..." value="<?php echo htmlspecialchars($search_userid); ?>">
                </div>
                <div class="filter-group">
                    <span class="filter-label">From Date</span>
                    <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <span class="filter-label">To Date</span>
                    <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-action-modern btn-primary-modern">Apply Filters</button>
                    <a href="index.php" class="btn-action-modern">Clear</a>
                </div>
            </form>

            <div class="glass-card mt-3">
                <div class="r-table-wrapper">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Username</th>
                                <th>User ID</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.01)'" onmouseout="this.style.background='transparent'">
                                        <td style="font-family: monospace; font-weight: 700; color: var(--brand);">
                                            #<?php echo $row['ticket_id']; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class='bx bx-user' style="opacity: 0.3;"></i>
                                                <span style="font-weight: 700; color: #fff;"><?php echo $row['tbl_user_name'] ?: 'Guest'; ?></span>
                                            </div>
                                        </td>
                                        <td style="font-size: 11px; opacity: 0.6;">
                                            <?php echo $row['user_id']; ?>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px;">
                                                <?php echo htmlspecialchars($row['subject']); ?>
                                            </div>
                                        </td>
                                        <td><span class="priority-badge priority-<?php echo $row['priority']; ?>"><?php echo $row['priority']; ?></span></td>
                                        <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status'] == 'open' ? 'Pending' : str_replace('_', ' ', $row['status']); ?></span></td>
                                        <td style="font-size: 11px; opacity: 0.6;">
                                            <?php echo date('d M Y, H:i', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="view.php?id=<?php echo $row['ticket_id']; ?>" class="btn-action-modern">
                                                <i class='bx bx-chat'></i> View & Reply
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 60px; color: var(--text-dim);">
                                        <i class='bx bx-search-alt' style="font-size: 32px; display: block; margin-bottom: 15px; opacity: 0.2;"></i>
                                        <p style="text-transform: uppercase; font-weight: 900; letter-spacing: 2px; font-size: 11px;">No tickets found matching your filters</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
</body>

</html>