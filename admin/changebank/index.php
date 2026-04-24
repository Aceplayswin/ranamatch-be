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
    if ($accessObj->isAllowed("access_settings") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }   
} else {
    header('location:../../logout-account');
    exit;
}

$search_input = trim($_POST['search_input'] ?? $_GET['search'] ?? '');

$where_sql = "";
if (!empty($search_input)) {
    $where_sql = " WHERE u.tbl_uniq_id LIKE ? OR u.tbl_user_name LIKE ? ";
}

$query = "SELECT u.tbl_uniq_id as user_id, u.tbl_user_name as username, u.tbl_balance as balance, COUNT(b.id) as accounts_count 
          FROM tblusersdata u 
          INNER JOIN tblallbankcards b ON u.tbl_uniq_id = b.tbl_user_id 
          $where_sql 
          GROUP BY u.tbl_uniq_id 
          ORDER BY accounts_count DESC";

if (!empty($search_input)) {
    $stmt = $conn->prepare($query);
    $like = "%$search_input%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = mysqli_query($conn, $query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Manage Bank Cards</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1px solid var(--border-dim);
            margin-bottom: 12px;
        }
        .dash-header-left { display: flex; align-items: center; gap: 12px; }
        .back-btn {
            width: 32px; height: 32px; border-radius: 8px; background: var(--input-bg);
            border: 1px solid var(--border-dim); color: var(--text-main); display: flex; align-items: center;
            justify-content: center; font-size: 20px; cursor: pointer; transition: all 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.1); transform: translateX(-4px); }

        .dash-breadcrumb { font-size: 8px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--status-info); }
        .dash-title { font-size: 20px; font-weight: 800; color: var(--text-main); }

        .search-area {
            background: var(--panel-bg); border: 1px solid var(--border-dim);
            border-radius: 16px; padding: 16px; margin: 0 20px 16px;
            box-shadow: var(--card-shadow);
        }
        .cus-inp {
            height: 38px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 10px !important;
            padding: 0 12px !important; color: var(--text-main) !important; font-size: 12px !important;
        }
        .cus-inp:focus { border-color: var(--accent-blue) !important; box-shadow: none !important; }
        .cus-inp::placeholder { color: #64748b !important; opacity: 1; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 600;
            display: flex; align-items: center; gap: 6px; transition: all 0.2s;
            cursor: pointer; border: none; font-size: 12px;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); }
        
        .update-btn {
            background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);
            padding: 6px 12px; border-radius: 8px; font-size: 10px; font-weight: 700;
            border: 1px solid rgba(59, 130, 246, 0.2); transition: all 0.2s;
        }
        .update-btn:hover { background: var(--accent-blue); color: #fff; }

        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .r-table thead th {
            font-size: 9px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase;
            color: var(--text-dim); padding: 0 12px 6px; border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td {
            padding: 10px 12px; font-size: 12px; font-weight: 500; color: var(--text-main);
            background: var(--table-header-bg); border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td:first-child { border-radius: 10px 0 0 10px; border-left: 1px solid var(--border-dim); }
        .r-table tbody td:last-child { border-radius: 0 10px 10px 0; border-right: 1px solid var(--border-dim); }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .dash-header { flex-direction: column; align-items: flex-start; gap: 12px; padding: 12px 16px; }
            .btn-modern { width: 100%; justify-content: center; }
            
            .search-area { margin: 0 16px 12px; padding: 12px; border-radius: 12px; }
            .search-area .row { flex-direction: column; gap: 8px; }
            .search-area .col-md-9, .search-area .col-md-3 { width: 100%; }
            
            .dash-title { font-size: 16px; }
            .admin-main-content { padding-bottom: 20px; }
        }
        
        .table-inp {
            background: var(--input-bg) !important; border: 1px solid var(--border-dim) !important;
            border-radius: 8px !important; padding: 8px 12px !important; color: var(--text-main) !important;
            font-size: 13px !important; font-weight: 600 !important; transition: all 0.2s;
            width: 100%;
        }
        .table-inp:focus {
            background: rgba(255,255,255,0.06) !important; border-color: var(--accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; outline: none;
        }

        .msg-alert {
            margin: 0 20px 20px; padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 14px;
        }
        .msg-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); }
        .msg-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
    </style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div>
                    <span class="dash-breadcrumb">System Settings > Bank Control</span>
                    <h1 class="dash-title">Search & Change Bank Details</h1>
                </div>
            </div>
            <button class="btn-modern" onclick="window.location.href='index.php'" style="background: var(--input-bg); border: 1px solid var(--border-dim); color: var(--text-main);">
                <i class='bx bx-refresh'></i> Refresh All
            </button>
        </div>

        <div class="search-area">
            <form method="POST" class="row g-3">
                <div class="col-md-9">
                    <input type="text" name="search_input" placeholder="Enter User ID or Beneficiary Name..." class="form-control cus-inp" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="search" class="btn-modern btn-primary-modern w-100">
                        <i class='bx bx-search'></i> Search Data
                    </button>
                </div>
            </form>
        </div>

        <div style="padding: 0 20px;">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th style="text-align: center;">Bank Accounts</th>
                            <th style="text-align: center;">Current Real Balance</th>
                            <th style="text-align: right;">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr onmouseover="this.style.background='rgba(255,255,255,0.01)'" onmouseout="this.style.background='transparent'" style="transition: all 0.2s;">
                                <td style="font-weight: 700; color: var(--accent-blue);"><?= htmlspecialchars($row['user_id']) ?></td>
                                <td style="font-weight: 600; color: #fff;"><?= htmlspecialchars($row['username'] ?: 'N/A') ?></td>
                                <td style="text-align: center;">
                                    <span style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 11px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <?= $row['accounts_count'] ?> Accounts
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #fff; font-size: 14px;">
                                    ₹<?= number_format($row['balance'], 2) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="view.php?user_id=<?= $row['user_id'] ?>" class="update-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class='bx bx-edit-alt'></i> Manage Accounts
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 100px; color: var(--text-dim); background: rgba(255,255,255,0.01); border-radius: 24px; border: 2px dashed rgba(255,255,255,0.05); margin-top: 20px;">
                    <i class='bx bx-search-alt' style="font-size: 64px; display: block; margin-bottom: 20px; opacity: 0.2;"></i>
                    <p style="text-transform: uppercase; font-weight: 900; letter-spacing: 2px; font-size: 12px; opacity: 0.5;">No users found with bank records</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>
</body>
</html>