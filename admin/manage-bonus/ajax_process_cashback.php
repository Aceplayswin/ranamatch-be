<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()!="true"){
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$promo_id_filter = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Default to the last 7 days for the calculation window if not processing via the manual page
$date_from = date('Y-m-d', strtotime('-7 days'));
$date_to = date('Y-m-d');

include 'calculate_cashback_engine.php';

echo json_encode(['status' => 'success', 'message' => $message ?? 'Processed successfully.']);
?>
