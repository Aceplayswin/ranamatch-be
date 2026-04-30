<?php
$host_url = "https://$_SERVER[HTTP_HOST]/admin/";
$page_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$page_url_name = trim(str_replace($host_url, '', strtok($page_url, '?')), '/');
?>

<style>
<?php include __DIR__ . "/theme-variables.php"; ?>
.menu-bar-view {
    background: var(--side-bg) !important;
    border-right: 1px solid var(--border-dim) !important;
    width: 260px;
    height: 100vh;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    font-family: var(--font-body);
    transition: all 0.3s ease;
    z-index: 1000;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 10px 0 30px rgba(0,0,0,0.1);
}
.menu-bar-view::-webkit-scrollbar { width: 4px; }
.menu-bar-view::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.menu-bar-view::-webkit-scrollbar-track { background: transparent; }

.side-logo-area {
    padding: 10px 12px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.side-logo-text {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.menu-close-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    color: var(--text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.menu-close-btn:hover { background: var(--table-row-hover); }

.nav-section-label {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--text-dim);
    margin: 32px 18px 12px;
    opacity: 0.6;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 18px;
    margin: 6px 0;
    border-radius: 12px;
    text-decoration: none !important;
    color: var(--side-text);
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.nav-link i {
    font-size: 20px;
    margin-right: 12px;
    transition: transform 0.2s ease;
}

.nav-link:hover {
    background: var(--table-row-hover);
    color: var(--side-text-active);
    transform: translateX(4px);
}

.nav-link:hover i { transform: scale(1.1); }

.nav-link.menu-active-btn {
    background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
}

.nav-link.menu-active-btn i {
    color: #ffffff !important;
}

.nav-link.menu-active-btn::after {
    display: none;
}

.nav-divider {
    height: 1px;
    background: var(--side-border);
    margin: 15px 12px;
}

.nav-logout {
    margin-top: auto;
    color: #f87171 !important;
}
.nav-logout:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* Mobile Responsive Logic */
@media (max-width: 1024px) {
    .menu-bar-view {
        left: -280px !important;
        box-shadow: none !important;
    }
    .menu-bar-view.show {
        left: 0 !important;
        box-shadow: 20px 0 50px rgba(0,0,0,0.5) !important;
    }
    .menu-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .menu-backdrop.show {
        display: block !important;
        opacity: 1 !important;
    }
    .mobile-show-btn { display: flex !important; }
    .mobile-hide-btn { display: none !important; }
}
</style>

<script>
    function toggleGlobalSearch() {
        const wrapper = document.getElementById('sideSearchWrapper');
        const input = document.getElementById('adminGlobalSearch');
        if (wrapper && input) {
            const isHidden = wrapper.style.display === 'none';
            wrapper.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
                setTimeout(() => input.focus(), 50);
            }
        }
    }
</script>

<div class="menu-bar-view hide-native-scrollbar">
    
    <div class="side-logo-area">
        <div style="display: flex; align-items: center; gap: 10px;">
            <?php 
                if(!defined("ACCESS_SECURITY")) define("ACCESS_SECURITY", "true");
                $dots = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
                include_once $dots . 'security/config.php';
                include_once $dots . 'security/constants.php';
                $logo_src = (strpos($APP_LOGO, 'http') === 0) ? $APP_LOGO : $dots . $APP_LOGO;
                // If it's just the dots, use a fallback
                if($logo_src == $dots) $logo_src = $dots . "wincologo.png";
            ?>
            <img src="<?php echo $logo_src; ?>" alt="Logo" style="width: 32px; height: 32px; object-fit: contain;" onerror="this.src='<?php echo $dots; ?>favicon.ico'">
            <span class="side-logo-text"><?php echo strtoupper($APP_NAME); ?> </span>
        </div>
        <div class="d-flex gap-2">
            <div class="menu-close-btn" onclick="toggleGlobalSearch()" title="Search Menu" style="cursor: pointer;">
                <i class='bx bx-search' style="pointer-events: none;"></i>
            </div>
            <div class="menu-close-btn" onclick="themeToggle.toggle()" title="Toggle Theme">
                <i class='bx bx-moon theme-icon-dark'></i>
                <i class='bx bx-sun theme-icon-light'></i>
            </div>
            <div class="menu-open-btn menu-close-btn mobile-hide-btn"><i class='bx bx-chevron-left'></i></div>
            <div class="menu-open-btn menu-close-btn mobile-show-btn" style="display:none;"><i class='bx bx-x'></i></div>
        </div>
    </div>

    <!-- Global Search Section -->
    <div id="sideSearchWrapper" style="display: none; padding: 0 16px 15px;">
        <div class="search-input-container">
            <i class='bx bx-search search-icon'></i>
            <input type="text" id="adminGlobalSearch" placeholder="Search menu..." autocomplete="off">
            <div id="searchSuggestions" class="search-suggestions-dropdown"></div>
        </div>
    </div>

    <style>
        .search-input-container {
            position: relative;
            display: flex;
            align-items: center;
            background: var(--input-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 0 14px;
            height: 42px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        .search-input-container:focus-within {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.15), inset 0 2px 4px rgba(0,0,0,0.05);
            background: var(--panel-bg);
        }
        #adminGlobalSearch {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            font-size: 14px;
            width: 100%;
            height: 100%;
            font-weight: 500;
        }
        .search-suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 16px;
            right: 16px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            max-height: 320px;
            overflow-y: auto;
            z-index: 2000;
            display: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            margin-top: 8px;
            padding: 8px;
        }
        /* Custom scrollbar to remove "ticker" look */
        .search-suggestions-dropdown::-webkit-scrollbar {
            width: 5px;
        }
        .search-suggestions-dropdown::-webkit-scrollbar-track {
            background: transparent;
        }
        .search-suggestions-dropdown::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .search-suggestions-dropdown::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .suggestion-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s;
            margin-bottom: 4px;
            border: 1px solid transparent;
        }
        .suggestion-item:last-child {
            margin-bottom: 0;
        }
        .suggestion-item:hover, .suggestion-item.selected {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .suggestion-item i {
            width: 40px;
            height: 40px;
            background: rgba(6, 182, 212, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--accent-blue);
            margin-right: 16px;
            transition: all 0.3s;
        }
        .suggestion-item:hover i {
            background: var(--accent-blue);
            color: #fff;
            transform: scale(1.1);
        }
        .suggestion-info {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .suggestion-title {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .suggestion-path-badge {
            display: inline-block;
            font-size: 9px;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 4px;
            font-weight: 800;
            background: rgba(6, 182, 212, 0.15);
            padding: 2px 8px;
            border-radius: 4px;
            width: fit-content;
        }
        
        /* Unified Custom Scrollbar */
        .hide-native-scrollbar::-webkit-scrollbar,
        .search-suggestions-dropdown::-webkit-scrollbar {
            width: 4px;
        }
        .hide-native-scrollbar::-webkit-scrollbar-track,
        .search-suggestions-dropdown::-webkit-scrollbar-track {
            background: transparent;
        }
        .hide-native-scrollbar::-webkit-scrollbar-thumb,
        .search-suggestions-dropdown::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }
        .hide-native-scrollbar::-webkit-scrollbar-thumb:hover,
        .search-suggestions-dropdown::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }
        #adminGlobalSearch {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 500;
            width: 100%;
            height: 38px;
        }
        #adminGlobalSearch::placeholder { color: var(--text-dim); opacity: 0.5; }
        
        .search-suggestions-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--panel-bg);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 2000;
            display: none;
            box-shadow: var(--card-shadow);
            animation: fadeInUp 0.2s ease;
        }
        .suggestion-item {
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid var(--border-dim);
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover, .suggestion-item.selected {
            background: var(--table-row-hover);
        }
        .suggestion-item i { font-size: 18px; width: 24px; text-align: center; }
        .suggestion-info { display: flex; flex-direction: column; }
        .suggestion-title { font-size: 12px; font-weight: 600; color: var(--text-main); }
        .suggestion-path { font-size: 9px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
        
    <nav>
        <a href="<?php echo $host_url; ?>dashboard" class="nav-link <?php if ($page_url_name == 'dashboard') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-grid-alt' style='color:#3b82f6;'></i>
            Dashboard
        </a>

        <div class="nav-section-label">Gaming Control</div>
        
        <a href="<?php echo $host_url; ?>manage-games" class="nav-link <?php if (strpos($page_url_name, 'manage-games') !== false) {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-joystick' style='color:#8b5cf6;'></i>
            Manage Games
        </a>
        
        <a href="<?php echo $host_url; ?>chart" class="nav-link <?php if ($page_url_name == 'chart') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-line-chart' style='color:#f59e0b;'></i>
            Chart data
        </a>

        <div class="nav-section-label">User Management</div>
            
        <a href="<?php echo $host_url; ?>users-data" class="nav-link <?php if ($page_url_name == 'users-data') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-group' style='color:#6366f1;'></i>
            Users Data
        </a>
        
        <a href="<?php echo $host_url; ?>users-data/index1.php" class="nav-link <?php if ($page_url_name == 'users-data/index1.php') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-list-ol' style='color:#f59e0b;'></i>
            Top User Balances
        </a>

        <div class="nav-section-label">Add Bonus</div>
        
        <a href="<?php echo $host_url; ?>manage-bonus/create-bonus" class="nav-link <?php if ($page_url_name == 'manage-bonus/create-bonus') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-plus-circle' style='color:#10b981;'></i>
            Create Bonus
        </a>
        
        <a href="<?php echo $host_url; ?>manage-bonus/bonus-list" class="nav-link <?php if ($page_url_name == 'manage-bonus/bonus-list') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-list-ul' style='color:#3b82f6;'></i>
            Bonus List
        </a>

        <a href="<?php echo $host_url; ?>manage-bonus/explore-bonus" class="nav-link <?php if ($page_url_name == 'manage-bonus/explore-bonus') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-refresh' style='color:#f59e0b;'></i>
            Explore Bonus
        </a>

        <a href="<?php echo $host_url; ?>manage-bonus/create-cashback" class="nav-link <?php if ($page_url_name == 'manage-bonus/create-cashback') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bxs-coupon' style='color:#8b5cf6;'></i>
            Create Cashback
        </a>

        <a href="<?php echo $host_url; ?>manage-bonus/cashback-list.php" class="nav-link <?php if ($page_url_name == 'manage-bonus/cashback-list.php') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-coin-stack' style='color:#14b8a6;'></i>
            Cashback List
        </a>

        <a href="<?php echo $host_url; ?>manage-bonus/cashback-process.php" class="nav-link <?php if ($page_url_name == 'manage-bonus/cashback-process.php') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-rocket' style='color:#f43f5e;'></i>
            Process Cashback
        </a>

        <div class="nav-section-label">History & Records</div>
            
        <a href="<?php echo $host_url; ?>recently-played-top" class="nav-link <?php if ($page_url_name == 'recently-played-top') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-trophy' style='color:#ec4899;'></i>
            Top Bet Records
        </a>
        
        <a href="<?php echo $host_url; ?>recent-played" class="nav-link <?php if ($page_url_name == 'recent-played') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-history' style='color:#8b5cf6;'></i>
            Recently Played
        </a>
            
        <a href="<?php echo $host_url; ?>casino-bet-history" class="nav-link <?php if ($page_url_name == 'casino-bet-history') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-dice-5' style='color:#f43f5e;'></i>
            Casino History
        </a>

        <a href="<?php echo $host_url; ?>sports-bet-history" class="nav-link <?php if ($page_url_name == 'sports-bet-history') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-football' style='color:#14b8a6;'></i>
            Sports History
        </a>


        <div class="nav-section-label">Finance & Stats</div>
            
        <a href="<?php echo $host_url; ?>recharge-records" class="nav-link <?php if ($page_url_name == 'recharge-records') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-trending-up' style='color:#22c55e;'></i>
            Recharge Records
        </a>
        <a href="<?php echo $host_url; ?>manual-withdraw-records" class="nav-link <?php if ($page_url_name == 'manual-withdraw-records') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-wallet' style='color:#ef4444;'></i>
            Manual Withdraw
        </a>
        
        <a href="<?php echo $host_url; ?>withdraw-records" class="nav-link <?php if ($page_url_name == 'withdraw-records') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-receipt' style='color:#8b5cf6;'></i>
            Withdraw Records
        </a>

        <a href="<?php echo $host_url; ?>withdraw-statistics" class="nav-link <?php if ($page_url_name == 'withdraw-statistics') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-stats' style='color:#f97316;'></i>
            Withdraw Stats
        </a>

        <a href="<?php echo $host_url; ?>changebank" class="nav-link <?php if ($page_url_name == 'changebank') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bxs-bank' style='color:#0ea5e9;'></i>
            Change Bank
        </a>

        <a href="<?php echo $host_url; ?>game-statistics" class="nav-link <?php if ($page_url_name == 'game-statistics') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-pie-chart-alt-2' style='color:#06b6d4;'></i>
            Game Statistics
        </a>

        <a href="<?php echo $host_url; ?>manage-withdraw" class="nav-link <?php if ($page_url_name == 'manage-withdraw') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-wallet-alt' style='color:#14b8a6;'></i>
            Bet Add Withdraw
        </a>
        
        <a href="<?php echo $host_url; ?>manage-salary" class="nav-link <?php if ($page_url_name == 'manage-salary') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-briefcase' style='color:#3b82f6;'></i>
            Manage Salary
        </a>
        
        <div class="nav-section-label">Reports & Analytics</div>
            
        <a href="<?php echo $host_url; ?>reports" class="nav-link <?php if ($page_url_name == 'reports') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-file' style='color:#06b6d4;'></i>
            Downloadable Report
        </a>

        <div class="nav-section-label">System & Tools</div>
            
        <a href="<?php echo $host_url; ?>manage-rewards" class="nav-link <?php if ($page_url_name == 'manage-rewards') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-gift' style='color:#ec4899;'></i>
            Manage Rewards
        </a>

        <?php /*
        <a href="<?php echo $host_url; ?>manage-promotions" class="nav-link <?php if(strpos($page_url_name, 'manage-promotions') !== false) { echo 'menu-active-btn'; }?>">
            <i class='bx bx-party' style='color:#facc15;'></i>
            Manage Promotions
        </a>
        */ ?>

        <?php /*
        <a href="<?php echo $host_url; ?>manage-sliders" class="nav-link <?php if ($page_url_name == 'manage-sliders') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-images' style='color:#8b5cf6;'></i>
            Manage Sliders
        </a>
        */ ?>

        <a href="<?php echo $host_url; ?>send-message" class="nav-link <?php if ($page_url_name == 'send-message') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-message-square-dots' style='color:#10b981;'></i>
            Send Message
        </a>

        <a href="<?php echo $host_url; ?>support-tickets" class="nav-link <?php if (strpos($page_url_name, 'support-tickets') !== false) {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-support' style='color:#3b82f6;'></i>
            Support Tickets
        </a>

        <a href="<?php echo $host_url; ?>manage-admins" class="nav-link <?php if ($page_url_name == 'manage-admins') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-user-plus' style='color:#f59e0b;'></i>
            Manage Admins
        </a>


        <?php /*
        <a href="http://api.<?php echo strtolower($APP_NAME); ?>.site/payments/bharatpe/manager/?mode=prod-9874-mode" class="nav-link" target="_blank">
            <i class='bx bx-credit-card' style='color:#3b82f6;'></i>
            Manage Payments
        </a>
        */ ?>
        
        <a href="<?php echo $host_url; ?>manage-settings/site-branding.php" class="nav-link <?php if ($page_url_name == 'manage-settings/site-branding.php') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-paint-roll' style='color:#E6A000;'></i>
            Site Branding
        </a>

        <a href="<?php echo $host_url; ?>manage-settings" class="nav-link <?php if ($page_url_name == 'manage-settings') {
    echo 'menu-active-btn';
}?>">
            <i class='bx bx-cog' style='color:#64748b;'></i>
            Settings
        </a>
        
        <a href="<?php echo $host_url; ?>logout-account" class="nav-link nav-logout">
            <i class='bx bx-log-out-circle' style='color:#ef4444;'></i>
            Logout
        </a>
    </nav>
</div>
<!-- Mobile Navigation Backdrop -->
<div class="menu-backdrop" id="sideMenuBackdrop"></div>

<script>
/**
 * Admin Navigation & Global Search System
 */
const adminNav = {
    init: function() {
        this.sidebar = document.querySelector('.menu-bar-view');
        this.backdrop = document.getElementById('sideMenuBackdrop');
        
        this.injectToggle();
        this.bindEvents();
    },
    
    injectToggle: function() {
        if (!document.querySelector('.admin-mobile-toggle')) {
            const toggle = document.createElement('div');
            toggle.className = 'mobile-nav-toggle admin-mobile-toggle';
            toggle.innerHTML = "<i class='bx bx-menu-alt-left'></i>";
            document.body.appendChild(toggle);
            toggle.addEventListener('click', () => this.setMenu(true));
        }
    },
    
    setMenu: function(isOpen) {
        if (this.sidebar) {
            if (isOpen) {
                this.sidebar.classList.add('show');
                this.backdrop.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                this.sidebar.classList.remove('show');
                this.backdrop.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    },
    
    bindEvents: function() {
        // Mobile Controls
        if (this.backdrop) {
            this.backdrop.onclick = () => this.setMenu(false);
        }
        
        const openBtns = document.querySelectorAll('.menu-open-btn');
        openBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setMenu(true);
            });
        });

        const closeBtns = document.querySelectorAll('.menu-close-btn');
        closeBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setMenu(false);
            });
        });

        // Global Search Logic
        const searchInput = document.getElementById('adminGlobalSearch');
        const suggestionsBox = document.getElementById('searchSuggestions');
        const searchWrapper = document.getElementById('sideSearchWrapper');
        let menuItems = [];

        // Index all menu items dynamically
        document.querySelectorAll('.nav-link:not(.nav-logout)').forEach(link => {
            const title = link.textContent.trim();
            const icon = link.querySelector('i')?.className || 'bx bx-link';
            const url = link.href;
            const section = link.previousElementSibling?.classList.contains('nav-section-label') 
                ? link.previousElementSibling.textContent.trim() 
                : 'General';
            
            menuItems.push({ title, icon, url, section });
        });

        searchInput?.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            if (!query) {
                suggestionsBox.style.display = 'none';
                return;
            }

            const matches = menuItems.filter(item => 
                item.title.toLowerCase().includes(query) || 
                item.section.toLowerCase().includes(query)
            ).slice(0, 8);

            if (matches.length > 0) {
                suggestionsBox.innerHTML = matches.map((item, idx) => `
                    <div class="suggestion-item" data-url="${item.url}" data-index="${idx}">
                        <i class="${item.icon}"></i>
                        <div class="suggestion-info">
                            <span class="suggestion-title">${item.title}</span>
                            <span class="suggestion-path-badge">${item.section}</span>
                        </div>
                    </div>
                `).join('');
                suggestionsBox.style.display = 'block';
            } else {
                suggestionsBox.style.display = 'none';
            }
        });

        suggestionsBox?.addEventListener('click', (e) => {
            const item = e.target.closest('.suggestion-item');
            if (item) window.location.href = item.dataset.url;
        });

        let selectedIdx = -1;
        searchInput?.addEventListener('keydown', (e) => {
            const items = suggestionsBox.querySelectorAll('.suggestion-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIdx = Math.min(selectedIdx + 1, items.length - 1);
                this.highlightSuggestion(items, selectedIdx);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIdx = Math.max(selectedIdx - 1, -1);
                this.highlightSuggestion(items, selectedIdx);
            } else if (e.key === 'Enter' && selectedIdx > -1) {
                e.preventDefault();
                window.location.href = items[selectedIdx].dataset.url;
            } else if (e.key === 'Escape') {
                suggestionsBox.style.display = 'none';
                searchInput.blur();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const wrapper = document.getElementById('sideSearchWrapper');
                if (wrapper?.style.display === 'none') {
                    toggleGlobalSearch();
                } else {
                    searchInput?.focus();
                }
            }
            if (e.key === 'Escape') this.setMenu(false);
        });

        document.addEventListener('click', (e) => {
            if (!searchInput?.contains(e.target) && !suggestionsBox?.contains(e.target) && !e.target.closest('.menu-close-btn')) {
                suggestionsBox.style.display = 'none';
            }
        });
    },

    highlightSuggestion: function(items, idx) {
        items.forEach(it => it.classList.remove('selected'));
        if (idx > -1) {
            items[idx].classList.add('selected');
            items[idx].scrollIntoView({ block: 'nearest' });
        }
    }
};

setTimeout(() => adminNav.init(), 100);
</script>
