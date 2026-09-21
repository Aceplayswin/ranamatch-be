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

// Fetch filter parameters
$f_provider = isset($_GET['f_provider']) ? trim(mysqli_real_escape_string($conn, $_GET['f_provider'])) : '';
$f_game = isset($_GET['f_game']) ? trim(mysqli_real_escape_string($conn, $_GET['f_game'])) : '';
$f_date_from = isset($_GET['f_date_from']) && !empty($_GET['f_date_from']) ? $_GET['f_date_from'] : date('Y-m-d');
$f_date_to = isset($_GET['f_date_to']) && !empty($_GET['f_date_to']) ? $_GET['f_date_to'] : date('Y-m-d');

// Get all unique providers from tbl_games and tblmatchplayed
$all_providers = [];

$p_res1 = mysqli_query($conn, "SELECT DISTINCT game_provider FROM tbl_games WHERE game_provider IS NOT NULL AND game_provider != '' ORDER BY game_provider ASC");
if ($p_res1) {
    while ($r = mysqli_fetch_assoc($p_res1)) {
        $all_providers[] = $r['game_provider'];
    }
}

$p_res2 = mysqli_query($conn, "SELECT DISTINCT tbl_provider FROM tblmatchplayed WHERE tbl_provider IS NOT NULL AND tbl_provider != '' AND tbl_provider != 'Standard'");
if ($p_res2) {
    while ($r = mysqli_fetch_assoc($p_res2)) {
        if (!in_array($r['tbl_provider'], $all_providers)) {
            $all_providers[] = $r['tbl_provider'];
        }
    }
}
sort($all_providers);

// Build map of Game Name -> Provider & Image from tbl_games
$game_meta_map = [];
$g_res = mysqli_query($conn, "SELECT game_name, game_provider, game_image, game_category FROM tbl_games");
if ($g_res) {
    while ($grow = mysqli_fetch_assoc($g_res)) {
        $game_meta_map[strtolower(trim($grow['game_name']))] = [
            'provider' => $grow['game_provider'],
            'image' => $grow['game_image'],
            'category' => $grow['game_category']
        ];
    }
}

// Data aggregation structures
$provider_stats = []; // provider_name => [bet, win, loss, count]
$game_stats = [];     // game_name => [provider, bet, win, loss, count, image]

$selected_game_info = null;
$selected_game_logs = [];

$grand_filtered_bet = 0;
$grand_filtered_win = 0;
$grand_filtered_loss = 0;
$grand_filtered_count = 0;

// Fetch match records from tblmatchplayed
$all_matches_sql = "SELECT m.*, u.tbl_full_name, u.tbl_user_name 
                    FROM tblmatchplayed m 
                    LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id";
$all_matches_res = mysqli_query($conn, $all_matches_sql);

if ($all_matches_res) {
    while ($m = mysqli_fetch_assoc($all_matches_res)) {
        $cost = floatval($m['tbl_match_cost'] ?? 0);
        $profit = floatval($m['tbl_match_profit'] ?? 0);
        $m_status = strtolower(trim($m['tbl_match_status'] ?? ''));
        $m_result = strtolower(trim($m['tbl_match_result'] ?? ''));
        $game_name = trim($m['tbl_project_name'] ?? 'Unknown');
        $raw_provider = trim($m['tbl_provider'] ?? '');

        // Resolve provider name
        $game_key = strtolower($game_name);
        $resolved_provider = 'Standard / Uncategorized';
        $game_img = '';

        if (isset($game_meta_map[$game_key])) {
            $resolved_provider = $game_meta_map[$game_key]['provider'];
            $game_img = $game_meta_map[$game_key]['image'];
        } elseif (!empty($raw_provider) && $raw_provider !== 'Standard') {
            $resolved_provider = $raw_provider;
        } else {
            // Infer provider from project name if standard
            if (preg_match('/(saba|lucksport|9wickets|esports)/i', $game_name)) {
                $resolved_provider = 'Saba Sports';
            } elseif (preg_match('/aviator|spribe|mines|dice|goal|plinko|hilo|keno|hotline|scratch/i', $game_name)) {
                $resolved_provider = 'Spribe';
            } elseif (preg_match('/roulette|baccarat|blackjack|dragon|teenpatti|crazy|monopoly|dream|lightning/i', $game_name)) {
                $resolved_provider = 'Evolution Live';
            } else {
                $resolved_provider = $game_name;
            }
        }

        $is_win = (in_array($m_status, ['profit', 'win', 'won', 'cashout']) || in_array($m_result, ['profit', 'win', 'won', 'cashout']));
        $is_loss = (in_array($m_status, ['loss', 'lost']) || in_array($m_result, ['loss', 'lost']));

        // Check date range filter
        $rec_date = '';
        if (!empty($m['tbl_time_stamp'])) {
            $ts = strtotime($m['tbl_time_stamp']);
            if ($ts !== false) {
                $rec_date = date('Y-m-d', $ts);
            }
        }

        if (empty($rec_date) || ($rec_date >= $f_date_from && $rec_date <= $f_date_to)) {
            // Provider Filter
            if (!empty($f_provider) && strtolower($resolved_provider) !== strtolower($f_provider)) {
                continue;
            }

            // Game Filter
            if (!empty($f_game) && strpos(strtolower($game_name), strtolower($f_game)) === false) {
                continue;
            }

            // Global totals
            $grand_filtered_bet += $cost;
            $grand_filtered_count++;
            if ($is_win) {
                $grand_filtered_win += $profit;
            } elseif ($is_loss) {
                $grand_filtered_loss += $cost;
            }

            // Aggregate by Provider
            if (!isset($provider_stats[$resolved_provider])) {
                $provider_stats[$resolved_provider] = [
                    'bet' => 0, 'win' => 0, 'loss' => 0, 'count' => 0
                ];
            }
            $provider_stats[$resolved_provider]['bet'] += $cost;
            $provider_stats[$resolved_provider]['count']++;
            if ($is_win) {
                $provider_stats[$resolved_provider]['win'] += $profit;
            } elseif ($is_loss) {
                $provider_stats[$resolved_provider]['loss'] += $cost;
            }

            // Aggregate by Game
            if (!isset($game_stats[$game_name])) {
                $game_stats[$game_name] = [
                    'provider' => $resolved_provider,
                    'bet' => 0, 'win' => 0, 'loss' => 0, 'count' => 0,
                    'image' => $game_img
                ];
            }
            $game_stats[$game_name]['bet'] += $cost;
            $game_stats[$game_name]['count']++;
            if ($is_win) {
                $game_stats[$game_name]['win'] += $profit;
            } elseif ($is_loss) {
                $game_stats[$game_name]['loss'] += $cost;
            }

            // If a single game filter is active, collect logs
            if (!empty($f_game) && strpos(strtolower($game_name), strtolower($f_game)) !== false) {
                if ($selected_game_info === null) {
                    $selected_game_info = [
                        'name' => $game_name,
                        'provider' => $resolved_provider,
                        'image' => $game_img
                    ];
                }
                $selected_game_logs[] = $m;
            }
        }
    }
}

// Calculate House Net GGR
$grand_filtered_ggr = $grand_filtered_loss - $grand_filtered_win;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Provider & Game Analytics</title>
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
            color: var(--text-main);
            min-height: 100vh;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid var(--border-dim); padding-bottom: 16px;
        }
        .dash-breadcrumb {
            font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: block; margin-bottom: 4px;
        }
        .dash-title { font-size: 24px; font-weight: 800; color: var(--text-main); }

        .filter-panel {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 14px;
            padding: 20px; box-shadow: var(--card-shadow); margin-bottom: 24px;
        }

        .game-autocomplete-wrapper { position: relative; width: 100%; }
        .game-suggestions-box {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: rgba(22, 27, 34, 0.98); backdrop-filter: blur(12px);
            border: 1px solid var(--border-dim); border-radius: 12px;
            max-height: 320px; overflow-y: auto; z-index: 9999; display: none;
            box-shadow: 0 12px 32px rgba(0,0,0,0.5); padding: 6px;
        }

        .game-suggestion-item {
            display: flex; align-items: center; gap: 12px; padding: 8px 12px;
            border-radius: 8px; cursor: pointer; transition: all 0.2s ease;
        }
        .game-suggestion-item:hover {
            background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3);
        }
        .game-thumb-img {
            width: 36px; height: 36px; border-radius: 8px; object-fit: cover;
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim); flex-shrink: 0;
        }
        .game-thumb-placeholder {
            width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.15);
            color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
        }

        .metric-card {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 12px;
            padding: 16px; position: relative; overflow: hidden; box-shadow: var(--card-shadow);
        }

        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .r-table thead th {
            font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim);
            padding: 8px 12px; border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td {
            padding: 12px; font-size: 13px; color: var(--text-main);
            background: var(--table-header-bg); border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td:first-child { border-radius: 10px 0 0 10px; border-left: 1px solid var(--border-dim); }
        .r-table tbody td:last-child { border-radius: 0 10px 10px 0; border-right: 1px solid var(--border-dim); }
        .r-table tbody tr:hover td { background: var(--table-row-hover); }

        .btn-modern {
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 12px;
            display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; border: none; cursor: pointer;
        }
        .btn-primary-modern {
            background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <!-- Page Header -->
            <div class="dash-header">
                <div>
                    <span class="dash-breadcrumb">History & Records > Provider Analytics</span>
                    <span class="dash-title"><i class='bx bx-pie-chart-alt-2 me-2' style="color:#3b82f6;"></i>Provider & Game Reports</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class='bx bx-printer me-1'></i> Print Report
                    </button>
                    <a href="index.php" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #3b82f6, #2563eb); border: none;">
                        <i class='bx bx-refresh me-1'></i> Refresh Data
                    </a>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="filter-panel">
                <form method="GET" action="index.php" id="analyticsFilterForm">
                    <div class="row g-3 align-items-end">

                        <!-- Provider Filter -->
                        <div class="col-md-3">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px; letter-spacing: 0.5px;">Provider Filter</label>
                            <select name="f_provider" class="form-select form-select-sm" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim); height: 38px; border-radius: 8px;">
                                <option value="">All Providers (<?php echo count($all_providers); ?>)</option>
                                <?php foreach ($all_providers as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov); ?>" <?php if (strtolower($f_provider) === strtolower($prov)) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($prov); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Range From -->
                        <div class="col-md-2">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px; letter-spacing: 0.5px;">From Date</label>
                            <input type="date" name="f_date_from" value="<?php echo htmlspecialchars($f_date_from); ?>" class="form-control form-control-sm" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim); height: 38px; border-radius: 8px;">
                        </div>

                        <!-- Date Range To -->
                        <div class="col-md-2">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px; letter-spacing: 0.5px;">To Date</label>
                            <input type="date" name="f_date_to" value="<?php echo htmlspecialchars($f_date_to); ?>" class="form-control form-control-sm" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-dim); height: 38px; border-radius: 8px;">
                        </div>

                        <!-- Single Game Search with Image Autocomplete -->
                        <div class="col-md-4">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px; letter-spacing: 0.5px;">Single Game Search</label>
                            <div class="game-autocomplete-wrapper">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" style="background: var(--input-bg); border-color: var(--border-dim); color: var(--text-dim);"><i class='bx bx-search'></i></span>
                                    <input type="text" id="gameSearchInput" name="f_game" value="<?php echo htmlspecialchars($f_game); ?>" placeholder="Type game name (e.g. Aviator, Mines...)" class="form-control" style="background: var(--input-bg); color: var(--text-main); border-color: var(--border-dim); height: 38px; border-radius: 0 8px 8px 0;" autocomplete="off">
                                </div>
                                <div class="game-suggestions-box" id="gameSuggestionsBox"></div>
                            </div>
                        </div>

                        <!-- Submit & Reset Buttons -->
                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100" style="height: 38px; background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; border-radius: 8px; font-weight: 600;">
                                <i class='bx bx-filter-alt'></i>
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm" style="height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class='bx bx-reset'></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Date Presets -->
                    <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                        <span class="text-muted" style="font-size: 11px; font-weight: 600;">Quick Presets:</span>
                        <a href="?f_provider=<?php echo urlencode($f_provider); ?>&f_game=<?php echo urlencode($f_game); ?>&f_date_from=<?php echo date('Y-m-d'); ?>&f_date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-d') && $f_date_to == date('Y-m-d')) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">Today</a>
                        <a href="?f_provider=<?php echo urlencode($f_provider); ?>&f_game=<?php echo urlencode($f_game); ?>&f_date_from=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&f_date_to=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-d', strtotime('-1 day')) && $f_date_to == date('Y-m-d', strtotime('-1 day'))) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">Yesterday</a>
                        <a href="?f_provider=<?php echo urlencode($f_provider); ?>&f_game=<?php echo urlencode($f_game); ?>&f_date_from=<?php echo date('Y-m-01'); ?>&f_date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == date('Y-m-01') && $f_date_to == date('Y-m-d')) echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">This Month</a>
                        <a href="?f_provider=<?php echo urlencode($f_provider); ?>&f_game=<?php echo urlencode($f_game); ?>&f_date_from=1970-01-01&f_date_to=2099-12-31" class="btn btn-sm btn-outline-primary <?php if ($f_date_from == '1970-01-01') echo 'active'; ?>" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">All Time</a>
                    </div>
                </form>
            </div>

            <!-- Grand Totals Overview Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #3b82f6;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total Bet Done</div>
                        <div class="h3 mb-0 fw-bold" style="color: #3b82f6;">&#8377;<?php echo number_format($grand_filtered_bet, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;"><?php echo number_format($grand_filtered_count); ?> Total Bets Placed</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #10b981;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total User Win</div>
                        <div class="h3 mb-0 fw-bold" style="color: #10b981;">&#8377;<?php echo number_format($grand_filtered_win, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Total Winning Payouts</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #ef4444;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Total User Loss</div>
                        <div class="h3 mb-0 fw-bold" style="color: #ef4444;">&#8377;<?php echo number_format($grand_filtered_loss, 2); ?></div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">Total Lost Stakes</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="metric-card" style="border-left: 4px solid #f59e0b;">
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">House Net Revenue (GGR)</div>
                        <div class="h3 mb-0 fw-bold" style="color: <?php echo ($grand_filtered_ggr >= 0) ? '#10b981' : '#ef4444'; ?>;">
                            &#8377;<?php echo number_format($grand_filtered_ggr, 2); ?>
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">
                            <?php echo ($grand_filtered_ggr >= 0) ? 'House Net Profit' : 'House Net Loss'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Game Active Result View (If Single Game Searched) -->
            <?php if (!empty($f_game)): ?>
                <div class="filter-panel mb-4" style="border-color: rgba(59, 130, 246, 0.3);">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($selected_game_info['image'])): ?>
                                <img src="<?php echo htmlspecialchars($selected_game_info['image']); ?>" class="rounded-3" style="width: 54px; height: 54px; object-fit: cover; border: 1px solid var(--border-dim);" onerror="this.src='https://placehold.co/100x100?text=Game';">
                            <?php else: ?>
                                <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-size: 24px;">
                                    <i class='bx bx-game'></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="badge bg-primary text-uppercase" style="font-size: 9px;"><?php echo htmlspecialchars($selected_game_info['provider'] ?? 'Game'); ?></span>
                                <h4 class="mb-0 fw-bold" style="color: var(--text-main);"><?php echo htmlspecialchars($f_game); ?> Analytics</h4>
                                <span class="text-muted small">Single Game Performance Breakdown</span>
                            </div>
                        </div>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class='bx bx-x me-1'></i>Clear Single Game Filter</a>
                    </div>

                    <!-- Single Game Bet Logs Table -->
                    <div class="table-responsive">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>User ID & Name</th>
                                    <th>Game Title</th>
                                    <th>Stake (Bet)</th>
                                    <th>Win Payout</th>
                                    <th>Result Status</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($selected_game_logs)): 
                                    $g_idx = 1;
                                    foreach ($selected_game_logs as $log): 
                                        $s_cost = floatval($log['tbl_match_cost']);
                                        $s_profit = floatval($log['tbl_match_profit']);
                                        $s_status = strtolower($log['tbl_match_status'] ?? '');
                                        $s_is_win = (in_array($s_status, ['profit', 'win', 'won', 'cashout']));
                                ?>
                                    <tr>
                                        <td><span class="badge bg-dark"><?php echo $g_idx++; ?></span></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($log['tbl_full_name'] ?? 'User'); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($log['tbl_user_id']); ?></div>
                                        </td>
                                        <td><span class="fw-bold" style="color: #3b82f6;"><?php echo htmlspecialchars($log['tbl_project_name']); ?></span></td>
                                        <td class="fw-bold">&#8377;<?php echo number_format($s_cost, 2); ?></td>
                                        <td class="fw-bold" style="color: <?php echo $s_is_win ? '#10b981' : '#94a3b8'; ?>;">
                                            &#8377;<?php echo number_format($s_profit, 2); ?>
                                        </td>
                                        <td>
                                            <?php if ($s_is_win): ?>
                                                <span class="badge bg-success"><i class='bx bx-up-arrow-alt'></i> Win / Profit</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class='bx bx-down-arrow-alt'></i> Loss</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($log['tbl_time_stamp']); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No bet logs recorded for this game in selected date range.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Provider Performance Table -->
            <div class="filter-panel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold text-uppercase" style="font-size: 13px; letter-spacing: 1px; color: var(--text-main);">
                        <i class='bx bx-list-ol me-1' style="color: #3b82f6;"></i> Provider Breakdown Summary
                    </div>
                    <span class="badge bg-dark text-muted"><?php echo count($provider_stats); ?> Providers Listed</span>
                </div>

                <div class="table-responsive">
                    <table class="r-table" id="providerTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Provider Name</th>
                                <th>Total Bet Done</th>
                                <th>Total User Win</th>
                                <th>Total User Loss</th>
                                <th>House Profit / Loss (GGR)</th>
                                <th>Bets Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($provider_stats)): 
                                $p_no = 1;
                                uasort($provider_stats, function($a, $b) { return $b['bet'] <=> $a['bet']; });
                                foreach ($provider_stats as $prov_name => $stats):
                                    $p_ggr = $stats['loss'] - $stats['win'];
                            ?>
                                <tr>
                                    <td><span class="fw-bold text-muted"><?php echo $p_no++; ?></span></td>
                                    <td>
                                        <div class="fw-bold" style="color: var(--text-main); font-size: 14px;">
                                            <i class='bx bx-chip me-1' style="color: #3b82f6;"></i> <?php echo htmlspecialchars($prov_name); ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold" style="color: #3b82f6;">&#8377;<?php echo number_format($stats['bet'], 2); ?></td>
                                    <td class="fw-bold" style="color: #10b981;">&#8377;<?php echo number_format($stats['win'], 2); ?></td>
                                    <td class="fw-bold" style="color: #ef4444;">&#8377;<?php echo number_format($stats['loss'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($p_ggr >= 0) ? 'bg-success' : 'bg-danger'; ?>" style="font-size: 12px; padding: 6px 12px;">
                                            &#8377;<?php echo number_format($p_ggr, 2); ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-dark"><?php echo number_format($stats['count']); ?> Bets</span></td>
                                    <td>
                                        <a href="?f_provider=<?php echo urlencode($prov_name); ?>&f_date_from=<?php echo $f_date_from; ?>&f_date_to=<?php echo $f_date_to; ?>" class="btn btn-xs btn-outline-primary" style="font-size: 11px; border-radius: 6px;">
                                            <i class='bx bx-filter'></i> Filter Provider
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No provider betting data found for selected criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Single Games List Table -->
            <div class="filter-panel mt-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold text-uppercase" style="font-size: 13px; letter-spacing: 1px; color: var(--text-main);">
                        <i class='bx bx-joystick me-1' style="color: #06b6d4;"></i> All Games Financial Breakdown
                    </div>
                    <span class="badge bg-dark text-muted"><?php echo count($game_stats); ?> Games Active</span>
                </div>

                <div class="table-responsive">
                    <table class="r-table" id="gamesTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Game Title</th>
                                <th>Provider</th>
                                <th>Total Bet Done</th>
                                <th>User Win</th>
                                <th>User Loss</th>
                                <th>House GGR</th>
                                <th>Rounds</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($game_stats)):
                                $g_no = 1;
                                uasort($game_stats, function($a, $b) { return $b['bet'] <=> $a['bet']; });
                                foreach ($game_stats as $g_title => $g_data):
                                    $g_ggr = $g_data['loss'] - $g_data['win'];
                            ?>
                                <tr>
                                    <td><span class="fw-bold text-muted"><?php echo $g_no++; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($g_data['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($g_data['image']); ?>" class="rounded-2" style="width: 28px; height: 28px; object-fit: cover;" onerror="this.src='https://placehold.co/60x60?text=G';">
                                            <?php else: ?>
                                                <div class="rounded-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(6, 182, 212, 0.15); color: #06b6d4; font-size: 14px;">
                                                    <i class='bx bx-game'></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="fw-bold" style="color: var(--text-main);"><?php echo htmlspecialchars($g_title); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-dark text-info"><?php echo htmlspecialchars($g_data['provider']); ?></span></td>
                                    <td class="fw-bold" style="color: #3b82f6;">&#8377;<?php echo number_format($g_data['bet'], 2); ?></td>
                                    <td class="fw-bold" style="color: #10b981;">&#8377;<?php echo number_format($g_data['win'], 2); ?></td>
                                    <td class="fw-bold" style="color: #ef4444;">&#8377;<?php echo number_format($g_data['loss'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($g_ggr >= 0) ? 'bg-success' : 'bg-danger'; ?>">
                                            &#8377;<?php echo number_format($g_ggr, 2); ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-dark"><?php echo number_format($g_data['count']); ?></span></td>
                                    <td>
                                        <a href="?f_game=<?php echo urlencode($g_title); ?>&f_date_from=<?php echo $f_date_from; ?>&f_date_to=<?php echo $f_date_to; ?>" class="btn btn-xs btn-outline-info" style="font-size: 11px; border-radius: 6px;">
                                            <i class='bx bx-show'></i> Single Game Report
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No individual game records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Live Autocomplete JavaScript with Image Thumbnails -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchInput = document.getElementById("gameSearchInput");
        const suggestionsBox = document.getElementById("gameSuggestionsBox");
        const filterForm = document.getElementById("analyticsFilterForm");

        let debounceTimer;

        searchInput.addEventListener("input", function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 1) {
                suggestionsBox.style.display = "none";
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`search_games.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success' && res.data.length > 0) {
                            suggestionsBox.innerHTML = '';
                            res.data.forEach(item => {
                                const div = document.createElement("div");
                                div.className = "game-suggestion-item";
                                
                                let imgHtml = '';
                                if (item.game_image && item.game_image.length > 3) {
                                    imgHtml = `<img src="${item.game_image}" class="game-thumb-img" onerror="this.src='https://placehold.co/80x80?text=Game';">`;
                                } else {
                                    imgHtml = `<div class="game-thumb-placeholder"><i class='bx bx-game'></i></div>`;
                                }

                                div.innerHTML = `
                                    ${imgHtml}
                                    <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                                        <div class="fw-bold text-truncate text-white" style="font-size: 13px;">${item.game_name}</div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="badge bg-primary" style="font-size: 8px;">${item.game_provider}</span>
                                            <span class="badge bg-secondary" style="font-size: 8px;">${item.game_category}</span>
                                        </div>
                                    </div>
                                `;

                                div.addEventListener("click", function () {
                                    searchInput.value = item.game_name;
                                    suggestionsBox.style.display = "none";
                                    filterForm.submit();
                                });

                                suggestionsBox.appendChild(div);
                            });
                            suggestionsBox.style.display = "block";
                        } else {
                            suggestionsBox.style.display = "none";
                        }
                    })
                    .catch(err => {
                        console.error("Game search error:", err);
                        suggestionsBox.style.display = "none";
                    });
            }, 200);
        });

        document.addEventListener("click", function (e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = "none";
            }
        });
    });
    </script>
</body>

</html>
