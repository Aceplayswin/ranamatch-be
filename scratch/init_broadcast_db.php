<?php
define("ACCESS_SECURITY", "true");
include 'd:/xampp/htdocs/security/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS tbl_broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_type ENUM('all', 'specific') DEFAULT 'all',
    target_uid VARCHAR(50) DEFAULT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    show_once TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tbl_broadcast_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    broadcast_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (broadcast_id, user_id)
);
";

if (mysqli_multi_query($conn, $sql)) {
    echo "Broadcast tables initialized successfully!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
