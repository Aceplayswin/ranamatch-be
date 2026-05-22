<?php
/**
 * Migration: Add lotto_game_id column to tbl_games
 */

// Connect to database
$conn = new mysqli("localhost", "root", "", "abconndb");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if lotto_game_id column already exists
$check_sql = "SHOW COLUMNS FROM tbl_games WHERE Field = 'lotto_game_id'";
$check_result = $conn->query($check_sql);

if ($check_result->num_rows == 0) {
    // Add the column
    $alter_sql = "ALTER TABLE tbl_games ADD COLUMN lotto_game_id VARCHAR(50) AFTER game_provider";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "✓ Column 'lotto_game_id' added to tbl_games successfully\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ Column 'lotto_game_id' already exists in tbl_games\n";
}

$conn->close();
echo "Migration complete.\n";
?>
