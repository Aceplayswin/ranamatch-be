<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }
    header('location:../logout-account');
    exit;
}

$user_id = mysqli_real_escape_string($conn, $_REQUEST['user-id'] ?? $_REQUEST['user_id'] ?? $_REQUEST['id'] ?? '');
$request_type = mysqli_real_escape_string($conn, $_REQUEST['request-type'] ?? $_REQUEST['request_type'] ?? $_REQUEST['type'] ?? '');
$is_ajax = isset($_REQUEST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if (empty($user_id) || empty($request_type)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters provided.']);
        exit;
    }
    echo "Invalid request parameters.";
    exit;
}

// Fetch user info
$select_sql = "SELECT id, tbl_uniq_id, tbl_account_status, tbl_user_name, tbl_full_name FROM tblusersdata WHERE tbl_uniq_id = '$user_id' OR id = '$user_id'";
$select_result = mysqli_query($conn, $select_sql);

if ($select_result && mysqli_num_rows($select_result) > 0) {
    $u_data = mysqli_fetch_assoc($select_result);
    $real_uniq_id = $u_data['tbl_uniq_id'];

    // Map request types
    $new_status = $request_type;
    if (in_array(strtolower($request_type), ['ban', 'banned', 'blocked', 'block'])) {
        $new_status = 'ban';
    } elseif (in_array(strtolower($request_type), ['true', 'active', 'unban', 'restore'])) {
        $new_status = 'true';
    }

    $update_sql = "UPDATE tblusersdata SET tbl_account_status = '{$new_status}' WHERE tbl_uniq_id = '{$real_uniq_id}'";
    $update_result = mysqli_query($conn, $update_sql);

    if ($update_result) {
        // Self-healing migration for tbl_blocked_ips table if needed
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_blocked_ips'");
        if ($checkTable && mysqli_num_rows($checkTable) == 0) {
            mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_blocked_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(100) NOT NULL UNIQUE,
                reason VARCHAR(255) DEFAULT 'Security Block',
                blocked_by VARCHAR(100) DEFAULT 'Admin',
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Fetch user IP address from activity logs
        $user_ip = '';
        $ip_q = mysqli_query($conn, "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id = '{$real_uniq_id}' AND tbl_device_ip IS NOT NULL AND tbl_device_ip != '' AND tbl_device_ip != 'N/A' ORDER BY id DESC LIMIT 1");
        if ($ip_r = mysqli_fetch_assoc($ip_q)) {
            $user_ip = $ip_r['tbl_device_ip'];
        }

        // Handle IP Block syncing
        if (!empty($user_ip) && filter_var($user_ip, FILTER_VALIDATE_IP)) {
            if ($new_status === 'ban') {
                $stmt = $conn->prepare("INSERT INTO tbl_blocked_ips (ip_address, reason, blocked_by, status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active', reason = VALUES(reason)");
                $reason = "User Account Banned: " . ($u_data['tbl_user_name'] ?: $u_data['tbl_full_name']);
                $admin_id = $_SESSION['admin_user_id'] ?? 'Admin';
                $stmt->bind_param("sss", $user_ip, $reason, $admin_id);
                $stmt->execute();
                $stmt->close();
            } elseif ($new_status === 'true') {
                mysqli_query($conn, "UPDATE tbl_blocked_ips SET status = 'inactive' WHERE ip_address = '{$user_ip}'");
            }
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => ($new_status === 'ban') ? 'User account & IP restricted successfully!' : 'User account access restored successfully!',
                'new_status' => $new_status
            ]);
            exit;
        }

        echo "<script>
            alert('User status updated successfully to: " . strtoupper($new_status) . "');
            if (window.opener) {
                window.opener.location.reload();
                window.close();
            } else {
                window.location.href = 'manager.php?id=" . $real_uniq_id . "';
            }
        </script>";
        exit;
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . mysqli_error($conn)]);
            exit;
        }
        echo "Database update failed: " . mysqli_error($conn);
        exit;
    }
} else {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'User account not found.']);
        exit;
    }
    echo "User account not found.";
    exit;
}
?>