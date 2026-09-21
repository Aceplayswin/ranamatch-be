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

// Self-healing migration for tbl_blocked_ips table
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_blocked_ips'");
if ($checkTable && mysqli_num_rows($checkTable) == 0) {
    $createSql = "CREATE TABLE IF NOT EXISTS tbl_blocked_ips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(100) NOT NULL UNIQUE,
        reason VARCHAR(255) DEFAULT 'Security Block',
        blocked_by VARCHAR(100) DEFAULT 'Admin',
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $createSql);
}

// Handle Add IP Block
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_ip') {
        $ip_address = trim(mysqli_real_escape_string($conn, $_POST['ip_address'] ?? ''));
        $reason = trim(mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Manual Security Block'));
        $blocked_by = $_SESSION['admin_user_id'] ?? 'Admin';

        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            $msg = "Invalid IP address format!";
            $msg_type = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_blocked_ips (ip_address, reason, blocked_by, status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'active'");
            $stmt->bind_param("sss", $ip_address, $reason, $blocked_by);
            if ($stmt->execute()) {
                $msg = "IP Address $ip_address has been blocked successfully.";
                $msg_type = "success";
            } else {
                $msg = "Error blocking IP: " . $conn->error;
                $msg_type = "danger";
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete_ip') {
        $ip_id = intval($_POST['ip_id'] ?? 0);
        if ($ip_id > 0) {
            mysqli_query($conn, "DELETE FROM tbl_blocked_ips WHERE id = $ip_id");
            $msg = "IP record removed from blacklist.";
            $msg_type = "success";
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $ip_id = intval($_POST['ip_id'] ?? 0);
        $new_status = mysqli_real_escape_string($conn, $_POST['new_status'] ?? 'active');
        if ($ip_id > 0) {
            mysqli_query($conn, "UPDATE tbl_blocked_ips SET status = '$new_status' WHERE id = $ip_id");
            $msg = "IP status updated to $new_status.";
            $msg_type = "success";
        }
    }
}

// Search and fetch blocked IPs
$search = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (ip_address LIKE '%$search%' OR reason LIKE '%$search%' OR blocked_by LIKE '%$search%')";
}

$res = mysqli_query($conn, "SELECT * FROM tbl_blocked_ips $where ORDER BY id DESC");
$blocked_list = [];
$active_count = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $blocked_list[] = $row;
        if ($row['status'] === 'active') {
            $active_count++;
        }
    }
}

$prefill_ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Blocked IP Manager</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../components/theme-variables.php"; ?>

        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            color: var(--text-main);
            min-height: 100vh;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid var(--border-dim); padding-bottom: 16px;
        }
        .dash-breadcrumb {
            font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
            background: linear-gradient(90deg, #ef4444, #f97316);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: block; margin-bottom: 4px;
        }
        .dash-title { font-size: 24px; font-weight: 800; color: var(--text-main); }

        .content-panel {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 14px;
            padding: 20px; box-shadow: var(--card-shadow); margin-bottom: 24px;
        }

        .metric-card {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 12px;
            padding: 16px; position: relative; overflow: hidden; box-shadow: var(--card-shadow);
        }

        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .r-table thead th {
            font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);
            padding: 10px 12px; border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td {
            padding: 12px; font-size: 13px; color: var(--text-main);
            background: var(--table-header-bg); border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td:first-child { border-radius: 10px 0 0 10px; border-left: 1px solid var(--border-dim); }
        .r-table tbody td:last-child { border-radius: 0 10px 10px 0; border-right: 1px solid var(--border-dim); }
        .r-table tbody tr:hover td { background: var(--table-row-hover); }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <!-- Page Header -->
            <div class="dash-header">
                <div>
                    <span class="dash-breadcrumb">Risk Management > IP Blacklist</span>
                    <span class="dash-title"><i class='bx bx-shield-x me-2' style="color:#ef4444;"></i>Blocked IP Manager</span>
                </div>
                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addIpModal" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: none; font-weight: 600;">
                    <i class='bx bx-plus-circle me-1'></i> Block New IP
                </button>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Metrics Overview -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="metric-card" style="border-left: 4px solid #ef4444;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total Blocked IPs</div>
                        <div class="h3 mb-0 fw-bold" style="color: #ef4444;"><?php echo count($blocked_list); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Blacklisted IP addresses</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="metric-card" style="border-left: 4px solid #f97316;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Active Security Blocks</div>
                        <div class="h3 mb-0 fw-bold" style="color: #f97316;"><?php echo $active_count; ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Currently Enforced</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="metric-card" style="border-left: 4px solid #3b82f6;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Inactive / Whitelisted</div>
                        <div class="h3 mb-0 fw-bold" style="color: #3b82f6;"><?php echo count($blocked_list) - $active_count; ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Unblocked History</div>
                    </div>
                </div>
            </div>

            <!-- Blocked IP Table -->
            <div class="content-panel">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="fw-bold text-uppercase" style="font-size: 13px; letter-spacing: 1px; color: var(--text-main);">
                        <i class='bx bx-list-check me-1' style="color: #ef4444;"></i> IP Blacklist Records
                    </div>

                    <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search IP or Reason..." class="form-control form-control-sm" style="width: 220px; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim); border-radius: 6px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: #3b82f6; border: none; border-radius: 6px;"><i class='bx bx-search'></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 6px;"><i class='bx bx-x'></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Blocked By</th>
                                <th>Status</th>
                                <th>Date Blocked</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($blocked_list)): 
                                $idx = 1;
                                foreach ($blocked_list as $ip):
                            ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?php echo $idx++; ?></span></td>
                                    <td>
                                        <div class="fw-bold" style="color: #ef4444; font-family: 'JetBrains Mono', monospace; font-size: 14px;">
                                            <i class='bx bx-globe me-1'></i> <?php echo htmlspecialchars($ip['ip_address']); ?>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold" style="color: var(--text-main);"><?php echo htmlspecialchars($ip['reason']); ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ip['blocked_by']); ?></span></td>
                                    <td>
                                        <?php if ($ip['status'] === 'active'): ?>
                                            <span class="badge bg-danger"><i class='bx bx-block me-1'></i> Blocked</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class='bx bx-check-circle me-1'></i> Whitelisted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($ip['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="ip_id" value="<?php echo $ip['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo ($ip['status'] === 'active') ? 'inactive' : 'active'; ?>">
                                                <button type="submit" class="btn btn-xs <?php echo ($ip['status'] === 'active') ? 'btn-outline-warning' : 'btn-outline-success'; ?>" style="font-size: 11px; padding: 3px 8px;">
                                                    <?php echo ($ip['status'] === 'active') ? 'Unblock' : 'Re-block'; ?>
                                                </button>
                                            </form>

                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this IP record?');">
                                                <input type="hidden" name="action" value="delete_ip">
                                                <input type="hidden" name="ip_id" value="<?php echo $ip['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size: 11px; padding: 3px 8px;">
                                                    <i class='bx bx-trash'></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No blocked IP records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Add IP Modal -->
    <div class="modal fade" id="addIpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--panel-bg); color: var(--text-main); border: 1px solid var(--border-dim);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-dim);">
                    <h5 class="modal-title fw-bold" style="color: #ef4444;"><i class='bx bx-shield-x me-1'></i> Block New IP Address</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_ip">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">IP ADDRESS</label>
                            <input type="text" name="ip_address" value="<?php echo htmlspecialchars($prefill_ip); ?>" placeholder="e.g. 192.168.1.1 or 103.45.12.99" class="form-control" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim);" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">REASON FOR BLOCKING</label>
                            <input type="text" name="reason" placeholder="e.g. Suspicious Login, Fraud Attempt, Bot Activity..." class="form-control" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim);" required>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-dim);">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm" style="background: #ef4444; border: none; font-weight: 600;">Enforce IP Block</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($prefill_ip)): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var modal = new bootstrap.Modal(document.getElementById('addIpModal'));
                modal.show();
            });
        </script>
    <?php endif; ?>
</body>

</html>
