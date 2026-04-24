<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_settings")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Promotions</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border-dim);
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
        
        .btn-green-modern { background: rgba(34, 197, 94, 0.1); color: var(--status-success); border: 1px solid rgba(34, 197, 94, 0.2); }
        .btn-green-modern:hover { background: var(--status-success); color: #fff; }
        
        .btn-gray-modern { background: rgba(100, 116, 139, 0.1); color: var(--text-dim); border: 1px solid rgba(100, 116, 139, 0.2); }
        .btn-gray-modern:hover { background: var(--text-dim); color: #fff; }

        /* r-table system */
        .r-table-wrapper {
            background: var(--table-header-bg); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: auto;
            max-height: calc(100vh - 180px);
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: var(--panel-bg); padding: 16px 20px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
            white-space: nowrap;
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

        .promo-img-preview {
            width: 80px; height: 50px; object-fit: cover; border-radius: 8px;
            border: 1px solid var(--border-dim); background: #000;
        }
        
        .badge-cat {
            padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase;
        }
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
                <span class="dash-breadcrumb">Content Management</span>
                <h1>Promotions Manager</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="create-promotion.php" class="btn-modern btn-primary-modern">
                    <i class='bx bx-plus'></i> Add Offer
                </a>
                <button class="btn-modern p-0" style="background:transparent; color:var(--text-dim)" onclick="window.location.reload()">
                    <i class='bx bx-refresh ft-sz-25'></i>
                </button>
            </div>
        </div>

        <div class="r-table-wrapper hide-native-scrollbar">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 60px">No</th>
                        <th>Banner</th>
                        <th>Title / Desc</th>
                        <th>Category</th>
                        <th>Ends At</th>
                        <th>Status</th>
                        <th style="width: 150px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $indexVal = 1;
                    $promo_sql = "SELECT * FROM tbl_offer_promotions ORDER BY id DESC";
                    $promo_result = mysqli_query($conn, $promo_sql);
                
                    if ($promo_result && mysqli_num_rows($promo_result) > 0){
                        while ($row = mysqli_fetch_assoc($promo_result)){
                            $cat_color = "#3b82f6";
                            if($row['category'] == 'sports') $cat_color = "#10b981";
                            if($row['category'] == 'casino') $cat_color = "#f43f5e";
                        ?>
                        <tr>
                            <td><span style="color: var(--text-dim); font-weight: 700;">#<?php echo $indexVal; ?></span></td>
                            <td>
                                <img src="../../<?php echo $row['image_path']; ?>" class="promo-img-preview" alt="Promo" onerror="this.src='https://placehold.co/80x50/161b22/94a3b8?text=Invalid'">
                            </td>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);"><?php echo $row['title']; ?></div>
                                <div style="font-size: 11px; color: var(--text-dim); margin-top:2px;"><?php echo $row['description']; ?></div>
                            </td>
                            <td>
                                <span class="badge-cat" style="color: <?php echo $cat_color; ?>; background: <?php echo $cat_color; ?>20;">
                                    <?php echo $row['category']; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; font-weight: 600; color: var(--text-main);">
                                    <?php echo date('d M Y, h:i A', strtotime($row['end_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="toggle_status.php?id=<?php echo $row['id']; ?>&status=<?php echo $row['status'] == 'active' ? 'inactive' : 'active'; ?>" 
                                   class="btn-modern <?php echo $row['status'] == 'active' ? 'btn-green-modern' : 'btn-gray-modern'; ?>" 
                                   style="height: 30px; font-size: 11px;">
                                    <i class='bx <?php echo $row['status'] == 'active' ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i> 
                                    <?php echo ucfirst($row['status']); ?>
                                </a>
                            </td>
                            <td style="text-align: right;">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="edit-promotion.php?id=<?php echo $row['id']; ?>" class="btn-modern btn-primary-modern" style="padding: 0 12px; height: 32px; font-size: 12px;">
                                        <i class='bx bx-edit-alt'></i> Edit
                                    </a>
                                    <a class="btn-modern btn-red-modern" onclick="deleteItem('<?php echo $row['id']; ?>')" style="padding: 0 12px; height: 32px; font-size: 12px;">
                                        <i class='bx bx-trash'></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php $indexVal++; 
                        }
                    } else { ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                <i class='bx bx-ghost' style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                No promotional offers found!
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
    if(confirm("Are you sure you want to delete this promotion?")){
        window.open("delete.php?id="+id, "_self");
    }
}
</script>
</body>
</html>
