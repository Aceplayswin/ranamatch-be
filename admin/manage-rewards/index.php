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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Rewards Management</title>
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

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }
        .dash-title h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--status-info); text-transform: uppercase; letter-spacing: 1px; }

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
            background: var(--table-header-bg); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: hidden;
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: var(--panel-bg); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
        }
        .r-table td {
            padding: 16px 20px; font-size: 14px; color: var(--text-main);
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: var(--table-row-hover); }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-dim);
            text-decoration: none; font-weight: 700; font-size: 11px; text-transform: uppercase;
            margin-bottom: 8px; cursor: pointer; transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }

        .status-pill {
            padding: 4px 10px; border-radius: 100px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-active { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-expired { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }

        .id-badge {
            background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);
            padding: 4px 10px; border-radius: 6px; font-family: monospace; font-weight: 700;
        }
        .action-link {
            font-size: 18px; color: var(--text-dim); transition: color 0.2s; cursor: pointer;
            margin-right: 12px;
        }
        .action-link:hover { color: var(--accent-blue); }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <div class="back-link" onclick="window.history.back()">
                    <i class='bx bx-left-arrow-alt ft-sz-18'></i> Back
                </div><br>
                <span class="dash-breadcrumb">Marketing Tools</span>
                <h1>All Rewards</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="create-giftcard" class="btn-modern btn-primary-modern">
                    <i class='bx bx-plus'></i> Create Rewards
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
                        <th>Id</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="width: 180px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $indexVal = 1;
                    $recharge_records_sql = "SELECT * FROM tblgiftcards ORDER BY id DESC LIMIT 100";
                    $recharge_records_result = mysqli_query($conn, $recharge_records_sql);
                
                    if ($recharge_records_result && mysqli_num_rows($recharge_records_result) > 0){
                        while ($row = mysqli_fetch_assoc($recharge_records_result)){
                            $request_status = $row['tbl_giftcard_status'];
                        ?>
                        <tr>
                            <td><span style="color: var(--text-dim); font-weight: 700;">#<?php echo $indexVal; ?></span></td>
                            <td><span class="id-badge"><?php echo $row['tbl_giftcard']; ?></span></td>
                            <td><span style="font-weight: 800; color: var(--text-main);">₹<?php echo number_format($row['tbl_giftcard_bonus'], 2); ?></span></td>
                            <td>
                                <?php if($request_status=='true'): ?>
                                    <span class="status-pill status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-pill status-expired">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a class="action-link" onclick="copyText('<?php echo $MAIN_DOMAIN_URL.'/redeemgiftcard/?c='.$row['tbl_giftcard']; ?>')" title="Copy Link">
                                    <i class='bx bx-link'></i>
                                </a>
                                <a class="btn-modern btn-red-modern" onclick="deleteItem('<?php echo $row['tbl_giftcard']; ?>')">
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
<script>
function deleteItem(id){
    if(confirm("Are you sure you want to delete?")){
        window.open("delete.php?id="+id);
        setTimeout(() => { window.location.reload(); }, 500);
    }
}
    
function copyText(text){
  var textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.opacity = "0"; 
  textArea.style.position = "fixed";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  try {
    document.execCommand('copy');
    document.body.removeChild(textArea);
    alert('Copied to clipboard!');
  } catch (err) {
    console.error('Copy failed', err);
  }
  return true;
}
</script>
</body>
</html>