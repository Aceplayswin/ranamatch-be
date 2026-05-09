<?php
define("ACCESS_SECURITY", "true");
include 'd:\xampp\htdocs\security\config.php';

$sql = "CREATE TABLE IF NOT EXISTS tbl_offer_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'all',
    end_date DATETIME,
    image_path TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "Table tbl_offer_promotions created successfully!";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}
?>
