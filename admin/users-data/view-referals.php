<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_users_data")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

if(!isset($_GET['id'])){
  echo "request block";
  return;
}
$user_uniq_id = mysqli_real_escape_string($conn, $_GET['id']);

$searched="";
if (isset($_POST['submit'])){
   $searched = $_POST['searchinp'];
}

$content = 15;
if (isset($_GET['page_num'])){
 $page_num = $_GET['page_num'];
 $offset = ($page_num-1)*$content;
}else{
 $page_num = 1;
 $offset = ($page_num-1)*$content;
}

if(isset($_POST['order_type'])){
  $newRequestStatus = $_POST['order_type'];
}else{
  $newRequestStatus = "true";
}

// Fetch Parent User Info
$parent_sql = "SELECT tbl_full_name FROM tblusersdata WHERE tbl_uniq_id='{$user_uniq_id}'";
$parent_res = mysqli_query($conn, $parent_sql);
$parent_name = ($parent_row = mysqli_fetch_assoc($parent_res)) ? $parent_row['tbl_full_name'] : "Unknown User";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Referrals: <?php echo htmlspecialchars($parent_name); ?></title>
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
            padding: 24px 20px; border-bottom: 1px solid var(--border-dim);
        }
        .dash-header-left { display: flex; align-items: center; gap: 14px; }
        .back-btn {
            width: 40px; height: 40px; border-radius: 10px; background: var(--input-bg);
            border: 1px solid var(--border-dim); color: var(--text-main); display: flex; align-items: center;
            justify-content: center; font-size: 24px; cursor: pointer; transition: all 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.1); transform: translateX(-4px); }

        .dash-breadcrumb { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--status-info); }
        .dash-title { font-size: 28px; font-weight: 800; color: var(--text-main); }

        .search-area {
            background: rgba(255,255,255,0.02); border: 1px solid var(--border-dim);
            border-radius: 20px; padding: 24px; margin-bottom: 24px;
        }
        .cus-inp {
            height: 48px; background: var(--input-bg) !important;
            border: 1px solid var(--border-dim) !important; border-radius: 12px !important;
            padding: 0 16px !important; color: var(--text-main) !important; font-size: 14px !important;
        }
        .cus-inp:focus { border-color: var(--accent-blue) !important; box-shadow: none !important; }
        .cus-inp::placeholder { color: var(--text-dim) !important; opacity: 1; }

        .btn-modern {
            height: 48px; padding: 0 24px; border-radius: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 8px; transition: all 0.2s;
        }
        .btn-primary-modern { background: var(--accent-blue); color: #fff; border: none; }
        .btn-primary-modern:hover { background: #2563eb; transform: translateY(-2px); }
        
        .btn-outline-modern { 
            background: transparent; color: var(--text-dim); border: 1px solid var(--border-dim);
        }
        .btn-outline-modern:hover { background: rgba(255,255,255,0.05); color: #fff; }

        .filter-options {
            background: rgba(255,255,255,0.02); border: 1px solid var(--border-dim);
            border-radius: 12px; padding: 18px; margin-top: 15px; display: none;
        }
        .filter-options.show { display: block; }
        .custom-check {
            display: inline-flex; align-items: center; gap: 10px; margin-right: 25px;
            cursor: pointer; font-size: 14px; color: var(--text-dim);
        }
        .custom-check input { width: 18px; height: 18px; accent-color: var(--accent-blue); }

        .r-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .r-table thead th {
            font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            color: #94a3b8; padding: 0 16px 8px; border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .r-table tbody td {
            padding: 14px 16px; font-size: 13px; font-weight: 500; color: var(--text-main);
            background: var(--table-header-bg); border-top: 1px solid var(--border-dim);
            border-bottom: 1px solid var(--border-dim);
        }
        .r-table tbody td:first-child { border-radius: 12px 0 0 12px; }
        .r-table tbody td:last-child { border-radius: 0 12px 12px 0; }
        .r-table tr:hover td { background: var(--table-row-hover); color: var(--text-main); }

        .status-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-active  { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-banned  { background: rgba(244, 63, 94, 0.15);  color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.3); }
        .status-inactive { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); }

        .pagination-container { display: flex; justify-content: flex-end; margin-top: 24px; gap: 8px; }
        .page-btn {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim);
            border-radius: 10px; color: var(--text-dim); font-weight: 600; text-decoration: none;
            transition: all 0.2s;
        }
        .page-btn:hover { background: rgba(59, 130, 246, 0.1); color: #ffffff; border-color: var(--accent-blue); }
        .page-btn.active { background: var(--accent-blue); color: #ffffff; border-color: var(--accent-blue); }
        .page-btn.disabled { opacity: 0.3; pointer-events: none; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    </style>
</head>

<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="dash-header-left">
                <div class="back-btn" onclick="window.history.back()"><i class='bx bx-left-arrow-alt'></i></div>
                <div>
                    <span class="dash-breadcrumb">Network > Downline</span>
                    <span class="dash-title"><?php echo htmlspecialchars($parent_name); ?> <small style="font-size: 11px; color: var(--text-dim);">#<?php echo $user_uniq_id; ?></small></span>
                </div>
            </div>
            <button class="btn-modern btn-outline-modern" onclick="window.location.reload()"><i class='bx bx-refresh'></i> Refresh List</button>
        </div>

        <div style="padding: 24px 20px;">
            
            <div class="search-area">
                <form action="<?php echo $_SERVER['PHP_SELF'].'?id='.$user_uniq_id; ?>" method="POST">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <input type="text" name="searchinp" placeholder="Search by ID, Mobile, Name..." value="<?php echo htmlspecialchars($searched); ?>" class="form-control cus-inp">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" name="submit" class="btn-modern btn-primary-modern flex-grow-1">
                                <i class='bx bx-search'></i> Search Downline
                            </button>
                            <button type="button" class="btn-modern btn-outline-modern filter-toggle-btn">
                                <i class='bx bx-filter-alt'></i> Filter
                            </button>
                        </div>
                    </div>

                    <div class="filter-options <?php if($newRequestStatus != 'true') echo 'show'; ?>">
                        <label class="custom-check">
                            <input type="radio" name="order_type" value="true" <?php if($newRequestStatus=="true"){ echo 'checked'; } ?>> 
                            Active Members
                        </label>
                        <label class="custom-check">
                            <input type="radio" name="order_type" value="ban" <?php if($newRequestStatus=="ban"){ echo 'checked'; } ?>> 
                            Banned Members
                        </label>
                        <label class="custom-check">
                            <input type="radio" name="order_type" value="false" <?php if($newRequestStatus=="false"){ echo 'checked'; } ?>> 
                            Inactive Members
                        </label>
                    </div>
                </form>
            </div>

            <div class="w-100 ovflw-x-scroll hide-native-scrollbar">
                <table class="r-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Player Identity</th>
                            <th>Contact / Mobile</th>
                            <th>Direct Uplines</th>
                            <th style="text-align: right;">Account Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $indexVal = 1;
                        if($searched!=""){
                             $sql = "SELECT * FROM tblusersdata WHERE tbl_account_status = '{$newRequestStatus}' AND tbl_joined_under='{$user_uniq_id}' AND (tbl_uniq_id like '%$searched%' or tbl_mobile_num like '%$searched%' or tbl_full_name like '%$searched%' or tbl_email_id like '%$searched%') LIMIT 100";
                        }else{
                             $sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}' AND tbl_joined_under='{$user_uniq_id}' ORDER BY id DESC LIMIT {$offset},{$content}";
                        }
                
                        $res = mysqli_query($conn, $sql);
                        if (mysqli_num_rows($res) > 0){
                            while ($row = mysqli_fetch_assoc($res)){
                                $uid = $row['tbl_uniq_id'];
                                $st = $row['tbl_account_status'];
                                ?>
                                <tr onclick="window.location.href='manager.php?id=<?php echo $uid; ?>'" style="cursor: pointer;">
                                    <td style="font-size: 11px; color: var(--text-dim);"><?php echo $indexVal + $offset; ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($row['tbl_full_name']); ?></div>
                                        <div style="font-size: 11px; color: var(--accent-blue);"><?php echo $uid; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;"><?php echo $row['tbl_mobile_num']; ?></div>
                                        <div style="font-size: 11px; color: var(--text-dim);"><?php echo htmlspecialchars($row['tbl_email_id']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;">Managed by <?php echo htmlspecialchars($parent_name); ?></div>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if($st == 'true'): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php elseif($st == 'ban'): ?>
                                            <span class="status-badge status-banned">Banned</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $indexVal++; 
                            }
                        } else { ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                    <i class='bx bx-user-x' style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                    No referrals found for this criteria.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php
            $count_sql = "SELECT count(id) as total FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}' AND tbl_joined_under='{$user_uniq_id}'";
            $count_res = mysqli_query($conn, $count_sql);
            $total_records = ($count_row = mysqli_fetch_assoc($count_res)) ? $count_row['total'] : 0;
            $total_page = ceil($total_records / $content);

            if ($total_records > 0): ?>
            <div class="pagination-container">
                <div style="margin-right: auto; align-self: center; font-size: 13px; color: var(--text-dim);">
                    Showing <?php echo min($total_records, $offset + 1); ?>-<?php echo min($total_records, $offset + $content); ?> of <?php echo $total_records; ?> members
                </div>
                <a href="?id=<?php echo $user_uniq_id; ?>&page_num=<?php echo max(1, $page_num - 1); ?>&order_type=<?php echo $newRequestStatus; ?>" class="page-btn <?php if($page_num <= 1) echo 'disabled'; ?>">
                    <i class='bx bx-chevron-left'></i>
                </a>
                <?php for($i = 1; $i <= $total_page; $i++): ?>
                    <?php if($i == $page_num || ($i >= $page_num - 2 && $i <= $page_num + 2)): ?>
                        <a href="?id=<?php echo $user_uniq_id; ?>&page_num=<?php echo $i; ?>&order_type=<?php echo $newRequestStatus; ?>" class="page-btn <?php if($i == $page_num) echo 'active'; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="?id=<?php echo $user_uniq_id; ?>&page_num=<?php echo min($total_page, $page_num + 1); ?>&order_type=<?php echo $newRequestStatus; ?>" class="page-btn <?php if($page_num >= $total_page) echo 'disabled'; ?>">
                    <i class='bx bx-chevron-right'></i>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelector(".filter-toggle-btn").addEventListener("click", () => {
        document.querySelector(".filter-options").classList.toggle("show");
    });
</script>
</body>
</html>