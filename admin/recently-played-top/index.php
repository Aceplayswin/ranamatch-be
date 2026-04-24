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

$content = 15;
$page_num = (int) (isset($_GET['page_num']) ? $_GET['page_num'] : 1);
if ($page_num < 1)
    $page_num = 1;
$offset = ($page_num - 1) * $content;

// Current server dates for processing
$today_date = date('Y-m-d');
$yesterday_date = date('Y-m-d', strtotime('-1 day'));

// Get TODAY'S game statistics
$game_stats_sql = "SELECT mp.tbl_project_name as game, 
                   SUM(mp.tbl_match_cost) as total_bet_amount,
                   COUNT(*) as bet_count 
                   FROM tblmatchplayed mp
                   WHERE DATE(mp.created_at) = '$today_date' 
                   GROUP BY mp.tbl_project_name 
                   ORDER BY total_bet_amount DESC 
                   LIMIT 5";
$game_stats_result = mysqli_query($conn, $game_stats_sql);
$game_stats = [];
while ($row = mysqli_fetch_assoc($game_stats_result)) {
    $game_stats[] = $row;
}

// Get TODAY'S top players for each game
$top_players_by_game_sql = "SELECT mp.tbl_project_name as game, 
                           mp.tbl_user_id as user_id,
                           SUM(mp.tbl_match_cost) as total_bet_amount
                           FROM tblmatchplayed mp
                           WHERE DATE(mp.created_at) = '$today_date'
                           GROUP BY mp.tbl_project_name, mp.tbl_user_id
                           ORDER BY mp.tbl_project_name, total_bet_amount DESC";
$top_players_result = mysqli_query($conn, $top_players_by_game_sql);
$top_players_by_game = [];
while ($row = mysqli_fetch_assoc($top_players_result)) {
    if (!isset($top_players_by_game[$row['game']])) {
        $top_players_by_game[$row['game']] = [];
    }
    if (count($top_players_by_game[$row['game']]) < 3) {
        $top_players_by_game[$row['game']][] = $row;
    }
}

// Today's Top Bets - Above ₹5000
$todayTopBetsSql = "SELECT * FROM tblmatchplayed 
                    WHERE DATE(created_at) = '$today_date' 
                    AND tbl_match_cost > 5000
                    ORDER BY tbl_match_cost DESC LIMIT 5";
$todayTopBetsResult = mysqli_query($conn, $todayTopBetsSql);
$todayTopBets = [];
while ($row = mysqli_fetch_assoc($todayTopBetsResult)) {
    $todayTopBets[] = $row;
}

// Yesterday's Top Bets - Above ₹5000
$yesterdayTopBetsSql = "SELECT * FROM tblmatchplayed 
                        WHERE DATE(created_at) = '$yesterday_date'
                        AND tbl_match_cost > 5000
                        ORDER BY tbl_match_cost DESC LIMIT 5";
$yesterdayTopBetsResult = mysqli_query($conn, $yesterdayTopBetsSql);
$yesterdayTopBets = [];
while ($row = mysqli_fetch_assoc($yesterdayTopBetsResult)) {
    $yesterdayTopBets[] = $row;
}

// Alternative: Last 24-48 hours
$alt_yesterday_sql = "SELECT * FROM tblmatchplayed 
                     WHERE created_at >= NOW() - INTERVAL 2 DAY 
                     AND created_at < NOW() - INTERVAL 1 DAY
                     AND tbl_match_cost > 5000
                     ORDER BY tbl_match_cost DESC LIMIT 5";
$alt_yesterday_result = mysqli_query($conn, $alt_yesterday_sql);
$alt_yesterday_bets = [];
while ($row = mysqli_fetch_assoc($alt_yesterday_result)) {
    $alt_yesterday_bets[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Top Records</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../components/theme-variables.php"; ?>
    </style>
    <style>
        /* Page specific variable overrides only if needed */
        /* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }


        .metric-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 16px;
            padding: 0;
            position: relative;
            overflow: hidden;
            height: 100%;
            box-shadow: var(--card-shadow);
        }

        .metric-card-header {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-dim);
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .game-stat-item {
            background: var(--table-header-bg);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
            border: 1px solid var(--border-dim);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .title-bar {
            width: 4px;
            height: 20px;
            border-radius: 2px;
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
            border-color: var(--border-dim);
        }
    </style>
</head>

<body>
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php"; ?>
        <div class="admin-main-content hide-native-scrollbar">

            <div class="dash-header">
                <div class="dash-header-left">
                    <div>
                        <span class="dash-breadcrumb">History & Records > Top Records</span>
                        <span class="dash-title">Betting Insights</span>
                    </div>
                </div>
                <div class="dash-header-right">
                    <button class="btn-modern btn-outline-modern" onclick="location.reload()">
                        <i class='bx bx-refresh'></i> Refresh Data
                    </button>
                </div>
            </div>

            <div style="padding: 12px 14px;">

                <div class="search-area">
                    <form method="POST" class="search-input-group" action="index.php">
                        <input type="text" name="searchinp" placeholder="Search by ID, Game or User ID..."
                            class="search-input" value="<?php echo htmlspecialchars($searched); ?>" />
                        <button class="btn-modern btn-primary-modern" name="submit" type="submit">
                            <i class='bx bx-search'></i> Search Records
                        </button>
                        <button class="btn-modern btn-outline-modern" type="button" onclick="window.print()">
                            <i class='bx bx-printer'></i> Export Data
                        </button>
                    </form>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Today's Top Games -->
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-header" style="color: var(--accent-blue);">
                                <i class='bx bx-trending-up'></i> Today's Top Games
                            </div>
                            <div class="metric-card-body p-0">
                                <table class="r-table" style="margin: 0;">
                                    <thead>
                                        <tr>
                                            <th>Game</th>
                                            <th>Total Bet</th>
                                            <th>Top Players</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($game_stats) > 0): ?>
                                            <?php foreach ($game_stats as $game): ?>
                                                <tr>
                                                    <td style="font-weight: 700; font-size: 12px;">
                                                        <?php echo htmlspecialchars($game['game']); ?></td>
                                                    <td style="font-weight: 800; font-size: 12px; color: var(--accent-blue);">
                                                        ₹<?php echo number_format($game['total_bet_amount'], 2); ?></td>
                                                    <td>
                                                        <div style="display: flex; flex-direction: column; gap: 2px;">
                                                            <?php if (isset($top_players_by_game[$game['game']])): ?>
                                                                <?php foreach ($top_players_by_game[$game['game']] as $player): ?>
                                                                    <div style="font-size: 10px; color: var(--text-dim);">
                                                                        <span><?php echo htmlspecialchars($player['user_id']); ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">No data for today</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Top Bets -->
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-header" style="color: var(--accent-emerald);">
                                <i class='bx bx-shield-quarter'></i> Today's Top Bets (>₹5000)
                            </div>
                            <div class="metric-card-body p-0">
                                <table class="r-table">
                                    <thead>
                                        <tr>
                                            <th>User ID</th>
                                            <th>Game</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($todayTopBets) > 0): ?>
                                            <?php foreach ($todayTopBets as $bet): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($bet['tbl_user_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($bet['tbl_project_name']); ?></td>
                                                    <td style="font-weight: 700;">
                                                        ₹<?php echo number_format($bet['tbl_match_cost'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">None today</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Yesterday's Top Bets (With Tabs) -->
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-header" style="color: var(--accent-amber);">
                                <i class='bx bx-history'></i> Yesterday's Top Bets
                            </div>
                            <div class="metric-card-body p-0">
                                <ul class="nav nav-tabs" id="yesterdayTabs" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" id="date-tab" data-bs-toggle="tab"
                                            data-bs-target="#date-content">By Date</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="hours-tab" data-bs-toggle="tab"
                                            data-bs-target="#hours-content">By Hours</button>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="date-content">
                                        <table class="r-table">
                                            <thead>
                                                <tr>
                                                    <th>User ID</th>
                                                    <th>Game</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($yesterdayTopBets) > 0): ?>
                                                    <?php foreach ($yesterdayTopBets as $bet): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($bet['tbl_user_id']); ?></td>
                                                            <td><?php echo htmlspecialchars($bet['tbl_project_name']); ?></td>
                                                            <td>₹<?php echo number_format($bet['tbl_match_cost'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4 text-muted">No high bets
                                                            yesterday</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="hours-content">
                                        <table class="r-table">
                                            <thead>
                                                <tr>
                                                    <th>User ID</th>
                                                    <th>Game</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($alt_yesterday_bets) > 0): ?>
                                                    <?php foreach ($alt_yesterday_bets as $bet): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($bet['tbl_user_id']); ?></td>
                                                            <td><?php echo htmlspecialchars($bet['tbl_project_name']); ?></td>
                                                            <td>₹<?php echo number_format($bet['tbl_match_cost'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4 text-muted">No high bets in
                                                            period</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Bettors Leaderboard -->
                <div class="metric-card mb-4" style="background: var(--panel-bg);">
                    <div class="section-title">
                        <span class="title-bar"
                            style="background: linear-gradient(180deg, var(--accent-amber), #d97706);"></span>
                        Top Bettors - Latest High Bets
                    </div>
                    <div class="metric-card-body p-0">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th width="60">No</th>
                                    <th>User ID</th>
                                    <th>Game</th>
                                    <th>Bet Amount</th>
                                    <th>Profit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $top_bet_sql = "SELECT * FROM tblmatchplayed ORDER BY id DESC, tbl_match_cost DESC LIMIT 100";
                                $top_bet_result = mysqli_query($conn, $top_bet_sql);
                                $user_game_pairs = [];
                                $i = 1;
                                while ($top = mysqli_fetch_assoc($top_bet_result)) {
                                    $key = $top['tbl_user_id'] . '-' . $top['tbl_project_name'];
                                    if (isset($user_game_pairs[$key]))
                                        continue;
                                    $user_game_pairs[$key] = true;
                                    $is_profit = ($top['tbl_match_status'] == 'profit');
                                    ?>
                                    <tr>
                                        <td><?php echo $i; ?></td>
                                        <td style="font-weight: 700; color: var(--text-main);">
                                            <?php echo htmlspecialchars($top['tbl_user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($top['tbl_project_name']); ?></td>
                                        <td style="font-weight: 700;">
                                            ₹<?php echo number_format($top['tbl_match_cost'], 2); ?></td>
                                        <td
                                            style="font-weight: 700; color: <?php echo $is_profit ? 'var(--status-success)' : 'var(--status-danger)'; ?>">
                                            ₹<?php echo number_format($top['tbl_match_profit'], 2); ?>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge <?php echo $is_profit ? 'status-profit' : 'status-loss'; ?>">
                                                <?php echo ucfirst($top['tbl_match_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php
                                    $i++;
                                    if ($i > 10)
                                        break;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Records -->
                <div class="record-section">
                    <div class="section-title">
                        <span class="title-bar"
                            style="background: linear-gradient(180deg, var(--accent-blue), #1d4ed8);"></span>
                        Recent Betting Logs
                    </div>

                    <table class="r-table">
                        <thead>
                            <tr>
                                <th width="80">No</th>
                                <th>User ID</th>
                                <th>Game Name</th>
                                <th>Bet Amount</th>
                                <th>Profit / Loss</th>
                                <th width="150">Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($searched != "") {
                                $main_sql = "SELECT * FROM tblmatchplayed WHERE tbl_user_id LIKE '%$searched%' OR tbl_project_name LIKE '%$searched%' ORDER BY id DESC LIMIT 100";
                            } else {
                                $main_sql = "SELECT * FROM tblmatchplayed ORDER BY id DESC LIMIT $offset, $content";
                            }
                            $main_res = mysqli_query($conn, $main_sql);
                            $k = 1;
                            if (mysqli_num_rows($main_res) > 0) {
                                while ($row = mysqli_fetch_assoc($main_res)) {
                                    $is_profit = ($row['tbl_match_status'] == 'profit');
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-dim); font-size: 11px;"><?php echo $k + $offset; ?></td>
                                        <td style="font-weight: 700; color: var(--text-main);">
                                            <?php echo htmlspecialchars($row['tbl_user_id']); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div
                                                    style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-blue);">
                                                </div>
                                                <?php echo htmlspecialchars($row['tbl_project_name']); ?>
                                            </div>
                                        </td>
                                        <td style="font-weight: 800; color: var(--text-main);">
                                            ₹<?php echo number_format((float) $row['tbl_match_cost'], 2); ?></td>
                                        <td
                                            style="font-weight: 700; color: <?php echo $is_profit ? 'var(--status-success)' : 'var(--status-danger)'; ?>">
                                            ₹<?php echo number_format((float) $row['tbl_match_profit'], 2); ?>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge <?php echo $is_profit ? 'status-profit' : 'status-loss'; ?>">
                                                <i
                                                    class='bx <?php echo $is_profit ? 'bx-trending-up' : 'bx-trending-down'; ?>'></i>
                                                <?php echo ucfirst($row['tbl_match_status']); ?>
                                            </span>
                                        </td>
                                        <td
                                            style="color: var(--text-dim); font-size: 12px; font-weight: 600; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['tbl_time_stamp']); ?>
                                        </td>
                                    </tr>
                                    <?php
                                    $k++;
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center py-5 text-muted'>No data found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>

                    <?php if ($searched == ""):
                        $count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM tblmatchplayed");
                        $total_rec = (int) mysqli_fetch_assoc($count_res)['total'];
                        $total_p = ceil($total_rec / $content);
                        ?>
                        <div class="d-flex justify-content-between align-items-center mt-4" style="padding: 0 10px;">
                            <div style="font-size: 12px; color: var(--text-dim); font-weight: 600;">
                                Showing page <?php echo $page_num; ?> of <?php echo $total_p; ?> (Total:
                                <?php echo $total_rec; ?> records)
                            </div>
                            <div class="pagination-container" style="margin: 0;">
                                <a href="?page_num=<?php echo max(1, $page_num - 1); ?>"
                                    class="page-btn <?php if ($page_num <= 1)
                                        echo 'disabled'; ?>">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                                <?php
                                $sp = max(1, $page_num - 2);
                                $ep = min($total_p, $page_num + 2);
                                for ($i = $sp; $i <= $ep; $i++) {
                                    $act = ($page_num == $i) ? 'active' : '';
                                    echo "<a href='?page_num={$i}' class='page-btn {$act}'>{$i}</a>";
                                }
                                ?>
                                <a href="?page_num=<?php echo min($total_p, $page_num + 1); ?>"
                                    class="page-btn <?php if ($page_num >= $total_p)
                                        echo 'disabled'; ?>">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script.js?v=05"></script>
</body>

</html>