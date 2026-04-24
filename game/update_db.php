<?php
define("ACCESS_SECURITY", "true");
include "../security/config.php";

$sql = "ALTER TABLE tblmatchplayed 
        ADD COLUMN IF NOT EXISTS tbl_result_time VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS tbl_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

if ($conn->query($sql) === TRUE) {
    // Also add index for performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_updated_at ON tblmatchplayed(tbl_updated_at)");
    echo "Success";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
