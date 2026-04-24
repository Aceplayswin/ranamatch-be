<?php
header("Cache-Control: no cache");
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


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Users Data</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
/* Page specific variable overrides only if needed */
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 30px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 20px;
        }

        .filter-section {
            background: var(--panel-bg); border: 1px solid var(--border-dim);
            border-radius: 16px; padding: 24px; margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }

        .cl-green { color: var(--accent-emerald) !important; font-weight: 700; }
        .cl-red { color: var(--accent-rose) !important; font-weight: 700; }
        .cl-black { color: var(--text-dim) !important; }
        .hide_view { display: none !important; }
    </style>
</head>
<body>
    
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <div class="dash-header">
            <div class="d-flex align-items-center gap-3">
                <div class="menu-open-btn col-view v-center pd-5 ft-sz-25 br-r-5 bx-shdw bg-white" style="display: none;"><i class='bx bx-menu' ></i></div>
                <div>
                    <p class="mg-0 ft-sz-12 text-dim">Dashboard > Users Data</p>
                    <h1 class="mg-0">Users Data (Active/Banned)</h1>
                </div>
            </div>
        </div>
           
        <div class="filter-section mg-t-20">
            
            <form action="<?php $_SERVER['PHP_SELF'] ?>" method="POST">
                <input type="text" name="searchinp" placeholder="Search Id, Mobile Number, Name, Date" class="w-100 cus-inp" />
                <br><br>
                <div class="row-view j-start mg-t-15" style="gap: 10px;">
                 <button type="submit" name="submit" class="btn-modern btn-primary-modern" style="border:none;">
                    <i class='bx bx-search'></i> Search Records
                 </button>
                 <button class="btn-modern btn-outline-modern filter_btn" type="button">
                    <i class='bx bx-filter'></i> Filter
                 </button>
                 <button class="btn-modern btn-outline-modern" onclick="exportPDF('users-data', 'table')" type="button">
                    <i class='bx bxs-file-pdf'></i> Export PDF
                 </button>
                </div>

                <div class="w-100 pd-15 mg-t-10 bg-l-blue br-r-5 filter_options hide_view">
                  <input type="checkbox" id="success_orders" name="order_type" value="true" <?php if($newRequestStatus=="true"){ ?> checked <?php } ?>>
                  <label for="success_orders"> Show Active</label><br>
                  
                  <input type="checkbox" id="rejected_orders" class="mg-t-10" name="order_type" value="ban" <?php if($newRequestStatus=="ban"){ ?> checked <?php } ?>>
                  <label for="rejected_orders"> Show Banned</label><br>
                  
                  <input type="checkbox" id="pending_orders" class="mg-t-10" name="order_type" value="false" <?php if($newRequestStatus=="false"){ ?> checked <?php } ?>>
                  <label for="pending_orders"> Show In-Active</label>
                </div>
            </form>
            
            </br></br>
            <p>All Records <a class="cur-pointer pd-5-10 mg-l-5 cl-white br-r-5 bg-primary" onclick="window.location.reload()"><i class='bx bx-refresh'></i>&nbsp;Refresh</a></p>
<table id="table" class="cus-tbl mg-t-10 bg-white" style="width: 100%; table-layout: fixed;">
	          <tr>
                 <th style="width:10%">No</th>
	  	         <th>Id</th>
	  	         <th>Username</th>
	  	         <th>Balance</th>
	  	         <th>Mobile</th>
	  	         <th>User IP</th>
	  	         <th style="width:10%">Date & Time</th>
	  	         <th style="width:15%">Status</th>	  	
	          </tr>
	          
	        <?php
              $indexVal = 1;
              $paginationAvailable = false;
              
              if($searched!=""){
                $user_records_sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}' AND (tbl_uniq_id like '%$searched%' or tbl_mobile_num like '%$searched%' or tbl_full_name like '%$searched%' or tbl_email_id like '%$searched%' or tbl_user_joined LIKE '%$searched%') LIMIT 100";
              }else{
                $user_records_sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}' ORDER BY id DESC LIMIT {$offset},{$content}";
              }
      
              $user_records_result = mysqli_query($conn, $user_records_sql) or die('search failed');
          
              if (mysqli_num_rows($user_records_result) > 0){
                $paginationAvailable = true;                   
                while ($row = mysqli_fetch_assoc($user_records_result)){
                    
                 $uniq_id = $row['tbl_uniq_id'];
                 $request_status = $row['tbl_account_status'];
                 $balance = $row['tbl_balance'];
                 $username = $row['tbl_full_name'];    
                 $data_sql = "SELECT tbl_device_ip FROM tblusersactivity WHERE tbl_user_id='{$uniq_id}' ORDER BY id ASC LIMIT 1";
                 $data_query = mysqli_query($conn, $data_sql);
                 $ip = "N/A";
                 if ($data_row = mysqli_fetch_assoc($data_query)) {
            $ip = $data_row['tbl_device_ip'];
                   }          
                ?>
                 <tr onclick="window.location.href='manager.php?id=<?php echo $uniq_id; ?>'">
	               <td><?php echo $indexVal; ?></td>
	               <td><?php echo $uniq_id; ?></td>
	                <td><?php echo $username; ?></td>
	               <td><?php echo $balance; ?></td>
	               <td><?php echo $row['tbl_mobile_num']; ?></td>
	                <td><?php echo $ip; ?></td>
	                <td><?php echo $row['tbl_user_joined']; ?></td>
	               <td class="<?php if($request_status=='true'){ echo 'cl-green'; }else if($request_status=='ban'){ echo 'cl-red'; }else{ echo 'cl-black'; } ?>"><?php if($request_status=="true"){ echo "Active"; }else if($request_status=="ban"){ echo "Banned"; }else{ echo "Not-Active"; } ?></td>
	             </tr>
                 
            <?php $indexVal++; }}else{ ?>
              <tr>
	               <td colspan="3">No data found!</td>
	               <td></td>
	          </tr>
            <?php } ?>
	        </table>
	        
	        <?php
	         $user_records_sql = "SELECT * FROM tblusersdata WHERE tbl_account_status='{$newRequestStatus}'";
             $user_records_result = mysqli_query($conn, $user_records_sql) or die('fetch failed');

             if (mysqli_num_rows($user_records_result) > 0) {
               $total_records = mysqli_num_rows($user_records_result);
               $total_page = ceil($total_records/ $content);
            ?>
	         <div class="w-100 row-view j-end mg-t-15">
               <div>Page: <?php echo $page_num.' / Records:'.$total_records; ?></div>
               <?php if ($page_num > 1) { ?>
                 <a class="action-btn br-r-5 ft-sz-16 pd-10-15 mg-l-10" type="button" onclick="window.history.back()">Back</a>
               <?php } ?>
               <?php if ($page_num != $total_page) { ?>
                 <a href="?page_num=<?php echo $page_num+1; ?>&order_type=<?php echo $newRequestStatus; ?>" class="action-btn br-r-5 ft-sz-16 txt-deco-n pd-10-15 mg-l-10" type="button">Next</a>
               <?php } ?>
             </div>
            <?php } ?>
        
           </div>
           
    </div>
</div>

<script src="../script.js?v=1"></script>
<script>
  document.querySelector(".filter_btn").addEventListener("click", ()=>{
    document.querySelector(".filter_options").classList.toggle("hide_view")
  });

  var filterOp = document.querySelector(".filter_options");
    var option = filterOp.getElementsByTagName("input");
    for (var i = 0; i < option.length; i++) {
      option[i].onclick = function () {
        for (var i = 0; i < option.length; i++) {
          if (option[i] != this && this.checked) {
            option[i].checked = false;
          }
        }
      };
    }
</script>

</body>
</html>