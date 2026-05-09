<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

// List of specific games to migrate to casino_lobby
$specific_games = ['Aviator', 'Go Rush', 'Trump Card'];

echo "MIGRATING GAMES TO 'casino_lobby' CATEGORY...\n";

// 1. Migrate games with 'Lobby' in their name
$res1 = mysqli_query($conn, "UPDATE tbl_games SET game_category = 'casino_lobby' WHERE game_name LIKE '%Lobby%'");
echo "Migrated Lobby games: " . mysqli_affected_rows($conn) . "\n";

// 2. Migrate specific games
foreach($specific_games as $name) {
    mysqli_query($conn, "UPDATE tbl_games SET game_category = 'casino_lobby' WHERE game_name LIKE '%$name%'");
    echo "Migrated $name: " . mysqli_affected_rows($conn) . "\n";
}

// 3. Special case for Mines (only the CasinoLive or Spribe one if applicable, but user wanted Mines)
mysqli_query($conn, "UPDATE tbl_games SET game_category = 'casino_lobby' WHERE game_name = 'Mines'");
echo "Migrated Mines: " . mysqli_affected_rows($conn) . "\n";

echo "MIGRATION COMPLETE.\n";
?>
