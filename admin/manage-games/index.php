<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$access = new AccessValidate();
if ($access->validate() == "false") {
    header('location:../index.php');
    die();
}

// Search and Filter logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (game_name LIKE '%$search%' OR game_uid LIKE '%$search%')";
}
if ($category) {
    $where .= " AND game_category = '$category'";
}
if ($status !== '') {
    $where .= " AND game_status = '$status'";
}

$sql = "SELECT * FROM tbl_games $where ORDER BY sort_order ASC, id ASC";
$result = mysqli_query($conn, $sql);

// Get unique categories for filter
$cat_sql = "SELECT DISTINCT game_category FROM tbl_games";
$cat_result = mysqli_query($conn, $cat_sql);
?>
    
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <?php include "../header_contents.php" ?>
    <title>Manage Games | <?php echo $APP_NAME; ?> Admin</title>
    
    <link href='../style.css?v=<?php echo time(); ?>' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        <?php include "../components/theme-variables.php"; ?>
        
        :root {
            --card-padding: 12px;
            --btn-height: 30px;
            --fs-base: 13px;
            --fs-small: 11px;
            --fs-header: 18px;
        }

        @keyframes borderGlow { 0%,100% { border-color: rgba(59,130,246,0.15); } 50% { border-color: rgba(6,182,212,0.3); } }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        /* Layout Structure */
        .admin-layout-wrapper {
            display: flex;
            min-height: 100vh;
            background: var(--page-bg);
            position: relative;
        }

        .admin-main-content {
            flex: 1;
            margin-left: 260px;
            padding: var(--card-padding) !important;
            transition: margin-left 0.3s ease, padding 0.3s ease;
            min-height: 100vh;
            overflow-x: hidden;
            font-size: var(--fs-base);
        }

        .admin-layout-wrapper.sidebar-collapsed .admin-main-content {
            margin-left: 0 !important;
        }

        .admin-layout-wrapper.sidebar-collapsed .side-menu {
            transform: translateX(-100%);
        }

        /* Compact Header */
        .dash-header {
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dash-title h1 {
            font-family: 'DM Sans', sans-serif;
            font-size: var(--fs-header) !important;
            font-weight: 700 !important;
            margin: 0 !important;
            color: var(--text-main) !important;
            letter-spacing: -0.3px !important;
        }

        .dash-breadcrumb {
            font-size: 9px !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent-primary) !important;
            font-weight: 700 !important;
            opacity: 0.8;
            display: block;
            margin-bottom: 2px !important;
        }

        .dash-header-right {
            display: flex;
            gap: 6px;
        }

        /* Buttons - Compact & Fixed Visibility */
        .btn-premium {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan)) !important;
            border: none !important;
            border-radius: 6px !important;
            color: #ffffff !important;
            padding: 0 12px !important;
            height: var(--btn-height) !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            transition: all 0.2s !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.2) !important;
            white-space: nowrap !important;
        }

        .btn-ghost {
            background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important;
            color: var(--text-main) !important;
            padding: 0 12px !important;
            height: var(--btn-height) !important;
            border-radius: 6px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            white-space: nowrap !important;
        }

        .btn-ghost:hover { background: var(--table-row-hover) !important; border-color: var(--accent-blue) !important; }

        /* Stats Cards */
        .stats-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; }
        .glass-card { background: var(--panel-bg); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-dim); border-radius: 10px; padding: 10px; position: relative; overflow: hidden; animation: fadeInUp 0.5s ease both; box-shadow: var(--card-shadow); }
        .glass-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--accent-cyan), var(--accent-blue), transparent); background-size: 200% 100%; animation: gradientShift 4s ease infinite; opacity: 0.5; }
        .stat-card-inner { display: flex; align-items: center; gap: 10px; padding: 2px !important; }
        .stat-icon { width: 34px !important; height: 34px !important; border-radius: 8px !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 16px !important; }
        .stat-value { font-size: 16px !important; font-weight: 800 !important; color: var(--text-main) !important; line-height: 1 !important; }
        .stat-label { font-size: 8px !important; font-weight: 700 !important; text-transform: uppercase; color: var(--text-dim) !important; margin-bottom: 1px !important; letter-spacing: 0.5px; }

        /* Filter Dashboard */
        .advanced-filter-card { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; background: var(--panel-bg); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-dim); margin-bottom: 12px; animation: fadeInUp 0.6s ease both; box-shadow: var(--card-shadow); }
        .filter-input-group { display: flex; flex-direction: column; gap: 2px; }
        .filter-label { font-size: 7.5px !important; font-weight: 800 !important; text-transform: uppercase !important; color: var(--text-dim) !important; margin-left: 2px !important; letter-spacing: 0.5px; opacity: 0.8; }
        .filter-inp { width: 100% !important; height: 32px !important; background: var(--panel-bg) !important; border: 1px solid var(--border-dim) !important; border-radius: 6px !important; padding: 0 8px 0 30px !important; color: var(--text-main) !important; font-size: 11px !important; font-weight: 500; transition: all 0.2s; }
        .filter-inp:focus { background: var(--panel-bg) !important; border-color: var(--accent-blue) !important; box-shadow: 0 0 0 2px rgba(6,182,212,0.05); }
        .filter-inp option { background: var(--panel-bg); color: var(--text-main); }
        
        .filter-input-wrapper { position: relative; display: flex; align-items: center; }
        .filter-input-wrapper i { position: absolute; left: 10px; font-size: 13px; color: var(--accent-blue); opacity: 0.6; }
 
        .btn-filter-submit { width: 100% !important; height: 32px !important; background: linear-gradient(135deg, #06b6d4, #3b82f6) !important; border: none !important; border-radius: 6px !important; color: #fff !important; font-weight: 800 !important; font-size: 11px !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 4px !important; cursor: pointer !important; text-transform: uppercase !important; letter-spacing: 0.5px; transition: all 0.2s; }
        .btn-filter-reset { width: 100% !important; height: 32px !important; background: var(--input-bg) !important; border: 1px solid var(--border-dim) !important; border-radius: 6px !important; color: var(--text-main) !important; font-weight: 800 !important; font-size: 11px !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 4px !important; cursor: pointer !important; text-transform: uppercase !important; text-decoration: none !important; transition: all 0.2s; }

        /* Table */
        .ovflw-x-scroll { animation: fadeInUp 0.7s ease both; }
        .r-table th { padding: 8px 10px !important; font-size: 9px !important; background: var(--table-header-bg) !important; border-bottom: 1px solid var(--border-dim) !important; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--text-dim); }
        .r-table td { padding: 6px 10px !important; font-size: 11px !important; border-bottom: 1px solid var(--border-dim) !important; vertical-align: middle; color: var(--text-main); }
        .game-icon-wrapper { width: 34px !important; height: 34px !important; border-radius: 8px !important; position: relative; overflow: hidden; flex-shrink: 0; background: var(--input-bg); border: 1px solid var(--border-dim); transition: all 0.3s; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
        .game-icon-premium { width: 100%; height: 100%; object-fit: cover; }
        .img-fallback-premium { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none; align-items: center; justify-content: center; background: var(--input-bg); color: var(--text-dim); }
        .img-fallback-premium i { font-size: 14px; }
 
        .premium-badge { padding: 4px 10px !important; font-size: 8px !important; border-radius: 20px !important; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s; }
        .badge-slots { background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6,182,212,0.2); }
        .badge-casino { background: rgba(244, 63, 94, 0.15); color: #f43f5e; border: 1px solid rgba(244,63,94,0.2); }
        .badge-poker { background: rgba(168, 85, 247, 0.15); color: #a855f7; border: 1px solid rgba(168,85,247,0.2); }
        .badge-fishing { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .badge-casino_lobby { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.2); }
        .badge-turbo { background: rgba(249,115,22,0.15); color: #f97316; border: 1px solid rgba(249,115,22,0.2); }
        .badge-live { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
 
        .status-toggle-premium { width: 36px !important; height: 18px !important; background: var(--input-bg); border-radius: 15px !important; position: relative; cursor: pointer; transition: all 0.4s cubic-bezier(.4,0,.2,1); border: 1px solid var(--border-dim); }
        .status-toggle-premium:hover { border-color: var(--accent-blue); box-shadow: 0 0 12px rgba(16,185,129,0.15); }
        .status-toggle-premium.active { background: linear-gradient(135deg, #10b981, #059669); border-color: rgba(16,185,129,0.4); box-shadow: 0 0 16px rgba(16,185,129,0.25); }
        .toggle-knob { position: absolute; top: 2px; left: 2px; width: 12px !important; height: 12px !important; background: #fff; border-radius: 50%; transition: all 0.4s cubic-bezier(.4,0,.2,1); box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
        .status-toggle-premium.active .toggle-knob { left: 20px !important; }
 
        .feature-star { font-size: 18px !important; color: var(--text-dim); cursor: pointer; opacity: 0.4; transition: all 0.3s cubic-bezier(.4,0,.2,1); }
        .feature-star:hover { opacity: 0.8; transform: scale(1.3); color: #fbbf24; }
        .feature-star.active { color: #fbbf24; opacity: 1; filter: drop-shadow(0 0 6px rgba(251,191,36,0.5)); }
 
        .action-btn-premium { width: 30px !important; height: 30px !important; border-radius: 8px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; transition: all 0.3s cubic-bezier(.4,0,.2,1) !important; border: none !important; font-size: 14px !important; }
        .action-btn-premium:hover { transform: translateY(-2px) scale(1.1); }
        .action-btn-premium.edit { background: var(--input-bg); color: var(--accent-blue); border: 1px solid var(--border-dim); }
        .action-btn-premium.edit:hover { background: var(--table-row-hover); border-color: var(--accent-blue); }
        .action-btn-premium.delete { background: var(--input-bg); color: var(--accent-rose); border: 1px solid var(--border-dim); }
        .action-btn-premium.delete:hover { background: var(--table-row-hover); border-color: var(--accent-rose); }
 
        /* Modals - Premium Glassmorphism */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: none; justify-content: center; align-items: center; z-index: 1000; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay[style*="flex"] { opacity: 1; }
        .modal-content { background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 16px !important; width: 100%; max-width: 420px; padding: 24px !important; box-shadow: var(--card-shadow); color: var(--text-main); animation: modalSlideIn 0.35s cubic-bezier(.4,0,.2,1); }
        
        #bulkModal .modal-content { max-width: 860px !important; }
        #bulkModal textarea { height: 400px !important; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 12px !important; line-height: 1.6; background: var(--input-bg) !important; border: 1px solid var(--border-dim) !important; border-radius: 10px !important; padding: 16px !important; resize: vertical; color: var(--text-main); }
        #bulkModal textarea:focus { border-color: var(--accent-blue) !important; box-shadow: 0 0 20px rgba(59,130,246,0.1); }
 
        .modal-header { margin-bottom: 20px; border-bottom: 1px solid var(--border-dim); padding-bottom: 14px; display: flex; align-items: center; gap: 10px; }
        .modal-header h2 { font-family: var(--font-display); margin: 0; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-main); }
        .modal-header-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: var(--input-bg); color: var(--accent-blue); border: 1px solid var(--border-dim); }
        .form-group { margin-bottom: 14px !important; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 10px !important; font-weight: 700 !important; color: var(--text-dim) !important; text-transform: uppercase; letter-spacing: 0.8px; }
        .cus-inp, .cus-sel { width: 100%; height: 38px !important; background: var(--panel-bg) !important; border: 1px solid var(--border-dim) !important; color: var(--text-main) !important; border-radius: 8px; outline: none; padding: 0 12px; font-size: 12px !important; transition: all 0.3s ease; }
        .cus-inp:focus, .cus-sel:focus { border-color: var(--accent-blue) !important; box-shadow: 0 0 0 3px rgba(59,130,246,0.08), 0 0 16px rgba(59,130,246,0.08); }
        .cus-sel option { background: var(--panel-bg); color: var(--text-main); }
        .cus-inp::placeholder { color: rgba(148,163,184,0.4); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.05); }

        /* Animations */
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-20px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .animate-spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* Premium Button Hover Effects */
        .btn-premium:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(6, 182, 212, 0.35) !important; }
        .btn-ghost:hover { transform: translateY(-1px); }
        .btn-filter-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(59,130,246,0.3) !important; }

        /* Table row animation */
        .premium-row { transition: all 0.3s cubic-bezier(.4,0,.2,1); }
        .premium-row:hover { background: var(--table-row-hover) !important; transform: translateX(3px); }
        .game-icon-wrapper { width: 34px !important; height: 34px !important; border-radius: 8px !important; position: relative; overflow: hidden; flex-shrink: 0; background: var(--input-bg); border: 1px solid var(--border-dim); transition: all 0.3s; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
        .premium-row:hover .game-icon-wrapper { border-color: var(--accent-blue); box-shadow: 0 0 12px rgba(59,130,246,0.15); }

        /* Stat card hover */
        .glass-card { transition: all 0.3s cubic-bezier(.4,0,.2,1); }
        .glass-card:hover { border-color: var(--accent-blue); transform: translateY(-2px); box-shadow: var(--card-shadow); }

        /* Drag handle */
        .drag-handle { cursor: grab; color: var(--text-dim); opacity: 0.4; font-size: 16px; transition: all 0.2s; display: flex; align-items: center; }
        .drag-handle:hover { opacity: 1; color: var(--accent-blue); }
        .drag-handle:active { cursor: grabbing; }
        .premium-row.sortable-ghost { opacity: 0.4; background: var(--table-row-hover) !important; }
        .premium-row.sortable-chosen { background: var(--table-row-hover) !important; box-shadow: var(--card-shadow); }
        .sort-num { font-size: 10px; color: var(--text-dim); font-weight: 600; min-width: 24px; text-align: center; }

        @media (max-width: 1024px) {
            .admin-main-content { margin-left: 0 !important; padding: 10px !important; }
        }

        @media (max-width: 768px) {
            .stats-container { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
            .dash-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .dash-header-right { display: grid !important; grid-template-columns: 1fr 1fr !important; width: 100% !important; gap: 6px !important; }
            .dash-header-right .btn-premium { grid-column: span 2 !important; }
            .modal-content { margin: 16px; max-width: calc(100% - 32px) !important; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="admin-layout-wrapper">
        <?php include "../components/side-menu.php" ?>
        
        <div class="admin-main-content">
            <!-- Accent Bar -->
            <div style="height: 2px; background: linear-gradient(90deg, #06b6d4, #3b82f6, #8b5cf6, #3b82f6, #06b6d4); background-size: 300% 100%; animation: gradientShift 6s ease infinite; border-radius: 4px; margin-bottom: 12px; opacity: 0.5;"></div>
            
            <div class="dash-header" style="animation: fadeInUp 0.3s ease both;">
                <div class="dash-header-left">
                    <div class="dash-menu-btn menu-open-btn"><i class='bx bx-menu'></i></div>
                    <div>
                        <span class="dash-breadcrumb">Management > Game Library</span>
                        <h1 class="dash-title">Game Library</h1>
                    </div>
                </div>
                <div class="dash-header-right">
                    <button class="btn-ghost" onclick="openBulkModal()">
                        <i class='bx bx-layer-plus'></i> Bulk Manage
                    </button>
                    <button class="btn-ghost" onclick="syncFromJSON()">
                        <i class='bx bx-refresh'></i> Sync Data
                    </button>
                    <button class="btn-ghost" onclick="normalizeOrder()">
                        <i class='bx bx-sort-alt-2'></i> Normalize Order
                    </button>
                    <button class="btn-premium" onclick="openAddModal()">
                        <i class='bx bx-plus'></i> Add New Game
                    </button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-container">
                <?php
                $total_games = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_games"))['c'];
                $active_games = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_games WHERE game_status = 1"))['c'];
                $featured_games = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_games WHERE is_featured = 1"))['c'];
                $categories_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT game_category) as c FROM tbl_games"))['c'];
                ?>
                <div class="glass-card stat-card-inner" style="animation-delay: 0.1s;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(6,182,212,0.15), rgba(59,130,246,0.15)); color: #06b6d4;">
                        <i class='bx bx-joystick'></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Games</div>
                        <div class="stat-value"><?php echo number_format($total_games); ?></div>
                    </div>
                </div>
                <div class="glass-card stat-card-inner" style="animation-delay: 0.2s;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(52,211,153,0.15)); color: #10b981;">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Games</div>
                        <div class="stat-value"><?php echo number_format($active_games); ?></div>
                    </div>
                </div>
                <div class="glass-card stat-card-inner" style="animation-delay: 0.3s;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(251,191,36,0.15), rgba(245,158,11,0.15)); color: #fbbf24;">
                        <i class='bx bxs-star'></i>
                    </div>
                    <div>
                        <div class="stat-label">Featured</div>
                        <div class="stat-value"><?php echo number_format($featured_games); ?></div>
                    </div>
                </div>
                <div class="glass-card stat-card-inner" style="animation-delay: 0.4s;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(168,85,247,0.15), rgba(139,92,246,0.15)); color: #a855f7;">
                        <i class='bx bx-category'></i>
                    </div>
                    <div>
                        <div class="stat-label">Categories</div>
                        <div class="stat-value"><?php echo number_format($categories_count); ?></div>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters -->
            <form method="GET" class="advanced-filter-card">
                <div class="filter-input-group">
                    <label class="filter-label">Search Games</label>
                    <div class="filter-input-wrapper">
                        <i class='bx bx-search'></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="filter-inp" placeholder="Name or UID...">
                    </div>
                </div>
                <div class="filter-input-group">
                    <label class="filter-label">Category</label>
                    <div class="filter-input-wrapper">
                        <i class='bx bx-category-alt'></i>
                        <select name="category" class="filter-inp">
                            <option value="">All Categories</option>
                            <?php 
                            mysqli_data_seek($cat_result, 0);
                            while($cat = mysqli_fetch_assoc($cat_result)): ?>
                                <option value="<?php echo $cat['game_category']; ?>" <?php echo $category == $cat['game_category'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($cat['game_category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-input-group">
                    <label class="filter-label">Status</label>
                    <div class="filter-input-wrapper">
                        <i class='bx bx-check-shield'></i>
                        <select name="status" class="filter-inp">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn-filter-submit"><i class='bx bx-filter-alt'></i> Apply</button>
                    <a href="index.php" class="btn-filter-reset"><i class='bx bx-reset'></i> Reset</a>
                </div>
            </form>

            <!-- Listing Info -->
            <div class="glass-card" style="padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #3b82f6; animation: fadeInUp 0.65s ease both;">
                <div class="listing-info-premium" style="display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-list-ul' style="font-size: 16px; color: #3b82f6;"></i>
                    <span style="font-size: 12px;">Showing <b style="color: #3b82f6;"><?php echo mysqli_num_rows($result); ?></b> Game Records</span>
                </div>
                <div style="display: flex; gap: 6px;">
                    <button class="btn-ghost" onclick="exportToCSV()" style="height: 28px !important; font-size: 9px !important; padding: 0 8px !important;">
                        <i class='bx bx-file'></i> CSV
                    </button>
                    <button class="btn-ghost" onclick="exportToExcel()" style="height: 28px !important; font-size: 9px !important; padding: 0 8px !important;">
                        <i class='bx bx-spreadsheet'></i> EXCEL
                    </button>
                    <button class="btn-ghost" onclick="exportToPDF()" style="height: 28px !important; font-size: 9px !important; padding: 0 8px !important;">
                        <i class='bx bxs-file-pdf'></i> PDF
                    </button>
                </div>
            </div>

            <!-- Main Table -->
            <div class="ovflw-x-scroll">
                <table class="r-table" style="width: 100%; border-collapse: separate; border-spacing: 0 6px;">
                    <thead>
                        <tr>
                            <th style="width: 30px;"></th>
                            <th style="width: 40px;">No</th>
                            <th>Game Info</th>
                            <th>Category</th>
                            <th>Provider</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: center;">Featured</th>
                            <th style="width: 50px; text-align: center;">Order</th>
                            <th style="width: 100px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($result)): 
                            $cat_class = 'badge-' . strtolower($row['game_category']);
                        ?>
                        <tr class="premium-row" data-id="<?php echo $row['id']; ?>">
                            <td><div class="drag-handle"><i class='bx bx-grid-vertical'></i></div></td>
                            <td style="color: var(--text-dim); font-weight: 700;"><?php echo str_pad($no++, 2, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="game-icon-wrapper">
                                        <img src="<?php echo htmlspecialchars($row['game_image']); ?>" class="game-icon-premium" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="img-fallback-premium"><i class='bx bx-image'></i></div>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main); margin-bottom: 1px;"><?php echo $row['game_name']; ?></div>
                                        <div style="font-size: 9px; color: var(--text-dim); font-family: monospace;"><?php echo $row['game_uid']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="premium-badge <?php echo $cat_class; ?>"><?php echo $row['game_category']; ?></span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 4px; color: var(--text-dim); font-size: 10px;">
                                    <i class='bx bx-cube-alt' style="opacity: 0.5;"></i>
                                    <span><?php echo $row['game_provider']; ?></span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center;">
                                    <div class="status-toggle-premium <?php echo $row['game_status'] == 1 ? 'active' : ''; ?>" onclick="toggleStatus(<?php echo $row['id']; ?>, this)">
                                        <div class="toggle-knob"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center;">
                                    <i class='bx bxs-star feature-star <?php echo $row['is_featured'] == 1 ? 'active' : ''; ?>' onclick="toggleFeatured(<?php echo $row['id']; ?>, this)"></i>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <input type="text" class="sort-num-input" value="<?php echo $row['sort_order']; ?>" readonly style="width: 34px; height: 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: var(--accent-blue); text-align: center; font-size: 10px; font-weight: 700; outline: none; cursor: default;">
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button class="action-btn-premium edit" onclick="editGame(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class='bx bx-edit-alt'></i>
                                    </button>
                                    <button class="action-btn-premium delete" onclick="deleteGame(<?php echo $row['id']; ?>)">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-icon"><i class='bx bx-joystick'></i></div>
                <h2>Add New Game</h2>
            </div>
            <form id="addGameForm" onsubmit="saveGame(event)">
                <input type="hidden" name="action" value="save_game">
                <div class="form-group">
                    <label>Game Name</label>
                    <input type="text" name="game_name" class="cus-inp" required>
                </div>
                <div class="form-group">
                    <label>Game UID</label>
                    <input type="text" name="game_uid" class="cus-inp" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="game_category" class="cus-sel" required>
                            <option value="slots">Slots</option>
                            <option value="casino">Casino</option>
                            <option value="turbo">Turbo</option>
                            <option value="fishing">Fishing</option>
                            <option value="poker">Poker</option>
                            <option value="live">Live</option>
                            <option value="casino_lobby">Casino Lobby</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Provider</label>
                        <input type="text" name="game_provider" class="cus-inp" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Icon URL</label>
                    <input type="text" name="game_image" class="cus-inp" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-premium">Save Game</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Manage Modal -->
    <div id="bulkModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-icon"><i class='bx bx-layer-plus'></i></div>
                <h2>Bulk Game Import</h2>
            </div>
            <div class="form-group">
                <label>Paste your JSON array below</label>
                <textarea id="bulkJsonInput" class="cus-inp" style="height: 450px; resize: vertical; padding: 12px; font-family: 'Consolas', monospace; font-size: 12px; line-height: 1.5; white-space: pre;" placeholder='[
  {
    "name": "Game Name",
    "uid": "game_uid",
    "category": "slots",
    "provider": "ProviderName",
    "icon": "https://..."
  },
  ...
]'></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" onclick="closeModal('bulkModal')">Cancel</button>
                <button type="button" class="btn-premium" onclick="processBulkImport()">Import Games</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('addGameForm').reset();
            document.querySelector('#addModal h2').innerText = 'Add New Game';
            document.querySelector('#addGameForm input[name="action"]').value = 'save_game';
            document.getElementById('addModal').style.display = 'flex';
        }

        function openBulkModal() {
            document.getElementById('bulkJsonInput').value = '';
            document.getElementById('bulkModal').style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function editGame(data) {
            const form = document.getElementById('addGameForm');
            form.querySelector('input[name="action"]').value = 'save_game';
            
            // Add ID for update
            let idInput = form.querySelector('input[name="id"]');
            if(!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                form.appendChild(idInput);
            }
            idInput.value = data.id;

            form.querySelector('input[name="game_name"]').value = data.game_name;
            form.querySelector('input[name="game_uid"]').value = data.game_uid;
            form.querySelector('select[name="game_category"]').value = data.game_category;
            form.querySelector('input[name="game_provider"]').value = data.game_provider;
            form.querySelector('input[name="game_image"]').value = data.game_image;

            document.querySelector('#addModal h2').innerText = 'Edit Game Details';
            document.getElementById('addModal').style.display = 'flex';
        }

        function saveGame(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            fetch('update_logic.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Swal.fire('Error', data.message || 'Action failed', 'error');
                }
            });
        }

        function toggleStatus(id, el) {
            const current = el.classList.contains('active') ? 0 : 1;
            fetch('update_logic.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle_status', id: id, status: current })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    el.classList.toggle('active');
                    Swal.fire({ icon: 'success', title: 'Status Updated', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                }
            });
        }

        function toggleFeatured(id, el) {
            const current = el.classList.contains('active') ? 0 : 1;
            fetch('update_logic.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle_featured', id: id, featured: current })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    el.classList.toggle('active');
                    Swal.fire({ icon: 'success', title: 'Featured Status Updated', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                }
            });
        }

        // Drag & Drop Reorder
        const tbody = document.querySelector('.r-table tbody');
        if (tbody) {
            new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function() {
                    const rows = tbody.querySelectorAll('.premium-row');
                    const order = [];
                    rows.forEach((row, idx) => {
                        const id = row.dataset.id;
                        const newPos = idx + 1;
                        order.push({ id: parseInt(id), pos: newPos });
                        
                        // Update visible sort number
                        const sortInp = row.querySelector('.sort-num-input');
                        if (sortInp) sortInp.value = newPos;
                        
                        // Update visible row number
                        row.children[1].textContent = String(newPos).padStart(2, '0');
                    });
                    
                    fetch('update_logic.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reorder', order: order })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Order Updated', 
                                toast: true, 
                                position: 'top-end', 
                                showConfirmButton: false, 
                                timer: 1500,
                                background: 'rgba(15, 23, 42, 0.9)',
                                color: '#fff'
                            });
                        }
                    });
                }
            });
        }

        function normalizeOrder() {
            Swal.fire({
                title: 'Normalize Sort Order?',
                text: "This will re-sequence all games from 1, 2, 3... based on current position.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Yes, Normalize'
            }).then((result) => {
                if (result.isConfirmed) {
                    const rows = document.querySelectorAll('.premium-row');
                    const order = [];
                    rows.forEach((row, idx) => {
                        order.push({ id: parseInt(row.dataset.id), pos: idx + 1 });
                    });
                    
                    fetch('update_logic.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reorder', order: order })
                    }).then(() => location.reload());
                }
            });
        }

        function updateSort(id, val) {
            const formData = new FormData();
            formData.append('action', 'update_sort');
            formData.append('id', id);
            formData.append('val', val);
            
            fetch('update_logic.php', {
                method: 'POST',
                body: formData
            });
        }

        function deleteGame(id) {
            Swal.fire({
                title: 'Delete Game?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_logic.php', {
                        method: 'POST',
                        body: new URLSearchParams({ action: 'delete_game', id: id })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            location.reload();
                        }
                    });
                }
            });
        }

        function syncFromJSON() {
            Swal.fire({
                title: 'Syncing Data...',
                text: 'Importing games from frontend JSON files',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('import_from_json.php')
            .then(res => res.json())
            .then(data => {
                Swal.fire({
                    icon: 'success',
                    title: 'Sync Complete',
                    text: `Imported ${data.imported} games. Errors: ${data.errors}`,
                    confirmButtonText: 'Great!'
                }).then(() => location.reload());
            });
        }

        function processBulkImport() {
            const input = document.getElementById('bulkJsonInput').value;
            if(!input.trim()) return;

            fetch('update_logic.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'bulk_import', data: input })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('Success', `Imported ${data.count} games`, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }

        function exportToCSV() {
            let csv = 'No,Name,UID,Category,Provider,Status,Featured\n';
            const rows = document.querySelectorAll('.premium-row');
            rows.forEach((row, index) => {
                const name = row.querySelector('div[style*="font-weight: 800"]').innerText;
                const uid = row.querySelector('div[style*="font-size: 9px"]').innerText;
                const cat = row.querySelector('.premium-badge').innerText;
                const prov = row.querySelector('div[style*="color: var(--text-dim); font-size: 10px"] span').innerText;
                const status = row.querySelector('.status-toggle-premium').classList.contains('active') ? 'Active' : 'Inactive';
                const featured = row.querySelector('.feature-star').classList.contains('active') ? 'Yes' : 'No';
                csv += `${index + 1},"${name}","${uid}","${cat}","${prov}","${status}","${featured}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'game_library.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function exportToExcel() {
            const data = [];
            const rows = document.querySelectorAll('.premium-row');
            rows.forEach((row, index) => {
                data.push({
                    'No': index + 1,
                    'Game Name': row.querySelector('div[style*="font-weight: 800"]').innerText,
                    'UID': row.querySelector('div[style*="font-size: 9px"]').innerText,
                    'Category': row.querySelector('.premium-badge').innerText,
                    'Provider': row.querySelector('div[style*="color: var(--text-dim); font-size: 10px"] span').innerText,
                    'Status': row.querySelector('.status-toggle-premium').classList.contains('active') ? 'Active' : 'Inactive',
                    'Featured': row.querySelector('.feature-star').classList.contains('active') ? 'Yes' : 'No'
                });
            });

            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Games");
            XLSX.writeFile(workbook, "game_library.xlsx");
        }

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            
            doc.setFontSize(18);
            doc.text('Winco Admin - Game Library', 14, 22);
            doc.setFontSize(11);
            doc.setTextColor(100);
            doc.text(`Exported on: ${new Date().toLocaleString()}`, 14, 30);

            const rows = [];
            document.querySelectorAll('.premium-row').forEach((row, index) => {
                rows.push([
                    index + 1,
                    row.querySelector('div[style*="font-weight: 800"]').innerText,
                    row.querySelector('div[style*="font-size: 9px"]').innerText,
                    row.querySelector('.premium-badge').innerText,
                    row.querySelector('div[style*="color: var(--text-dim); font-size: 10px"] span').innerText,
                    row.querySelector('.status-toggle-premium').classList.contains('active') ? 'Active' : 'Inactive',
                    row.querySelector('.feature-star').classList.contains('active') ? 'Yes' : 'No'
                ]);
            });

            doc.autoTable({
                head: [['No', 'Game Name', 'UID', 'Category', 'Provider', 'Status', 'Featured']],
                body: rows,
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [59, 130, 246] },
                alternateRowStyles: { fillColor: [245, 247, 250] }
            });

            doc.save('game_library.pdf');
        }

        // Sidebar Toggle Logic
        document.querySelector('.menu-open-btn').addEventListener('click', () => {
            document.querySelector('.admin-layout-wrapper').classList.toggle('sidebar-collapsed');
        });
    </script>
</body>
</html>
