<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()=="true"){
    if($accessObj->isAllowed("access_withdraw")=="false"){
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}else{
    header('location:../logout-account');
}

if(!isset($_GET['uniq-id'])){
  echo "invalid request";
  return;
}else{
  $uniq_id = mysqli_real_escape_string($conn,$_GET['uniq-id']);
}

$select_sql = "SELECT * FROM tbluserswithdraw WHERE tbl_uniq_id='$uniq_id'";
$select_result = mysqli_query($conn, $select_sql) or die('error');

if(mysqli_num_rows($select_result) > 0){
  $select_res_data = mysqli_fetch_assoc($select_result);

  // Assign variables with guards
  $user_id = $select_res_data['tbl_user_id'] ?? "Unknown";
  $withdraw_amount = $select_res_data['tbl_withdraw_amount'] ?? 0;
  $withdraw_request = $select_res_data['tbl_withdraw_request'] ?? 0;
  $withdraw_details = $select_res_data['tbl_withdraw_details'] ?? "";
  $request_status = $select_res_data['tbl_request_status'] ?? "pending";
  $request_date_time = $select_res_data['tbl_time_stamp'] ?? date('Y-m-d H:i:s');
  
  // Extract withdraw details with null/array guards
  $withdraw_details_arr = explode(',', $withdraw_details);
  $actual_name = $withdraw_details_arr[0] ?? "N/A";
  $bank_account = $withdraw_details_arr[1] ?? "N/A";
  $bank_ifsc_code = $withdraw_details_arr[2] ?? "N/A";
  $bank_name = $withdraw_details_arr[3] ?? "N/A";
  
}else{
  echo 'Invalid order-id or order-id already confirmed!';
  return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Withdraw Record Manager</title>
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
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0;
            display: flex; align-items: center; justify-content: center;
        }

        .manager-container {
            width: 100%; max-width: 1100px; margin: 20px auto; padding: 0 15px;
        }

        .dashboard-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }

        @media (max-width: 991px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .premium-card {
            background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 20px;
            padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            height: 100%;
        }

        .section-label {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1.2px; color: var(--accent-blue); margin-bottom: 12px;
            display: flex; align-items: center; gap: 6px;
        }
        .section-label i { font-size: 14px; }

        .info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 12px; color: var(--text-dim); }
        .info-value { font-size: 13px; font-weight: 700; color: var(--text-main); }

        .amount-display {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.01));
            border: 1px solid rgba(16, 185, 129, 0.08);
            border-radius: 16px; padding: 20px; text-align: center; margin-bottom: 16px;
        }
        .amount-val { font-size: 32px; font-weight: 900; color: var(--accent-emerald); }
        .amount-sub { font-size: 12px; color: var(--text-dim); text-decoration: line-through; opacity: 0.5; }

        .payout-item { 
            background: rgba(255,255,255,0.01); padding: 12px; border-radius: 10px; 
            margin-bottom: 8px; border: 1px solid var(--border-dim);
        }

        .tag { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .tag-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); }
        .tag-warning { background: rgba(245, 158, 11, 0.1); color: var(--accent-amber); }
        .tag-danger { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); }
        .tag-info { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); }

        .action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .btn-modern {
            height: 42px; border-radius: 12px; font-weight: 700; font-size: 12px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.2s; cursor: pointer; border: none; width: 100%;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-success-gradient {
            background: linear-gradient(135deg, #10b981, #059669); color: #fff;
            box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.3);
        }
        .btn-success-gradient:hover { transform: translateY(-2px); box-shadow: 0 8px 16px -2px rgba(16, 185, 129, 0.4); }
        .btn-danger-outline {
            background: rgba(244, 63, 94, 0.05); color: var(--accent-rose);
            border: 1px solid rgba(244, 63, 94, 0.2);
        }
        .btn-danger-outline:hover { background: var(--accent-rose); color: #fff; transform: translateY(-2px); }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1px solid var(--border-dim);
            margin-bottom: 16px;
        }
        .dash-header-left { display: flex; align-items: center; gap: 12px; }
        .back-btn {
            width: 32px; height: 32px; border-radius: 8px; background: var(--input-bg);
            border: 1px solid var(--border-dim); color: var(--text-main); display: flex; align-items: center;
            justify-content: center; font-size: 20px; cursor: pointer;
        }
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
                    <span style="font-size: 9px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Admin Console / Payouts</span>
                    <h1 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0;">Withdrawal Record Manager</h1>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 9px; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Transaction ID</div>
                <div style="font-size: 12px; font-weight: 700; color: var(--accent-blue); font-family: monospace;"><?php echo htmlspecialchars($uniq_id); ?></div>
            </div>
        </div>

        <div class="manager-container">
            <div class="dashboard-grid">
                <!-- Left Column: Transaction Details -->
                <div class="premium-card">
                    <div class="section-label"><i class='bx bx-info-circle'></i> Transaction Summary</div>
                    
                    <div class="info-row">
                        <span class="info-label">Current Status</span>
                        <?php 
                            $tagClass = "tag-warning";
                            if($request_status == "success") $tagClass = "tag-success";
                            elseif($request_status == "rejected") $tagClass = "tag-danger";
                            elseif($request_status == "approve") $tagClass = "tag-info";
                        ?>
                        <span class="tag <?php echo $tagClass; ?>"><?php echo ucfirst($request_status); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Player Account</span>
                        <span class="info-value"><?php echo htmlspecialchars($user_id); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Submission Date</span>
                        <span class="info-value"><?php echo htmlspecialchars($request_date_time); ?></span>
                    </div>

                    <div class="mt-4">
                        <div class="section-label"><i class='bx bx-wallet'></i> Withdrawal Amount</div>
                        <div class="amount-display">
                            <div class="amount-val">₹<?php echo number_format((float)($withdraw_amount ?: 0), 2); ?></div>
                            <?php if ($withdraw_request && $withdraw_request != $withdraw_amount): ?>
                                <div class="amount-sub">Requested: ₹<?php echo number_format((float)$withdraw_request, 2); ?></div>
                            <?php endif; ?>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 10px; opacity: 0.7;">Net amount to be disbursed via API</div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payout Info & Actions -->
                <div class="premium-card">
                    <div class="section-label"><i class='bx bx-credit-card-front'></i> Payout Destination</div>
                    
                    <div class="payout-item">
                        <div class="info-label" style="font-size: 11px; margin-bottom: 4px;">Beneficiary Name</div>
                        <div class="info-value" style="font-size: 15px; color: #fff;"><?php echo htmlspecialchars($actual_name); ?></div>
                    </div>

                    <?php if ($bank_ifsc_code != "null" && $bank_ifsc_code != "N/A"): ?>
                        <div class="payout-item">
                            <div class="info-label" style="font-size: 11px; margin-bottom: 4px;">Settlement Account</div>
                            <div class="info-value"><?php echo htmlspecialchars($bank_name); ?></div>
                            <div style="font-size: 13px; color: var(--accent-blue); margin-top: 8px; font-family: monospace; letter-spacing: 0.5px;">
                                <?php echo htmlspecialchars($bank_account); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 4px; font-weight: 700;">
                                IFSC: <?php echo htmlspecialchars($bank_ifsc_code); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="payout-item">
                            <div class="info-label" style="font-size: 11px; margin-bottom: 4px;">UPI / Virtual Payment Address</div>
                            <div class="info-value" style="font-size: 16px; color: var(--accent-blue); font-family: monospace;"><?php echo htmlspecialchars($bank_account); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <?php if ($request_status == "approve" || $request_status == "pending") { ?>
                            <div class="section-label"><i class='bx bx-cog'></i> Administrative Actions</div>
                            <div class="action-btns">
                                <button class="btn-modern btn-success-gradient" onclick="SucessRequest('success')">
                                    <i class='bx bx-check-shield'></i> API Approve
                                </button>
                                <button class="btn-modern btn-danger-outline" onclick="RejectRequest()">
                                    <i class='bx bx-block'></i> API Reject
                                </button>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px dashed var(--border-dim);">
                                <span style="font-size: 11px; font-weight: 700; color: var(--text-dim);">Processed on <?php echo $request_date_time; ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
  function RejectRequest(){
    Swal.fire({
      title: 'Reject Withdrawal?',
      text: "Cancel this transaction and refund the balance?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Reject',
      cancelButtonText: 'No'
    }).then((result) => {
      if (result.isConfirmed) {
        processWithdrawal('rejected');
      }
    });
  }

  function SucessRequest(status){
    Swal.fire({
      title: 'Approve Withdrawal?',
      text: "Proceed with the payout API and mark as success?",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      cancelButtonText: 'No'
    }).then((result) => {
      if (result.isConfirmed) {
        processWithdrawal(status);
      }
    });
  }

  function processWithdrawal(status) {
    Swal.fire({
      title: 'Processing Payout...',
      text: 'Please wait while we communicate with the API',
      allowOutsideClick: false,
      didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
      url: 'update-request.php',
      method: 'GET',
      data: {
        'order-id': '<?php echo $uniq_id; ?>',
        'order-type': status,
        'ajax': 'true'
      },
      dataType: 'json',
      success: function(res) {
        if(res.status === 'success') {
          Swal.fire({
            title: status === 'success' ? 'Withdrawal Approved' : 'Withdrawal Rejected',
            text: res.message,
            icon: 'success',
            confirmButtonText: 'Acknowledge'
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire('Error', res.message, 'error');
        }
      },
      error: function() {
        Swal.fire('Error', 'Failed to connect to payout server.', 'error');
      }
    });
  }
</script>

    </div>
</div>