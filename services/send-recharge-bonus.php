<?php
define("ACCESS_SECURITY","true");
include '../security/config.php';
include '../security/constants.php';
set_time_limit(1400);

if(isset($_GET['accessToken'])){
  $accessToken = mysqli_real_escape_string($conn,$_GET["accessToken"]);
  if($CRON_ACCESS_TOKEN!=$accessToken){
    echo "Access Token Error";
    return;
  }
}else{
    echo "Access Token Error";
    return;
}

date_default_timezone_set("Asia/Kolkata");
$curr_date_time = date("d-m-Y h:i a");

$currIndex = 0;
$rewardName = "Recharge Bonus";
$rewardType = "commision";
$recharge_refer_bonus = 0;

// minimum recharge accept bonus
$MIN_ALLOWED_RECHARGE = 110;

// all sql queries
$new_transaction_sql = "INSERT INTO tblotherstransactions (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) VALUES ";

$select_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under!='' AND tbl_account_level >= 2 ORDER BY id ASC";
$select_result = mysqli_query($conn, $select_sql) or die(mysqli_error($conn));

if (mysqli_num_rows($select_result) > 0) {
  
  while($row = mysqli_fetch_assoc($select_result)){
    $user_uniq_id = $row['tbl_uniq_id'];
    $user_refered_by = $row['tbl_joined_under'];
    
    $search_transactions_sql = "SELECT * FROM tblotherstransactions WHERE tbl_user_id='{$user_refered_by}' AND tbl_received_from='{$user_uniq_id}' AND tbl_transaction_note='{$rewardName}' ORDER BY id ASC";
    $search_transactions_result = mysqli_query($conn, $search_transactions_sql) or die(mysqli_error($conn));
    
    if(mysqli_num_rows($search_transactions_result) <= 0){
      $recharge_record_sql = "SELECT * FROM tblusersrecharge WHERE tbl_request_status='success' AND tbl_user_id='$user_uniq_id' AND tbl_recharge_amount >= $MIN_ALLOWED_RECHARGE ORDER BY id ASC";
      $recharge_record_result = mysqli_query($conn, $recharge_record_sql) or die(mysqli_error($conn));
    
      if (mysqli_num_rows($recharge_record_result) > 0) {
        $temp_res_data = mysqli_fetch_assoc($recharge_record_result);
        $user_recharge_amount = $temp_res_data['tbl_recharge_amount'];
        
        if($RECHARGE_BONUS_TYPE == "normal"){
          if($user_recharge_amount >= 100000){
            $recharge_refer_bonus = 5888; 
          }else if($user_recharge_amount >= 50000){
            $recharge_refer_bonus = 2888; 
          }else if($user_recharge_amount >= 10000){
            $recharge_refer_bonus = 488; 
          }else if($user_recharge_amount >= 5000){
            $recharge_refer_bonus = 288; 
          }else if($user_recharge_amount >= 1000){
            $recharge_refer_bonus = 188; 
          }else if($user_recharge_amount >= 500){
            $recharge_refer_bonus = 108; 
          }else if($user_recharge_amount >= 200){
            $recharge_refer_bonus = 48; 
          }else if($user_recharge_amount >= $MIN_ALLOWED_RECHARGE){
            $recharge_refer_bonus = 28;
          }   
        }else{
          $recharge_refer_bonus = number_format($user_recharge_amount*$RECHARGE_BONUS_PERCENTAGE/100, 2, '.', '');   
        }
        
        if($recharge_refer_bonus > 0){
            $update_balance_sql = "UPDATE tblusersdata SET tbl_balance = tbl_balance + '{$recharge_refer_bonus}' WHERE tbl_uniq_id = '{$user_refered_by}'";
            mysqli_query($conn, $update_balance_sql);  
        
          if($currIndex > 0){
            $new_transaction_sql .= ",('".$user_refered_by."', '".$user_uniq_id."', '".$rewardType."', '".$recharge_refer_bonus."', '".$rewardName."', '".$curr_date_time."')"; 
          }else{
            $new_transaction_sql .= "('".$user_refered_by."', '".$user_uniq_id."', '".$rewardType."', '".$recharge_refer_bonus."', '".$rewardName."', '".$curr_date_time."')"; 
          }  
          
          $currIndex++;
        }
        
      }
    }

  }
  
  
//   executing all queries
  if($currIndex > 0){
    mysqli_query($conn, $new_transaction_sql);  
    
    echo 'Bonus sended to all eligible users (success)<br>Total: '.$currIndex;
  }else{
    echo 'No eligible user found!';
  }

}else{
    echo 'No eligible user found!';
}

mysqli_close($conn);
?>