<?php
$resArr['account_balance'] = "0";


$const_user_id = "";
$const_recharge_amount = "";
$const_recharge_mode = "";
$const_recharge_details = "";
$const_uniq_Id = $headerObj -> getRandomString(15);


if(isset($_GET['USER_ID']) && isset($_GET['RECHARGE_AMOUNT']) && isset($_GET['RECHARGE_MODE'])){
  $const_user_id = mysqli_real_escape_string($conn,$_GET['USER_ID']);
  $const_recharge_amount = mysqli_real_escape_string($conn,$_GET['RECHARGE_AMOUNT']);
  $const_recharge_mode = mysqli_real_escape_string($conn,$_GET['RECHARGE_MODE']);
  $const_recharge_details = mysqli_real_escape_string($conn,$_GET['RECHARGE_DETAILS']);
}
$secret_key = $headerObj -> getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {

if($const_user_id!="" && $const_recharge_amount!="" && $const_recharge_mode!="" && $const_recharge_details!=""){

// validate entered utr code
function checkUTRCodeExist($conn,$verifyDetail){
    $returnVal = "false";
    
    // verify details in array
    $data_arr = explode(",", $verifyDetail);
    
    $select_sql = "SELECT * FROM tblusersrecharge WHERE tbl_recharge_details like '%$data_arr[0]%' ";
    $select_query = mysqli_query($conn,$select_sql);
          
    if(mysqli_num_rows($select_query) > 0){
        while ($recharge_data = mysqli_fetch_assoc($select_query)){
            $str_arr = explode(",", $recharge_data['tbl_recharge_details']);
            
            if(count($str_arr) > 1){
                    
                if($str_arr[0]==$data_arr[0]){
                   $returnVal = "true";
                }else if($str_arr[0]==$data_arr[0]){
                   $returnVal = "true";
                }
                
            }else if($str_arr[0]==$data_arr[0]){
                $returnVal = "true";
            }
        }
    }
    
    return $returnVal;
}

$available_balance = "";
$select_sql = "SELECT tbl_balance,tbl_account_status FROM tblusersdata WHERE tbl_uniq_id='$const_user_id' ";
$select_query = mysqli_query($conn,$select_sql);
  
if(mysqli_num_rows($select_query) > 0){
    $res_data = mysqli_fetch_assoc($select_query);
  
    if($res_data['tbl_account_status']=="true"){
        if(checkUTRCodeExist($conn,$const_recharge_details)=="true"){
            $resArr['status_code'] = "utr_exit";
        }else{
            
          if($const_recharge_mode=="UPIPay"){
            $request_status = "pending";
          
            $insert_sql = $conn->prepare("INSERT INTO tblusersrecharge(tbl_uniq_id,tbl_user_id,tbl_recharge_amount,tbl_recharge_mode,tbl_recharge_details,tbl_request_status,tbl_time_stamp) VALUES(?,?,?,?,?,?,?)");
            $insert_sql->bind_param("sssssss", $const_uniq_Id,$const_user_id,$const_recharge_amount,$const_recharge_mode, $const_recharge_details,$request_status,$curr_date_time);
            $insert_sql->execute();
  
            if ($insert_sql->error == ""){
            $resArr['status_code'] = "pending";
            }
          }else if($const_recharge_mode=="BankPay"){
            $request_status = "pending";
          
            $insert_sql = $conn->prepare("INSERT INTO tblusersrecharge(tbl_uniq_id,tbl_user_id,tbl_recharge_amount,tbl_recharge_mode,tbl_recharge_details,tbl_request_status,tbl_time_stamp) VALUES(?,?,?,?,?,?,?)");
            $insert_sql->bind_param("sssssss", $const_uniq_Id,$const_user_id,$const_recharge_amount,$const_recharge_mode, $const_recharge_details,$request_status,$curr_date_time);
            $insert_sql->execute();
  
            if ($insert_sql->error == "") {
              $resArr['status_code'] = "pending";
              $resArr['transaction_id'] = $const_uniq_Id;
            }
          }
            
        }
    }else{
        $resArr['status_code'] = "failed2"; 
    }
}else{
    $resArr['status_code'] = "failed1";
}

}else{
  $resArr['status_code'] = "invalid_params";  
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>