<?php
// OPcache reset script - delete this file after use
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared successfully! Refresh the admin page now.";
} elseif (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "✅ APC cache cleared!";
} else {
    echo "⚠️ No OPcache found. Try restarting PHP from hosting panel.";
}
?>
