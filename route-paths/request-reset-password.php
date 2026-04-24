<?php
$const_user_mobile = "";
$const_user_otp = "";
$const_new_password = "";

// getting params through post method
$json = file_get_contents('php://input');
$data = json_decode($json);


if (is_object($data) && property_exists($data, 'MOBILE') && property_exists($data, 'NEW_PASSWORD') && property_exists($data, 'USER_OTP')) {
  $const_user_mobile = mysqli_real_escape_string($conn,$data->MOBILE);
  $const_user_otp = mysqli_real_escape_string($conn,$data->USER_OTP);
  $const_new_password = mysqli_real_escape_string($conn,password_hash($data->NEW_PASSWORD,PASSWORD_BCRYPT));
} else {
  $resArr['status_code'] = "invalid_params";
  echo json_encode($resArr);
  return;
}

  
$select_user_sql = "SELECT * FROM tblusersdata WHERE tbl_mobile_num='{$const_user_mobile}' AND tbl_account_status='true' ";
$select_user_query = mysqli_query($conn,$select_user_sql);
  
if(mysqli_num_rows($select_user_query) > 0){
    $select_user_data = mysqli_fetch_assoc($select_user_query);
    $user_uniq_id = $select_user_data['tbl_uniq_id'];
    $user_status = $select_user_data['tbl_account_status'];
    
    $select_lastotp_sql = "SELECT tbl_mobile_num,tbl_otp,tbl_otp_date,tbl_otp_time FROM tblrecentotp WHERE tbl_mobile_num='{$const_user_mobile}' ORDER BY id DESC LIMIT 1 ";
    $select_lastotp_result = mysqli_query($conn, $select_lastotp_sql) or die('error');
  
    $user_last_otp = "";
    if (mysqli_num_rows($select_lastotp_result) > 0) {
      $response_data = mysqli_fetch_assoc($select_lastotp_result);
      $user_last_otp = $response_data['tbl_otp'];
      $user_last_otp_timestamp = $response_data['tbl_otp_date'].$response_data['tbl_otp_time'];
    }
  
    if($headerObj -> getSecondsBetDates($user_last_otp_timestamp, $curr_date_time) > 600){
      $user_last_otp = ""; 
    }
    
    if($user_status=="true"){
        
      if($user_last_otp==$const_user_otp){
        $update_sql = $conn->prepare("UPDATE tblusersdata SET tbl_password = ?  WHERE tbl_uniq_id = ? ");
        $update_sql->bind_param("ss", $const_new_password, $user_uniq_id);
        $update_sql->execute();

        if ($update_sql->error == "") {
          $resArr['status_code'] = "success";
        }else{
          $resArr['status_code'] = "sql_error";
        }
      }else{
        $resArr['status_code'] = "invalid_otp";
      }
      
    }else{
      $resArr['status_code'] = "account_error"; 
    }

}else{
  $resArr['status_code'] = "invalid_mobile_num"; 
}

mysqli_close($conn);
echo json_encode($resArr);  

?>