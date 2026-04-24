<script>
/**
 * Global game control scripts
 */

/**
 * Restart a specific game or all games
 * @param {string} project_name - The name of the project to restart
 */
function restartGame(project_name) {
    if (confirm("Are you sure you want to restart the game?")) {
        // Open the restart script in a new window/tab
        const restartWindow = window.open("restart-game.php?project=" + project_name, "_blank");
        
        // Optional: Reload the current page after a short delay if the window was closed
        if (restartWindow) {
            const timer = setInterval(function() {
                if (restartWindow.closed) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        }
    }
}

/**
 * Common layout and UI scripts for game control pages
 */
document.addEventListener('DOMContentLoaded', function() {
    // Shared UI initialization if needed
});
</script>
