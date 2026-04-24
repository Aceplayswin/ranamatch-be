<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_gift")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
    exit;
}

$searched="";
if (isset($_POST['submit'])){
   $searched = $_POST['searched'];
}

$content = 15;
if (isset($_GET['page_num'])){
 $page_num = $_GET['page_num'];
 $offset = ($page_num-1)*$content;
}else{
 $page_num = 1;
 $offset = ($page_num-1)*$content;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM tblotherstransactions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Record deleted successfully!'); window.location.href='index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error deleting record!'); window.history.back();</script>";
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Withdrawal Management</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .main-panel {
            flex-grow: 1; height: 100%; border-radius: 20px; border: 1px solid var(--border-dim);
            background: var(--panel-bg); box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            padding: 24px; overflow-y: scroll;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: #f1f5f9; margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        
        .btn-red-modern { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .btn-red-modern:hover { background: var(--accent-rose); color: #fff; }

        /* r-table system */
        .r-table-wrapper {
            background: rgba(255,255,255,0.02); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: hidden;
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(255,255,255,0.03); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
        }
        .r-table td {
            padding: 16px 20px; font-size: 14px; color: #e2e8f0;
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: rgba(255,255,255,0.02); }

        .user-id-badge {
            background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);
            padding: 4px 10px; border-radius: 6px; font-family: monospace; font-weight: 700;
        }
        .amount-text { font-weight: 800; color: var(--text-main); }
        .time-text { font-size: 12px; color: var(--text-dim); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Withdrawal Settings</span>
                <h1>All Withdrawal</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="create-withdraw" class="btn-modern btn-primary-modern">
                    <i class='bx bx-plus'></i> Create Withdraw
                </a>
                <button class="btn-modern p-0" style="background:transparent; color:var(--text-dim)" onclick="window.location.reload()">
                    <i class='bx bx-refresh ft-sz-25'></i>
                </button>
            </div>
        </div>

        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 80px">No</th>
                        <th>UserID</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th style="width: 120px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $indexVal = 1;
                    $recharge_records_sql = "SELECT * FROM tblotherstransactions WHERE tbl_transaction_type = 'Play Matched' ORDER BY id DESC LIMIT 20";
                    $recharge_records_result = mysqli_query($conn, $recharge_records_sql);
                
                    if ($recharge_records_result && mysqli_num_rows($recharge_records_result) > 0){
                        while ($row = mysqli_fetch_assoc($recharge_records_result)){
                        ?>
                        <tr>
                            <td><span style="color: var(--text-dim); font-weight: 700;">#<?php echo $indexVal; ?></span></td>
                            <td><span class="user-id-badge"><?php echo $row['tbl_user_id']; ?></span></td>
                            <td><span class="amount-text">₹<?php echo number_format($row['tbl_transaction_amount'], 2); ?></span></td>
                            <td><span class="time-text"><?php echo $row['tbl_time_stamp']; ?></span></td>
                            <td style="text-align: right;">
                                <a class="btn-modern btn-red-modern" href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this record?');">
                                    <i class='bx bx-trash'></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php $indexVal++; 
                        }
                    } else { ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                <i class='bx bx-ghost' style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                No data found!
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../script.js?v=2"></script>
</body>
</html>