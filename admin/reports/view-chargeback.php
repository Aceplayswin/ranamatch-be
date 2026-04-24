<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
session_cache_limiter("");
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") { header('location:../logout-account'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title>Chargeback Report</title>
    <link href='../style.css' rel='stylesheet'>
    <style>
        <?php include "../components/theme-variables.php"; ?>
        body { font-family: 'DM Sans', sans-serif !important; background: var(--page-bg); color: var(--text-main); margin: 0; }
        .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60vh; text-align: center; }
        .empty-state i { font-size: 64px; color: var(--accent-rose); opacity: 0.3; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content">
        <div class="dash-header">
            <div class="dash-header-left">
                <span class="dash-breadcrumb">Finance > Reports</span>
                <h1 class="dash-title">Chargeback Data</h1>
            </div>
            <div class="dash-header-right">
                <button class="btn-modern btn-outline-modern" onclick="window.location.href='index.php'">
                    <i class='bx bx-refresh'></i> Back to Reports
                </button>
            </div>
        </div>

        <div class="empty-state">
            <i class='bx bx-shield-quarter'></i>
            <h3>No Chargeback Records</h3>
            <p class="text-dim">Dispute monitoring is active. No disputed transactions found at this time.</p>
        </div>
    </div>
</div>
</body>
</html>
