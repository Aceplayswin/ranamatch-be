<?php
define("ACCESS_SECURITY", "true");
include __DIR__ . '/../../../security/config.php';
include __DIR__ . '/../../../security/constants.php';
include __DIR__ . '/../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../../logout-account');
    exit;
}

// Fetch commission summary statistics
$statSql = "SELECT 
    COUNT(*) as total_entries,
    COALESCE(SUM(amount), 0) as total_commission,
    COALESCE(SUM(CASE WHEN entry_type = 'rev_share' THEN amount ELSE 0 END), 0) as total_revshare,
    COALESCE(SUM(CASE WHEN entry_type = 'cpa' THEN amount ELSE 0 END), 0) as total_cpa
FROM affiliate_commission_ledger";
$statRes = mysqli_query($conn, $statSql);
$stats = $statRes ? mysqli_fetch_assoc($statRes) : [];

// Fetch recent commission records
$recordsSql = "SELECT l.*, a.full_name as affiliate_name, a.affiliate_code as referral_code 
               FROM affiliate_commission_ledger l 
               LEFT JOIN affiliates a ON l.affiliate_id = a.id 
               ORDER BY l.created_at DESC LIMIT 50";
$recordsRes = mysqli_query($conn, $recordsSql);

// Check last cron execution log
$cronLogFile = __DIR__ . '/../../../cron/cron_execution.log';
$lastCronLog = file_exists($cronLogFile) ? tailFile($cronLogFile, 5) : "No automated cron log recorded yet.";

function tailFile($filepath, $lines = 5) {
    $data = @file($filepath);
    if (!$data) return "Log file empty or unreadable.";
    return implode("", array_slice($data, -$lines));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . "/../../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Run Affiliate Commissions</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include __DIR__ . "/../../components/theme-variables.php"; ?>

        body {
            font-family: var(--font-body) !important;
            background-color: var(--page-bg) !important;
            min-height: 100vh;
            color: #f8fafc !important;
            margin: 0; padding: 0; overflow-x: hidden;
        }

        .dash-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 16px; flex-wrap: wrap; gap: 14px;
        }
        .dash-title h1 { font-size: 26px; font-weight: 800; color: #ffffff !important; margin: 0; letter-spacing: -0.5px; }
        .dash-breadcrumb { font-size: 11px; font-weight: 700; color: #38bdf8 !important; text-transform: uppercase; letter-spacing: 1.2px; }

        .stat-grid-clean {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px; margin-bottom: 24px;
        }
        .stat-card-clean {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px; padding: 18px 20px;
            display: flex; flex-direction: column; gap: 8px;
            backdrop-filter: blur(12px);
        }
        .stat-card-clean .label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-card-clean .val { font-size: 24px; font-weight: 800; color: #ffffff; }

        .panel-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px; padding: 22px; margin-bottom: 24px;
            backdrop-filter: blur(12px);
        }

        .btn-run-batch {
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            color: #ffffff; border: none; padding: 10px 24px;
            border-radius: 10px; font-size: 13px; font-weight: 700;
            cursor: pointer; transition: all 0.2s; display: inline-flex;
            align-items: center; gap: 8px; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-run-batch:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.35);
            color: #ffffff;
        }

        .table-custom { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .table-custom th {
            font-size: 10px; text-transform: uppercase; font-weight: 700;
            color: #94a3b8; padding: 12px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            letter-spacing: 0.8px; background: rgba(255, 255, 255, 0.02);
        }
        .table-custom td {
            font-size: 13px; color: #e2e8f0; padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include __DIR__ . "/../../components/side-menu.php"; ?>
    <div class="admin-main-content flex-grow-1 p-4" style="margin-left: 260px;">

        <div class="dash-header">
            <div>
                <span class="dash-breadcrumb">Affiliate System &gt; Calculation</span>
                <div class="dash-title"><h1>Run Affiliate Commissions</h1></div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary p-2"><i class='bx bx-time-five'></i> Server Time: <?php echo date('Y-m-d H:i:s T'); ?></span>
            </div>
        </div>

        <!-- Metrics -->
        <div class="stat-grid-clean">
            <div class="stat-card-clean">
                <span class="label">Total Generated Commission</span>
                <span class="val text-success">₹<?php echo number_format($stats['total_commission'] ?? 0, 2); ?></span>
            </div>
            <div class="stat-card-clean">
                <span class="label">Total RevShare</span>
                <span class="val text-info">₹<?php echo number_format($stats['total_revshare'] ?? 0, 2); ?></span>
            </div>
            <div class="stat-card-clean">
                <span class="label">Total CPA Bonus</span>
                <span class="val text-warning">₹<?php echo number_format($stats['total_cpa'] ?? 0, 2); ?></span>
            </div>
            <div class="stat-card-clean">
                <span class="label">Ledger Records</span>
                <span class="val text-primary"><?php echo number_format($stats['total_entries'] ?? 0); ?></span>
            </div>
        </div>

        <!-- Trigger Panel -->
        <div class="panel-box">
            <h5 class="fw-bold text-light mb-3"><i class='bx bx-play-circle text-info me-2'></i> Manual Commission Batch Trigger</h5>
            <p class="text-secondary small mb-4">
                Execute the daily commission worker to calculate live wagers, compute Net Gaming Revenue (NGR), apply RevShare & CPA rules, and credit affiliate available balances.
            </p>

            <form id="runCommissionForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-bold uppercase">Select Settlement Period Date</label>
                    <input type="date" id="periodDateInput" name="period_date" class="form-control bg-dark text-light border-secondary" value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn-run-batch" id="btnRunCommission" onclick="runCommissionBatch()">
                        <i class='bx bx-rocket'></i> Calculate &amp; Credit Commissions Now
                    </button>
                </div>
            </form>

            <div id="batchResultAlert" class="mt-4" style="display: none;"></div>
        </div>

        <!-- Automation Cron Status -->
        <div class="panel-box">
            <h5 class="fw-bold text-light mb-2"><i class='bx bx-cog text-warning me-2'></i> Automated Daily Cron Information</h5>
            <p class="text-secondary small mb-3">
                To run commissions automatically every night without manual intervention, schedule a daily cron job on your production server:
            </p>
            <div class="p-3 rounded bg-dark border border-secondary text-info font-monospace small mb-3" style="user-select: all;">
                0 1 * * * curl -s "https://api.velplay365.com/cron/daily_commission.php?token=VELPLAY_CRON_SECURE_TOKEN_2026" &gt; /dev/null 2&gt;&amp;1
            </div>
            <h6 class="fw-bold text-secondary small uppercase mb-2">Recent Cron Execution Log</h6>
            <pre class="bg-dark p-3 text-light rounded border border-secondary small mb-0" style="max-height: 150px; overflow-y: auto;"><?php echo htmlspecialchars($lastCronLog); ?></pre>
        </div>

        <!-- Ledger Table -->
        <div class="panel-box">
            <h5 class="fw-bold text-light mb-3"><i class='bx bx-list-ul text-primary me-2'></i> Recent Commission Ledger</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Affiliate</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Calculation Details</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recordsRes && mysqli_num_rows($recordsRes) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($recordsRes)): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-light"><?php echo htmlspecialchars($row['affiliate_name'] ?? 'Affiliate #' . $row['affiliate_id']); ?></div>
                                        <div class="text-secondary small"><?php echo htmlspecialchars($row['referral_code'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $row['entry_type'] === 'rev_share' ? 'bg-info' : 'bg-warning text-dark'; ?>">
                                            <?php echo strtoupper($row['entry_type'] ?? 'COMMISSION'); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">+₹<?php echo number_format($row['amount'], 2); ?></td>
                                    <td><span class="badge bg-success"><?php echo strtoupper($row['status'] ?? 'APPROVED'); ?></span></td>
                                    <td class="text-secondary small"><?php echo htmlspecialchars($row['base_kind'] . ' (Base: ₹' . number_format($row['base_amount'] ?? 0, 2) . ' @ ' . $row['rate'] . '%)'); ?></td>
                                    <td class="text-secondary small"><?php echo $row['created_at']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No commission ledger records found. Click the button above to calculate commissions!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function runCommissionBatch() {
    const periodDate = document.getElementById('periodDateInput').value;
    const btn = document.getElementById('btnRunCommission');
    const alertBox = document.getElementById('batchResultAlert');

    if (!periodDate) {
        Swal.fire('Warning', 'Please select a period date', 'warning');
        return;
    }

    Swal.fire({
        title: 'Run Commission Calculation?',
        text: `Calculating daily commissions for period date: ${periodDate}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#06b6d4',
        confirmButtonText: 'Yes, Run Calculation'
    }).then((result) => {
        if (result.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";
            alertBox.style.display = 'none';

            fetch('run/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ period_date: periodDate })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-rocket'></i> Calculate & Credit Commissions Now";
                
                if (data.status === 'success') {
                    const stats = data.data;
                    alertBox.className = 'alert alert-success mt-4';
                    alertBox.innerHTML = `
                        <h6 class='fw-bold mb-2'><i class='bx bx-check-circle me-1'></i> Commission Batch Completed!</h6>
                        <ul class='mb-0 small'>
                            <li>Processed: <b>${stats.records_processed}</b> records</li>
                            <li>Total Generated Commission: <b>₹${parseFloat(stats.total_commission_generated).toFixed(2)}</b></li>
                        </ul>
                    `;
                    alertBox.style.display = 'block';

                    Swal.fire({
                        title: 'Commissions Processed!',
                        text: `Successfully processed ${stats.records_processed} records. Total generated: ₹${stats.total_commission_generated}`,
                        icon: 'success'
                    }).then(() => location.reload());
                } else {
                    alertBox.className = 'alert alert-danger mt-4';
                    alertBox.innerHTML = `<b>Error:</b> ${data.message}`;
                    alertBox.style.display = 'block';
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-rocket'></i> Calculate & Credit Commissions Now";
                Swal.fire('Error', 'Server request failed', 'error');
            });
        }
    });
}
</script>
</body>
</html>
