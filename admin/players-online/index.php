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
date_default_timezone_set("Asia/Kolkata");

$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../logout-account');
    exit;
}

$curr_date = date('d-m-Y');
$curr_time = date('h:i:s a');
$curr_date_time = $curr_date . ' ' . $curr_time;

function getTimeDiffSeconds($datetime_1, $datetime_2) {
    $timestamp1 = strtotime($datetime_1);
    $timestamp2 = strtotime($datetime_2);
    if ($timestamp1 === false || $timestamp2 === false) return 999999;
    return abs($timestamp1 - $timestamp2);
}

// Time threshold in seconds (default 30 min = 1800 sec)
$window_minutes = isset($_GET['window']) ? intval($_GET['window']) : 30;
if ($window_minutes < 1) $window_minutes = 30;
$threshold_seconds = $window_minutes * 60;

// Search query
$search = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';

$sql = "SELECT * FROM tblusersdata WHERE tbl_account_status = 'true' ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

$online_players = [];
$total_online_balance = 0;
$active_5m = 0;
$active_15m = 0;
$active_30m = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $active_date = $row['tbl_last_active_date'] ?? '';
        $active_time = $row['tbl_last_active_time'] ?? '';
        $active_datetime_str = trim($active_date . ' ' . $active_time);

        $diff = getTimeDiffSeconds($curr_date_time, $active_datetime_str);

        // Count metrics
        if ($diff <= 300) $active_5m++;
        if ($diff <= 900) $active_15m++;
        if ($diff <= 1800) $active_30m++;

        // Filter players within selected time window
        if ($diff <= $threshold_seconds) {
            // Apply Search filter if set
            if (!empty($search)) {
                $full_name = $row['tbl_full_name'] ?? '';
                $user_name = $row['tbl_user_name'] ?? '';
                $uniq_id = $row['tbl_uniq_id'] ?? '';
                $mobile = $row['tbl_mobile_num'] ?? '';

                $search_lower = strtolower($search);
                if (
                    strpos(strtolower($full_name), $search_lower) === false &&
                    strpos(strtolower($user_name), $search_lower) === false &&
                    strpos(strtolower($uniq_id), $search_lower) === false &&
                    strpos(strtolower($mobile), $search_lower) === false
                ) {
                    continue;
                }
            }

            $row['idle_seconds'] = $diff;
            $row['idle_formatted'] = ($diff < 60) ? $diff . ' sec ago' : round($diff / 60) . ' min ago';
            $online_players[] = $row;
            $total_online_balance += floatval($row['tbl_balance'] ?? 0);
        }
    }
}

// Sort online players by most recently active first
usort($online_players, function($a, $b) {
    return $a['idle_seconds'] <=> $b['idle_seconds'];
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Real-Time Players Online</title>
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
            background: linear-gradient(90deg, #10b981, #06b6d4);
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

        .pulse-live-dot {
            width: 10px; height: 10px; border-radius: 50%; background: #10b981;
            display: inline-block; box-shadow: 0 0 12px #10b981; animation: pulseGlow 1.5s infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.2); }
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
                    <span class="dash-breadcrumb">Players > Real-Time Status</span>
                    <span class="dash-title">
                        <span class="pulse-live-dot me-2"></span>Players Online Monitor
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-check-label small text-muted me-1" for="autoRefreshToggle">
                        <i class='bx bx-sync bx-spin me-1'></i> Auto-Refresh (15s)
                    </label>
                    <input class="form-check-input mt-0" type="checkbox" id="autoRefreshToggle" checked>
                    <a href="index.php?window=<?php echo $window_minutes; ?>" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #10b981, #059669); border: none; font-weight: 600;">
                        <i class='bx bx-refresh me-1'></i> Refresh Now
                    </a>
                </div>
            </div>

            <!-- Metrics Overview -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #10b981;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Online (Last <?php echo $window_minutes; ?>m)</div>
                        <div class="h3 mb-0 fw-bold" style="color: #10b981;"><?php echo count($online_players); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Active Players Online</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #06b6d4;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Active (Last 5 Minutes)</div>
                        <div class="h3 mb-0 fw-bold" style="color: #06b6d4;"><?php echo $active_5m; ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Instant Activity</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #3b82f6;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Active (Last 15 Minutes)</div>
                        <div class="h3 mb-0 fw-bold" style="color: #3b82f6;"><?php echo $active_15m; ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Recent Sessions</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #f59e0b;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Online Players Balance</div>
                        <div class="h3 mb-0 fw-bold" style="color: #f59e0b;">&#8377;<?php echo number_format($total_online_balance, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Combined Total Balance</div>
                    </div>
                </div>
            </div>

            <!-- Content Panel -->
            <div class="content-panel">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <!-- Window Selector -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fw-bold" style="font-size: 11px; text-transform: uppercase;">Active Window:</span>
                        <a href="?window=5" class="btn btn-xs btn-outline-success <?php if ($window_minutes == 5) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">5 Mins</a>
                        <a href="?window=15" class="btn btn-xs btn-outline-success <?php if ($window_minutes == 15) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">15 Mins</a>
                        <a href="?window=30" class="btn btn-xs btn-outline-success <?php if ($window_minutes == 30) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">30 Mins</a>
                        <a href="?window=60" class="btn btn-xs btn-outline-success <?php if ($window_minutes == 60) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">60 Mins</a>
                    </div>

                    <!-- Search Box -->
                    <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="window" value="<?php echo $window_minutes; ?>">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search ID, Name, Mobile..." class="form-control form-control-sm" style="width: 220px; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim); border-radius: 6px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: #10b981; border: none; border-radius: 6px;"><i class='bx bx-search'></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="?window=<?php echo $window_minutes; ?>" class="btn btn-outline-secondary btn-sm" style="border-radius: 6px;"><i class='bx bx-x'></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Online Players Table -->
                <div class="table-responsive">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>User ID & Name</th>
                                <th>Mobile / Email</th>
                                <th>Current Balance</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($online_players)): 
                                $idx = 1;
                                foreach ($online_players as $player):
                            ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?php echo $idx++; ?></span></td>
                                    <td>
                                        <div class="fw-bold" style="color: var(--text-main); font-size: 14px;">
                                            <?php echo htmlspecialchars($player['tbl_full_name'] ?? 'Player'); ?>
                                        </div>
                                        <div class="small text-muted font-monospace">
                                            ID: <?php echo htmlspecialchars($player['tbl_uniq_id']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold" style="color: var(--accent-blue);"><?php echo htmlspecialchars($player['tbl_mobile_num'] ?? 'N/A'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($player['tbl_email_id'] ?? ''); ?></div>
                                    </td>
                                    <td class="fw-bold" style="color: #10b981; font-size: 14px;">
                                        &#8377;<?php echo number_format(floatval($player['tbl_balance'] ?? 0), 2); ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold" style="color: #06b6d4;"><?php echo htmlspecialchars($player['idle_formatted']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars(($player['tbl_last_active_date'] ?? '') . ' ' . ($player['tbl_last_active_time'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success" style="font-size: 11px; padding: 5px 10px;">
                                            <span class="pulse-live-dot me-1" style="width: 6px; height: 6px;"></span> Online
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <a href="../users-data/manager.php?id=<?php echo urlencode($player['tbl_uniq_id']); ?>" class="btn btn-xs btn-outline-info" style="font-size: 11px; border-radius: 6px;">
                                                <i class='bx bx-user-pin'></i> Manage Account
                                            </a>
                                            <a href="../blocked-ip/index.php?ip=<?php echo urlencode($player['tbl_user_ip'] ?? ''); ?>" class="btn btn-xs btn-outline-danger" style="font-size: 11px; border-radius: 6px;" title="Block Player IP">
                                                <i class='bx bx-shield-x'></i> Block IP
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No active players online in the last <?php echo $window_minutes; ?> minutes.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggle = document.getElementById("autoRefreshToggle");
            let timer;

            function startTimer() {
                timer = setInterval(() => {
                    if (toggle.checked) {
                        window.location.reload();
                    }
                }, 15000);
            }

            if (toggle) {
                startTimer();
                toggle.addEventListener("change", function() {
                    if (!this.checked) clearInterval(timer);
                    else startTimer();
                });
            }
        });
    </script>
</body>

</html>
