<?php
$const_user_id = "";

if(isset($_GET['USER_ID'])){
 $const_user_id = mysqli_real_escape_string($conn,$_GET["USER_ID"]);   
}

$secret_key = $headerObj -> getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {

// getting all services
$service_sql = "SELECT * FROM tblservices";
$service_query = mysqli_query($conn,$service_sql);
    
$service_min_withdrawl = 0;
$service_withdrawl_perday = 1;
    
while($serviceRow = mysqli_fetch_assoc($service_query)){
    if($serviceRow['tbl_service_name']=="MIN_WITHDRAW"){
        $service_min_withdrawl = $serviceRow['tbl_service_value'];  
    }else if($serviceRow['tbl_service_name']=="MAX_WITHDRAWL_PERDAY"){
        $service_withdrawl_perday = $serviceRow['tbl_service_value'];  
    }
}


$select_userdata_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}'";
$select_userdata_query = mysqli_query($conn,$select_userdata_sql);

if(mysqli_num_rows($select_userdata_query) > 0){
  $resp_data = mysqli_fetch_assoc($select_userdata_query);
  $account_balance = $resp_data['tbl_balance'];
  $required_play_balance = $resp_data['tbl_requiredplay_balance'];
  
  $withdrawable_balance = $account_balance - $required_play_balance;
  if($withdrawable_balance <= 0){
      $withdrawable_balance = "0.00";
  }
  
  $resArr['account_balance'] = $account_balance;
  $resArr['withdrawable_balance'] = number_format($withdrawable_balance,2);
  $resArr['required_play_balance'] = $required_play_balance;
  $resArr['minimum_withdrawl'] = $service_min_withdrawl;
  $resArr['withdrawl_perday'] = $service_withdrawl_perday;
  
  $select_sql = "SELECT * FROM tblallbankcards WHERE tbl_user_id='{$const_user_id}' AND tbl_bank_card_primary='true' ";
  $select_query = mysqli_query($conn,$select_sql);
  
  if(mysqli_num_rows($select_query) <= 0){
    $resArr['status_code'] = "404";
  }else{
    $resArr['status_code'] = "success";
    
    $res_data = mysqli_fetch_assoc($select_query);
 
    $index['c_beneficiary'] = $res_data['tbl_beneficiary_name'];
    $index['c_bank_name'] = $res_data['tbl_bank_name'];
    $index['c_bank_account'] = $res_data['tbl_bank_account'];
    $index['c_bank_ifsc_code'] = $res_data['tbl_bank_ifsc_code'];
    
    array_push($resArr['data'], $index);
  }

}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>