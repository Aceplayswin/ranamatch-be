<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_settings") == "false" && $accessObj->isAllowed("access_recharge") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
} else {
    header('location:../logout-account');
    exit;
}

// Auto-create table if not exists on live server
$create_table_sql = "CREATE TABLE IF NOT EXISTS `tbl_payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `method_name` VARCHAR(100) NOT NULL,
  `method_type` VARCHAR(50) NOT NULL DEFAULT 'upi',
  `account_name` VARCHAR(150) DEFAULT NULL,
  `account_number_or_upi` VARCHAR(255) DEFAULT NULL,
  `ifsc_or_bank_name` VARCHAR(150) DEFAULT NULL,
  `qr_code_image` VARCHAR(255) DEFAULT NULL,
  `min_deposit` DECIMAL(10,2) DEFAULT 100.00,
  `max_deposit` DECIMAL(10,2) DEFAULT 50000.00,
  `bonus_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `instructions` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
@mysqli_query($conn, $create_table_sql);

// Insert default sample methods if table is empty
$check_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tbl_payment_methods");
if ($check_count && ($row_cnt = mysqli_fetch_assoc($check_count)) && $row_cnt['cnt'] == 0) {
    $sample_insert = "INSERT INTO `tbl_payment_methods` 
    (`method_name`, `method_type`, `account_name`, `account_number_or_upi`, `ifsc_or_bank_name`, `qr_code_image`, `min_deposit`, `max_deposit`, `bonus_percentage`, `instructions`, `status`) VALUES
    ('Bank Wire Transfer', 'bank_transfer', 'HDFC Main Business Account', '50200012345678', 'HDFC0001234', '', 500.00, 200000.00, 0.00, 'Transfer directly to account number and upload transfer screenshot or UTR number.', 'active'),
    ('UPI Instant Pay', 'upi', 'Merchant Official', 'paytmqr2810050501011@paytm', 'Paytm Bank', '', 100.00, 50000.00, 5.00, 'Scan QR or copy UPI ID to complete your payment. Enter UTR reference number after payment.', 'active'),
    ('USDT (TRC20)', 'crypto', 'Crypto Deposit Wallet', 'TY1234567890ABCDEF1234567890ABCDEF', 'Tron TRC20', '', 1000.00, 500000.00, 2.00, 'Send USDT TRC20 to the address. 1 USDT = Server Rate.', 'active');";
    @mysqli_query($conn, $sample_insert);
}

// Fetch all payment methods
$sql = "SELECT * FROM tbl_payment_methods ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title><?php echo $APP_NAME; ?>: Manage Payment Methods</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid var(--border-dim);
            padding-bottom: 16px;
        }
        .dash-title h1 { font-size: 24px; font-weight: 800; color: var(--text-main); margin: 0; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

        .btn-modern {
            height: 38px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary-modern { background: #10b981; color: #fff; }
        .btn-primary-modern:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }

        .r-table-wrapper {
            background: rgba(255,255,255,0.02); border-radius: 16px; 
            border: 1px solid var(--border-dim); overflow: hidden;
        }
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(255,255,255,0.03); padding: 14px 18px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-dim); border-bottom: 1px solid var(--border-dim);
        }
        .r-table td {
            padding: 14px 18px; font-size: 13px; color: var(--text-main);
            border-bottom: 1px solid var(--border-dim); vertical-align: middle;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-table tr:hover td { background: rgba(255,255,255,0.02); }

        .type-badge {
            padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800;
            text-transform: uppercase; display: inline-block; background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3);
        }

        .status-pill {
            padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; border: none; cursor: pointer;
        }
        .status-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-inactive { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

        /* Modal custom dark */
        .modal-content {
            background: var(--panel-bg, #111827); border: 1px solid var(--border-dim, #1f2937); color: var(--text-main, #f9fafb); border-radius: 16px;
        }
        .modal-header { border-bottom: 1px solid var(--border-dim, #1f2937); }
        .modal-footer { border-top: 1px solid var(--border-dim, #1f2937); }
        .form-control, .form-select {
            background: var(--input-bg, #1f2937) !important; border: 1px solid var(--border-dim, #374151) !important;
            color: #ffffff !important; border-radius: 10px !important; padding: 10px 14px; font-size: 13px;
        }
        .form-control:focus, .form-select:focus { border-color: #10b981 !important; box-shadow: none !important; }
        .form-label { font-size: 12px; font-weight: 700; color: #9ca3af; margin-bottom: 6px; }
    </style>
</head>
<body class="bg-light">
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div class="dash-title">
                <span class="dash-breadcrumb">Deposit Management</span>
                <h1>Frontend Payment Methods</h1>
            </div>
            <div>
                <button class="btn-modern btn-primary-modern" onclick="openAddModal()">
                    <i class='bx bx-plus-circle'></i> Add Payment Method
                </button>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: rgba(16,185,129,0.2); border: 1px solid #10b981; color: #34d399;">
                <?php echo htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="r-table-wrapper">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width: 50px">#</th>
                        <th>Method Name</th>
                        <th>Type</th>
                        <th>Account Details / UPI / Wallet</th>
                        <th>Min / Max Deposit</th>
                        <th>Bonus %</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {
                        $idx = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td>#<?php echo $idx++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['method_name']); ?></strong>
                            <?php if (!empty($row['qr_code_image'])): ?>
                                <span title="QR Code Attached" style="color: #10b981; margin-left: 4px;"><i class='bx bx-qr-scan'></i></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="type-badge"><?php echo htmlspecialchars($row['method_type']); ?></span></td>
                        <td>
                            <div><strong>Acc/UPI:</strong> <?php echo htmlspecialchars($row['account_number_or_upi'] ?? '-'); ?></div>
                            <?php if (!empty($row['account_name'])): ?>
                                <small style="color: var(--text-dim);">Holder: <?php echo htmlspecialchars($row['account_name']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($row['ifsc_or_bank_name'])): ?>
                                <small style="color: var(--text-dim);"> | Bank/IFSC: <?php echo htmlspecialchars($row['ifsc_or_bank_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            ₹<?php echo number_format($row['min_deposit'], 2); ?> - ₹<?php echo number_format($row['max_deposit'], 2); ?>
                        </td>
                        <td>
                            <?php echo $row['bonus_percentage'] > 0 ? '<span style="color: #34d399; font-weight: 700;">+' . number_format($row['bonus_percentage'], 1) . '%</span>' : '0%'; ?>
                        </td>
                        <td>
                            <a href="toggle_status.php?id=<?php echo $row['id']; ?>" class="status-pill <?php echo $row['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo strtoupper($row['status']); ?>
                            </a>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-sm btn-outline-warning me-1" title="View Details" onclick='viewMethod(<?php echo json_encode($row); ?>)'><i class='bx bx-show'></i></button>
                            <button class="btn btn-sm btn-outline-info me-1" title="Edit" onclick='editMethod(<?php echo json_encode($row); ?>)'><i class='bx bx-edit-alt'></i></button>
                            <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this payment method?')"><i class='bx bx-trash'></i></a>
                        </td>

                    </tr>
                    <?php 
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            <i class='bx bx-credit-card-front' style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No payment methods configured yet. Click "Add Payment Method" to create one.
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="methodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form action="save.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="id" id="method_id" value="">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle" style="font-weight: 800;">Add Payment Method</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Payment Method Name *</label>
                    <input type="text" name="method_name" id="method_name" class="form-control" placeholder="e.g. PhonePe / GPay UPI" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Method Type *</label>
                    <select name="method_type" id="method_type" class="form-select" required>
                        <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                        <option value="qr_code">QR Code Scan & Pay</option>
                        <option value="bank_transfer">Bank Wire Transfer</option>
                        <option value="crypto">USDT / Crypto Wallet</option>
                        <option value="gateway">Payment Gateway</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Account Holder / Beneficiary Name</label>
                    <input type="text" name="account_name" id="account_name" class="form-control" placeholder="e.g. Velplay Games Ltd">
                </div>
                <div class="col-md-6">
                    <label class="form-label">UPI ID / Account No / Crypto Wallet Address *</label>
                    <input type="text" name="account_number_or_upi" id="account_number_or_upi" class="form-control" placeholder="e.g. merchant@ybl or 50200012345" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Bank Name / IFSC / Crypto Network</label>
                    <input type="text" name="ifsc_or_bank_name" id="ifsc_or_bank_name" class="form-control" placeholder="e.g. HDFC0001234 or TRC20">
                </div>
                <div class="col-md-6">
                    <label class="form-label">QR Code Image (Optional)</label>
                    <input type="file" name="qr_code_image" id="qr_code_image" class="form-control" accept="image/*">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Minimum Deposit (₹)</label>
                    <input type="number" step="0.01" name="min_deposit" id="min_deposit" class="form-control" value="100.00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Maximum Deposit (₹)</label>
                    <input type="number" step="0.01" name="max_deposit" id="max_deposit" class="form-control" value="50000.00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bonus Deposit %</label>
                    <input type="number" step="0.01" name="bonus_percentage" id="bonus_percentage" class="form-control" value="0.00">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Instructions for Users (Frontend)</label>
                    <textarea name="instructions" id="instructions" class="form-control" rows="2" placeholder="e.g. Scan QR code or pay to UPI ID. Copy transaction UTR number and paste below."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="active">Active (Visible on Frontend)</option>
                        <option value="inactive">Inactive (Hidden)</option>
                    </select>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary-modern">Save Payment Method</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" style="font-weight: 800;">
          <i class='bx bx-credit-card me-2' style="color: #10b981;"></i> 
          <span id="v_method_name">Payment Method Details</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-7">
            <div class="p-3" style="background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid var(--border-dim);">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <span id="v_method_type" class="type-badge">UPI</span>
                <span id="v_status" class="status-pill status-active">ACTIVE</span>
              </div>
              <div class="mb-2">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Account / UPI / Address</small>
                <div id="v_account_number_or_upi" class="fs-6 fw-bold text-white font-monospace"></div>
              </div>
              <div class="mb-2" id="v_account_name_box">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Account Holder</small>
                <div id="v_account_name" class="fw-semibold text-light"></div>
              </div>
              <div class="mb-2" id="v_ifsc_box">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Bank Name / IFSC / Network</small>
                <div id="v_ifsc_or_bank_name" class="fw-semibold text-light"></div>
              </div>
              <hr style="border-color: var(--border-dim);">
              <div class="row">
                <div class="col-6 mb-2">
                  <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Deposit Limits</small>
                  <div id="v_limits" class="fw-bold text-info"></div>
                </div>
                <div class="col-6 mb-2">
                  <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Deposit Bonus</small>
                  <div id="v_bonus" class="fw-bold text-success"></div>
                </div>
              </div>
              <div class="mt-2" id="v_instructions_box">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Instructions</small>
                <div id="v_instructions" class="p-2 mt-1 rounded text-light" style="background: rgba(0,0,0,0.2); font-size: 12px;"></div>
              </div>
            </div>
          </div>
          <div class="col-md-5 text-center d-flex flex-column align-items-center justify-content-center">
            <div id="v_qr_container" class="p-3 rounded text-center w-100" style="background: #ffffff; border-radius: 16px;">
              <img id="v_qr_img" src="" alt="QR Code Preview" class="img-fluid rounded" style="max-height: 220px;">
            </div>
            <div id="v_no_qr" class="p-4 text-muted text-center border rounded w-100" style="border-style: dashed !important;">
              <i class='bx bx-qr-scan display-4 d-block mb-2'></i>
              <span>No QR Code attached</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modal = null;
let viewModalObj = null;

document.addEventListener("DOMContentLoaded", function() {
    modal = new bootstrap.Modal(document.getElementById('methodModal'));
    viewModalObj = new bootstrap.Modal(document.getElementById('viewModal'));
});

function openAddModal() {
    document.getElementById('method_id').value = '';
    document.getElementById('modalTitle').innerText = 'Add Payment Method';
    document.getElementById('method_name').value = '';
    document.getElementById('method_type').value = 'upi';
    document.getElementById('account_name').value = '';
    document.getElementById('account_number_or_upi').value = '';
    document.getElementById('ifsc_or_bank_name').value = '';
    document.getElementById('min_deposit').value = '100.00';
    document.getElementById('max_deposit').value = '50000.00';
    document.getElementById('bonus_percentage').value = '0.00';
    document.getElementById('instructions').value = '';
    document.getElementById('status').value = 'active';
    modal.show();
}

function editMethod(data) {
    document.getElementById('method_id').value = data.id;
    document.getElementById('modalTitle').innerText = 'Edit Payment Method';
    document.getElementById('method_name').value = data.method_name || '';
    document.getElementById('method_type').value = data.method_type || 'upi';
    document.getElementById('account_name').value = data.account_name || '';
    document.getElementById('account_number_or_upi').value = data.account_number_or_upi || '';
    document.getElementById('ifsc_or_bank_name').value = data.ifsc_or_bank_name || '';
    document.getElementById('min_deposit').value = data.min_deposit || '100.00';
    document.getElementById('max_deposit').value = data.max_deposit || '50000.00';
    document.getElementById('bonus_percentage').value = data.bonus_percentage || '0.00';
    document.getElementById('instructions').value = data.instructions || '';
    document.getElementById('status').value = data.status || 'active';
    modal.show();
}

function viewMethod(data) {
    document.getElementById('v_method_name').innerText = data.method_name || 'Payment Method';
    document.getElementById('v_method_type').innerText = (data.method_type || 'UPI').toUpperCase();
    
    let statusEl = document.getElementById('v_status');
    statusEl.innerText = (data.status || 'active').toUpperCase();
    statusEl.className = 'status-pill ' + (data.status === 'active' ? 'status-active' : 'status-inactive');

    document.getElementById('v_account_number_or_upi').innerText = data.account_number_or_upi || '-';
    document.getElementById('v_account_name').innerText = data.account_name || '-';
    document.getElementById('v_ifsc_or_bank_name').innerText = data.ifsc_or_bank_name || '-';

    document.getElementById('v_limits').innerText = '₹' + parseFloat(data.min_deposit || 0).toLocaleString('en-IN') + ' - ₹' + parseFloat(data.max_deposit || 0).toLocaleString('en-IN');
    document.getElementById('v_bonus').innerText = (parseFloat(data.bonus_percentage || 0) > 0) ? '+' + data.bonus_percentage + '% Extra' : '0%';
    document.getElementById('v_instructions').innerText = data.instructions || 'No instructions provided.';

    if (data.qr_code_image && data.qr_code_image.trim() !== '') {
        document.getElementById('v_qr_img').src = data.qr_code_image;
        document.getElementById('v_qr_container').style.display = 'block';
        document.getElementById('v_no_qr').style.display = 'none';
    } else {
        document.getElementById('v_qr_container').style.display = 'none';
        document.getElementById('v_no_qr').style.display = 'block';
    }

    viewModalObj.show();
}
</script>
</body>
</html>

