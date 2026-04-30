<?php
define("ACCESS_SECURITY", "true");
include 'security/config.php';

echo "<h3>Broadcast System Diagnostic</h3>";

// 1. Check Tables
$tables = ['tbl_broadcasts', 'tbl_broadcast_views'];
foreach($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if(mysqli_num_rows($res) > 0) {
        echo "✅ Table <b>$t</b> exists.<br>";
        // Show columns
        $cols = mysqli_query($conn, "DESCRIBE $t");
        echo "<ul>";
        while($c = mysqli_fetch_assoc($cols)) {
            echo "<li>" . $c['Field'] . " (" . $c['Type'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ Table <b>$t</b> is MISSING!<br>";
    }
}

// 2. Check Data
$b_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_broadcasts"))['c'];
$v_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_broadcast_views"))['c'];

echo "📊 Current Data:<br>";
echo "- Total Broadcasts: $b_count<br>";
echo "- Total 'Seen' Records: $v_count<br>";

// 3. Show Recent Views
echo "<h4>Recent Seen Records:</h4>";
$views = mysqli_query($conn, "SELECT * FROM tbl_broadcast_views ORDER BY id DESC LIMIT 5");
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Broadcast ID</th><th>User ID</th></tr>";
while($v = mysqli_fetch_assoc($views)) {
    echo "<tr><td>{$v['id']}</td><td>{$v['broadcast_id']}</td><td>{$v['user_id']}</td></tr>";
}
echo "</table>";

mysqli_close($conn);
?>
