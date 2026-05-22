<?php
define("ACCESS_SECURITY", "true");
include "../security/config.php";

$sql = "ALTER TABLE tblmatchplayed 
        ADD COLUMN IF NOT EXISTS tbl_result_time VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS tbl_selection VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS tbl_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

$conn->query("CREATE TABLE IF NOT EXISTS tbl_game_names (
    tbl_game_id VARCHAR(100) PRIMARY KEY,
    tbl_game_name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($conn->query($sql) === TRUE) {
    // Also add index for performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_updated_at ON tblmatchplayed(tbl_updated_at)");
    echo "Success";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
