<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width , initial-scale=1">
<?php 
    if(!defined("ACCESS_SECURITY")) define("ACCESS_SECURITY", "true");
    $dots_h = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
    include_once $dots_h . 'security/config.php';
    include_once $dots_h . 'security/constants.php';
    
    // Use Logo as Favicon for consistent branding
    $favicon_src = (strpos($APP_LOGO, 'http') === 0) ? $APP_LOGO : $dots_h . $APP_LOGO;
    if(empty($APP_LOGO) || $APP_LOGO == "wincologo.png") $favicon_src = $dots_h . "favicon.ico";
?>
<link rel="icon" href="<?php echo $favicon_src; ?>">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- MODERN FONTS -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --font-heading: 'Outfit', sans-serif;
        --font-body: 'Inter', sans-serif;
    }
    
    body, html {
        font-family: var(--font-body) !important;
        font-size: 14px !important;
        line-height: 1.5;
    }
    
    h1, h2, h3, h4, h5, h6, 
    .card-title, .brand-name, .logo-text,
    .nav-label, .menu-title, .dash-title h1 {
        font-family: var(--font-heading) !important;
        font-weight: 600 !important;
    }
    
    /* Specific overrides for admin elements if they use legacy fonts */
    .table thead th, .game-table th {
        font-family: var(--font-heading) !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        font-size: 11px !important;
    }

    input, select, textarea, button, .cus-inp, .cus-sel {
        font-family: var(--font-body) !important;
        font-size: 13px !important;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>

    // Global Theme Management System

    const themeToggle = {

        init: function () {

            const savedTheme = localStorage.getItem('admin-theme') || 'dark';

            this.setTheme(savedTheme);

        },

        setTheme: function (theme) {

            document.documentElement.setAttribute('data-theme', theme);

            localStorage.setItem('admin-theme', theme);

            const updateIcons = () => {

                const darkIcons = document.querySelectorAll('.theme-icon-dark');

                const lightIcons = document.querySelectorAll('.theme-icon-light');

                if (theme === 'dark') {

                    darkIcons.forEach(i => i.style.display = 'block');

                    lightIcons.forEach(i => i.style.display = 'none');

                } else {

                    darkIcons.forEach(i => i.style.display = 'none');

                    lightIcons.forEach(i => i.style.display = 'block');

                }

            };

            if (document.readyState === 'loading') {

                document.addEventListener('DOMContentLoaded', updateIcons);

            } else {

                updateIcons();

            }

        },

        toggle: function () {

            const currentTheme = document.documentElement.getAttribute('data-theme');

            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            this.setTheme(newTheme);

        }

    };

    themeToggle.init();



    // Global SweetAlert2 Configuration Overhaul

    const originalSwal = window.Swal;

    const premiumSwal = originalSwal.mixin({
        width: '300px',
        background: 'var(--panel-bg)',
        color: 'var(--text-main)',
        backdrop: `rgba(0,0,0,0.5)`,

        buttonsStyling: false,

        customClass: {

            popup: 'compact-dash',

            title: 'compact-dash-title',

            htmlContainer: 'compact-dash-html',

            confirmButton: 'premium-swal-confirm',

            cancelButton: 'premium-swal-cancel',

            icon: 'premium-swal-icon'

        }

    });

    window.Swal = premiumSwal;



    // Global AJAX Cache Control

    if (typeof $ !== 'undefined') {

        $.ajaxSetup({ cache: false });

    }



    // BFCache Killer (Back-Forward Cache)

    window.addEventListener('pageshow', function (event) {

        if (event.persisted) {

            window.location.reload();

        }

    });

</script>

<style>
    <?php include __DIR__ . "/components/theme-variables.php"; ?>



    /* Global Premium SWAL Override Styles */

    .compact-dash {
        backdrop-filter: blur(15px) !important;
        -webkit-backdrop-filter: blur(15px) !important;
        border: 1px solid var(--border-dim) !important;
        border-radius: 16px !important;
        padding: 15px !important;
        box-shadow: var(--card-shadow) !important;
        background: var(--panel-bg) !important;
    }
 
    .compact-dash-title {
        font-family: 'DM Sans', sans-serif !important;
        color: var(--text-main) !important;
        font-weight: 800 !important;
        font-size: 16px !important;
        margin-bottom: 8px !important;
        border-bottom: 1px solid var(--border-dim) !important;
        padding-bottom: 8px !important;
    }
 
    .compact-dash-html {
        font-family: 'DM Sans', sans-serif !important;
        color: var(--text-dim) !important;
        font-size: 12px !important;
        margin: 0 !important;
        padding: 5px !important;
    }

    .premium-swal-confirm {

        background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;

        color: white !important;

        border-radius: 8px !important;

        padding: 8px 20px !important;

        width: 100% !important;

        font-weight: 800 !important;

        font-size: 11px !important;

        text-transform: uppercase !important;

        letter-spacing: 0.5px !important;

        box-shadow: 0 6px 12px -3px rgba(139, 92, 246, 0.4) !important;

        border: none !important;

        cursor: pointer !important;

        margin: 10px 0 5px 0 !important;

    }

    .premium-swal-cancel {

        background: rgba(255, 255, 255, 0.05) !important;

        color: #94a3b8 !important;

        border-radius: 8px !important;

        padding: 8px 20px !important;

        width: 100% !important;

        font-weight: 800 !important;

        font-size: 11px !important;

        text-transform: uppercase !important;

        border: 1px solid rgba(255, 255, 255, 0.1) !important;

        cursor: pointer !important;

        margin: 5px 0 !important;

    }

    .premium-swal-icon {

        transform: scale(0.6) !important;

        margin-top: -10px !important;

        margin-bottom: -10px !important;

    }

    .swal2-actions {

        flex-direction: column !important;

        width: 100% !important;

        gap: 2px !important;

    }
</style>