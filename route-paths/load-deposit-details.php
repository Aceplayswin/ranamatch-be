<?php
$const_user_id = "";

if (isset($_GET['USER_ID'])) {
    $const_user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
}

$secret_key = $headerObj->getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}'";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
    $configData = file_get_contents("../payments/config.json");
    if (empty($configData) || is_null(json_decode($configData, true))) {
        $resArr['status_code'] = "no-records-found";
    } else {
        $bankDetails = json_decode($configData, true);

        // Fetch Dynamic Recharge Options from tblservices
        $options_sql = "SELECT tbl_service_value FROM tblservices WHERE tbl_service_name='RECHARGE_OPTIONS'";
        $options_query = mysqli_query($conn, $options_sql);
        $deposit_options = "100,500,1000,2000,5000"; // Fallback
        if ($opt_row = mysqli_fetch_assoc($options_query)) {
            $deposit_options = $opt_row['tbl_service_value'];
        }

        $resArr['status_code'] = "success";
        $resArr['UPI'] = [
            "UPI_ID_1" => $bankDetails['UPI_ID_1'],
            "UPI_ID_2" => $bankDetails['UPI_ID_2']
        ];
        $resArr['BANK_DETAILS'] = $bankDetails['BANK_DETAILS'];
        $resArr['deposit_options'] = $deposit_options;
    }
} else {
    $resArr['status_code'] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr, JSON_PRETTY_PRINT);
?>