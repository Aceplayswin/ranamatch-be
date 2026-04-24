<?php
$resArr['account_balance'] = "0";
$user_id = "";
$const_giftcard_id = "";


// getting params through post method
$json = file_get_contents('php://input');
$data = json_decode($json);


// return request
function returnRequest($resArr){
   echo json_encode($resArr);
   exit();
}


if (is_object($data) && property_exists($data, 'USER_ID') && property_exists($data, 'GIFTCARD_ID')) {
  $user_id = mysqli_real_escape_string($conn,$data->USER_ID);
  $const_giftcard_id = mysqli_real_escape_string($conn,$data->GIFTCARD_ID);
  $secret_key = $headerObj -> getAuthorization();
} else {
  $resArr['status_code'] = "invalid_params";
  returnRequest($resArr);
}

$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);
if (mysqli_num_rows($select_query) > 0) {     
$select_gift_sql = "SELECT * FROM tblgiftcards WHERE tbl_giftcard='{$const_giftcard_id}' AND tbl_giftcard_status='true' ";
$select_gift_query = mysqli_query($conn,$select_gift_sql);
  
if(mysqli_num_rows($select_gift_query) > 0){
    $select_gift_data = mysqli_fetch_assoc($select_gift_query);
    $gift_amount = $select_gift_data['tbl_giftcard_bonus'];
    $gift_limit = $select_gift_data['tbl_giftcard_limit'];
    $is_balance_limit = $select_gift_data['tbl_giftcard_balance_limit'];
    $is_targeted_user = $select_gift_data['tbl_giftcard_targeted_id'];
    
    if($is_targeted_user!="none" && $is_targeted_user != $user_id){
        $resArr['status_code'] = "code_not_exist";
    }else{
        
      $select_sql = "SELECT tbl_balance,tbl_account_status FROM tblusersdata WHERE tbl_uniq_id='$user_id' AND tbl_account_status='true' ";
      $select_query = mysqli_query($conn,$select_sql);

      if(mysqli_num_rows($select_query) > 0){
        $res_data = mysqli_fetch_assoc($select_query);
        $user_available_balance = $res_data['tbl_balance'];

        if($is_balance_limit!="none" && (float)$user_available_balance < (float)$is_balance_limit){
            $resArr['status_code'] = "balance_limit";
        }else{
          $select_gift_sql = "SELECT * FROM tblotherstransactions WHERE tbl_user_id='$user_id' AND tbl_transaction_note='{$const_giftcard_id}' ";
          $select_gift_query = mysqli_query($conn,$select_gift_sql);
      
          if(mysqli_num_rows($select_gift_query) <= 0){
            $select_gift_sql = "SELECT * FROM tblotherstransactions WHERE tbl_transaction_note='{$const_giftcard_id}' ";
            $select_gift_query = mysqli_query($conn,$select_gift_sql);
      
            if(mysqli_num_rows($select_gift_query) < $gift_limit){
              
              $receive_from = "app";
              $transaction_type = "redeemcard";
              $insert_sql = $conn->prepare("INSERT INTO tblotherstransactions(tbl_user_id,tbl_received_from,tbl_transaction_type,tbl_transaction_amount,tbl_transaction_note,tbl_time_stamp) VALUES(?,?,?,?,?,?)");
              $insert_sql->bind_param("ssssss", $user_id, $receive_from, $transaction_type,$gift_amount,$const_giftcard_id,$curr_date_time);
              $insert_sql->execute();
    
              if ($insert_sql->error == ""){
    
               $updated_balance = $user_available_balance+$gift_amount;
               $update_sql = $conn->prepare("UPDATE tblusersdata SET tbl_balance = ? WHERE tbl_uniq_id = ?");
               $update_sql->bind_param("ss", $updated_balance, $user_id);
               $update_sql->execute();
               
                $new_card_status = "expired";
                if($is_targeted_user!="none"){
                   $update_sql = $conn->prepare("UPDATE tblgiftcards SET tbl_giftcard_status = ? WHERE tbl_giftcard = ?");
                   $update_sql->bind_param("ss", $new_card_status, $const_giftcard_id);
                   $update_sql->execute();
                }
                
                if(mysqli_num_rows($select_gift_query) == $gift_limit-1){
                    $new_card_status = "expired";
                    $update_sql = $conn->prepare("UPDATE tblgiftcards SET tbl_giftcard_status = ? WHERE tbl_giftcard = ?");
                    $update_sql->bind_param("ss", $new_card_status, $const_giftcard_id);
                    $update_sql->execute();
                }
    
               if ($update_sql->error == "") {
                 $resArr['account_balance'] = $updated_balance;
                 $resArr['status_code'] = "success";
               }else{
                 $resArr['status_code'] = "sql_failed";
               }
                
              }
            }else{
              $new_card_status = "expired";
              $update_sql = $conn->prepare("UPDATE tblgiftcards SET tbl_giftcard_status = ? WHERE tbl_giftcard = ?");
              $update_sql->bind_param("ss", $new_card_status, $const_giftcard_id);
              $update_sql->execute();
                
              $resArr['status_code'] = "code_not_exist";
            }
         }else{
          $resArr['status_code'] = "already_applied";
         }
         
        }
      }
    }
}else{
    $resArr['status_code'] = "code_not_exist";
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>