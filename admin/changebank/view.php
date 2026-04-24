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
    if ($accessObj->isAllowed("access_settings") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }   
} else {
    header('location:../../logout-account');
    exit;
}

$user_id = $_GET['user_id'] ?? '';
if (empty($user_id)) {
    header('location:index.php');
    exit;
}

$notification = ['show' => false, 'type' => '', 'msg' => ''];

if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $beneficiary = trim($_POST['tbl_beneficiary_name']);
    $bank = trim($_POST['tbl_bank_name']);
    $account = trim($_POST['tbl_bank_account']);
    $ifsc = trim($_POST['tbl_bank_ifsc_code']);

    $sql = "UPDATE tblallbankcards SET tbl_beneficiary_name=?, tbl_bank_name=?, tbl_bank_account=?, tbl_bank_ifsc_code=? WHERE id=? AND tbl_user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssis", $beneficiary, $bank, $account, $ifsc, $id, $user_id);

    if ($stmt->execute()) {
        $notification = ['show' => true, 'type' => 'success', 'msg' => 'Bank details updated successfully!'];
    } else {
        $notification = ['show' => true, 'type' => 'error', 'msg' => 'Failed to update bank details. Please try again.'];
    }
}

// Fetch user info with balance
$u_query = "SELECT tbl_user_name, tbl_balance FROM tblusersdata WHERE tbl_uniq_id = ?";
$u_stmt = $conn->prepare($u_query);
$u_stmt->bind_param("s", $user_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
$user_info = $u_res->fetch_assoc();

// Calculate total deposits
$dep_q = "SELECT SUM(tbl_recharge_amount) as total FROM tblusersrecharge WHERE tbl_user_id = ? AND tbl_request_status = 'success'";
$dep_stmt = $conn->prepare($dep_q);
$dep_stmt->bind_param("s", $user_id);
$dep_stmt->execute();
$total_deposits = ($dep_stmt->get_result()->fetch_assoc())['total'] ?? 0;

// Calculate total withdrawals
$wit_q = "SELECT SUM(tbl_withdraw_amount) as total FROM tbluserswithdraw WHERE tbl_user_id = ? AND tbl_request_status = 'success'";
$wit_stmt = $conn->prepare($wit_q);
$wit_stmt->bind_param("s", $user_id);
$wit_stmt->execute();
$total_withdrawals = ($wit_stmt->get_result()->fetch_assoc())['total'] ?? 0;

// Fetch all bank cards for this user
$cards_query = "SELECT * FROM tblallbankcards WHERE tbl_user_id = ? ORDER BY id DESC";
$cards_stmt = $conn->prepare($cards_query);
$cards_stmt->bind_param("s", $user_id);
$cards_stmt->execute();
$cards = $cards_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php" ?>
    <title>Manage User Bank Cards</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
        :root {
            --card-glass: rgba(255, 255, 255, 0.015);
            --card-border: rgba(255, 255, 255, 0.05);
            --brand: #FFA000;
        }

        body {
            font-family: 'DM Sans', sans-serif !important;
            background-color: var(--page-bg) !important;
            background-image: radial-gradient(var(--border-dim) 1px, transparent 1px) !important;
            background-size: 26px 26px !important;
            min-height: 100vh; color: #fff; margin: 0; padding: 0;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 20px; border-bottom: 1px solid var(--border-dim);
            margin-bottom: 20px; backdrop-filter: blur(10px); background: rgba(0,0,0,0.2);
        }
        .dash-header-left { display: flex; align-items: center; gap: 14px; }
        .back-btn {
            width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-dim); color: #fff; display: flex; align-items: center;
            justify-content: center; font-size: 24px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }
        .back-btn:hover { background: var(--brand); color: #000; transform: translateX(-5px); }

        .dash-breadcrumb { font-size: 10px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: var(--brand); opacity: 0.8; }
        .dash-title { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: -0.5px; }

        .user-info-bar {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-dim);
            border-radius: 20px; padding: 20px 24px; margin: 0 20px 30px;
            display: flex; align-items: center; gap: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: var(--brand); margin-bottom: 6px; opacity: 0.6; }
        .info-value { font-size: 15px; font-weight: 800; color: #fff; }

        .bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            padding: 0 20px 40px;
        }

        .account-card {
            background: var(--card-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .account-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 160, 0, 0.3);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .account-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,160,0,0.03) 0%, transparent 70%);
            pointer-events: none;
        }

        .card-label { font-size: 9px; font-weight: 900; text-transform: uppercase; color: var(--brand); opacity: 0.5; margin-bottom: 8px; display: block; letter-spacing: 1px; }
        .card-inp {
            background: rgba(0,0,0,0.2) !important;
            border: 1px solid rgba(255,255,255,0.05) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            width: 100%;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .card-inp:focus {
            border-color: var(--brand) !important;
            background: rgba(0,0,0,0.3) !important;
            box-shadow: 0 0 15px rgba(255, 160, 0, 0.1) !important;
            outline: none;
        }

        .save-btn {
            width: 100%;
            background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            color: #000;
            border: none;
            padding: 14px;
            border-radius: 14px;
            font-weight: 900;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 160, 0, 0.2);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .save-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(255, 160, 0, 0.4);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .dash-header { flex-direction: column; align-items: flex-start; gap: 16px; padding: 16px; }
            .dash-header-right { width: 100%; }
            .btn-modern { width: 100%; justify-content: center; }
            
            .user-info-bar { 
                flex-wrap: wrap; gap: 20px; padding: 16px; margin: 0 16px 20px;
                border-radius: 16px;
            }
            .info-item { flex: 1 1 120px; }
            .info-item.ms-auto { margin-left: 0 !important; width: 100%; margin-top: 10px; }
            .info-item.ms-auto .btn-modern { width: 100%; }
            
            .bank-grid { 
                grid-template-columns: 1fr; 
                padding: 0 16px 30px;
                gap: 16px;
            }
            .account-card { padding: 20px; border-radius: 20px; }
            .dash-title { font-size: 18px; }
        }

        /* Premium Notification Pop-up */
        .premium-pop {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.4s;
        }
        .premium-pop.show { opacity: 1; visibility: visible; }
        .pop-card {
            background: #111; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 32px; padding: 40px; text-align: center;
            max-width: 320px; width: 90%; transform: scale(0.8); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }
        .premium-pop.show .pop-card { transform: scale(1); }
        .pop-icon {
            width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 40px; margin: 0 auto 24px;
        }
        .pop-success .pop-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .pop-error .pop-icon { background: rgba(244, 63, 94, 0.1); color: #f43f5e; }
        .pop-title { font-size: 24px; font-weight: 900; margin-bottom: 12px; }
        .pop-msg { font-size: 13px; color: rgba(255,255,255,0.5); font-weight: 600; line-height: 1.6; margin-bottom: 30px; }
        .pop-close {
            background: #fff; color: #000; border: none; padding: 12px 30px; border-radius: 12px;
            font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; cursor: pointer;
        }
    </style>
</head>

<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        
        <!-- Header -->
        <div class="dash-header">
            <div class="dash-header-left">
                <a href="index.php" class="back-btn"><i class='bx bx-left-arrow-alt'></i></a>
                <div>
                    <span class="dash-breadcrumb">Security > Bank Management</span>
                    <h1 class="dash-title">Manage Registered Cards</h1>
                </div>
            </div>
            <button class="btn-modern" onclick="window.location.reload()" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-dim); color: #fff; padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 12px;">
                <i class='bx bx-refresh'></i> Refresh Cards
            </button>
        </div>

        <!-- User Identity -->
        <div class="user-info-bar">
            <div class="info-item">
                <span class="info-label">Managing Identity</span>
                <span class="info-value" style="color: var(--brand);"><?= htmlspecialchars($user_id) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($user_info['tbl_user_name'] ?: 'External User') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Real Balance</span>
                <span class="info-value" style="color: #fff;">₹<?= number_format($user_info['tbl_balance'] ?? 0, 2) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Deposits</span>
                <span class="info-value" style="color: var(--accent-emerald);">₹<?= number_format($total_deposits, 2) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Withdrawals</span>
                <span class="info-value" style="color: var(--accent-rose);">₹<?= number_format($total_withdrawals, 2) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Active Cards</span>
                <span class="info-value"><?= $cards->num_rows ?> Registered</span>
            </div>
            <div class="info-item ms-auto">
                <a href="../users-data/manager.php?id=<?= $user_id ?>" class="btn-modern" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-dim); color: #fff; text-decoration: none; font-size: 11px;">
                    <i class='bx bx-user-circle'></i> Manage Full Profile
                </a>
            </div>
        </div>

        <!-- Bank Cards Grid -->
        <div class="bank-grid">
            <?php if ($cards->num_rows > 0): ?>
                <?php while ($row = $cards->fetch_assoc()): ?>
                    <div class="account-card" id="card-<?= $row['id'] ?>">
                        <!-- View Mode -->
                        <div class="view-mode">
                            <span class="card-label">Beneficiary Name</span>
                            <div class="info-value mb-3"><?= htmlspecialchars($row['tbl_beneficiary_name']) ?></div>
                            
                            <span class="card-label">Bank Name</span>
                            <div class="info-value mb-3"><?= htmlspecialchars($row['tbl_bank_name']) ?></div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-7">
                                    <span class="card-label">Account Number</span>
                                    <div class="info-value"><?= htmlspecialchars($row['tbl_bank_account']) ?></div>
                                </div>
                                <div class="col-5">
                                    <span class="card-label">IFSC Code</span>
                                    <div class="info-value"><?= htmlspecialchars($row['tbl_bank_ifsc_code']) ?></div>
                                </div>
                            </div>
                            
                            <button type="button" class="save-btn" onclick="toggleEdit(<?= $row['id'] ?>)" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                                <i class='bx bx-edit-alt'></i> Modify Account
                            </button>
                        </div>

                        <!-- Edit Mode (Hidden by default) -->
                        <div class="edit-mode" style="display: none;">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                
                                <span class="card-label">Beneficiary Name</span>
                                <input type="text" name="tbl_beneficiary_name" value="<?= htmlspecialchars($row['tbl_beneficiary_name']) ?>" class="card-inp" required>
                                
                                <span class="card-label">Bank Name</span>
                                <input type="text" name="tbl_bank_name" value="<?= htmlspecialchars($row['tbl_bank_name']) ?>" class="card-inp" required>
                                
                                <div class="row g-3">
                                    <div class="col-7">
                                        <span class="card-label">Account Number</span>
                                        <input type="text" name="tbl_bank_account" value="<?= htmlspecialchars($row['tbl_bank_account']) ?>" class="card-inp" required>
                                    </div>
                                    <div class="col-5">
                                        <span class="card-label">IFSC Code</span>
                                        <input type="text" name="tbl_bank_ifsc_code" value="<?= htmlspecialchars($row['tbl_bank_ifsc_code']) ?>" class="card-inp" required>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="save-btn" onclick="toggleEdit(<?= $row['id'] ?>)" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2); width: 40px;">
                                        <i class='bx bx-x'></i>
                                    </button>
                                    <button type="submit" name="update" class="save-btn flex-grow-1" onclick="return confirm('Are you absolutely sure you want to update these bank details? Please verify the Account Number and IFSC before proceeding.')">
                                        <i class='bx bx-check-double'></i> Commit Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 120px; background: rgba(255,255,255,0.01); border-radius: 30px; border: 2px dashed rgba(255,255,255,0.05);">
                    <i class='bx bx-credit-card-front' style="font-size: 80px; color: var(--brand); opacity: 0.2; display: block; margin-bottom: 20px;"></i>
                    <p style="text-transform: uppercase; font-weight: 900; letter-spacing: 3px; font-size: 14px; opacity: 0.5;">No active bank accounts found</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- Premium Notification Modal -->
<div class="premium-pop <?php echo $notification['show'] ? 'show' : ''; ?> <?php echo 'pop-'.$notification['type']; ?>" id="notif-pop">
    <div class="pop-card">
        <div class="pop-icon">
            <?php echo $notification['type'] === 'success' ? "<i class='bx bx-check-circle'></i>" : "<i class='bx bx-error-alt'></i>"; ?>
        </div>
        <h3 class="pop-title"><?php echo $notification['type'] === 'success' ? 'Confirmed' : 'System Error'; ?></h3>
        <p class="pop-msg"><?php echo $notification['msg']; ?></p>
        <button class="pop-close" onclick="closePop()">Acknowledge</button>
    </div>
</div>

<script>
    function toggleEdit(id) {
        const card = document.getElementById('card-' + id);
        const viewMode = card.querySelector('.view-mode');
        const editMode = card.querySelector('.edit-mode');
        
        if (viewMode.style.display === 'none') {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
        } else {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        }
    }

    function closePop() {
        document.getElementById('notif-pop').classList.remove('show');
        // Clean URL after acknowledgment
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href.split('#')[0]);
        }
    }
    
    // Auto-close after 5 seconds if success
    <?php if ($notification['show'] && $notification['type'] === 'success'): ?>
    setTimeout(closePop, 5000);
    <?php endif; ?>
</script>
</body>
</html>
