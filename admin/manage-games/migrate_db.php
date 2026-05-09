<?php
define("ACCESS_SECURITY", true);
require_once('../../security/config.php');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "CREATE TABLE IF NOT EXISTS tbl_games (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    game_uid VARCHAR(255) NOT NULL UNIQUE, 
    game_name VARCHAR(255) NOT NULL,
    game_category VARCHAR(100) NOT NULL,
    game_provider VARCHAR(100) NOT NULL, 
    game_image VARCHAR(500) NOT NULL,    
    game_status TINYINT(1) DEFAULT 1,    
    is_featured TINYINT(1) DEFAULT 0,    
    sort_order INT(11) DEFAULT 0,        
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $sql)) {
    echo "Table tbl_games created successfully or already exists.\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}
?>
