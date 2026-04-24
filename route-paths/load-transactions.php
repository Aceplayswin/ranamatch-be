<?php
$resArr['pagination'] = "false";

$const_user_id = "";
$const_page_num = "";
$content = 15;


if(isset($_GET['USER_ID']) && isset($_GET['PAGE_NUM'])){
  $const_user_id = mysqli_real_escape_string($conn,$_GET['USER_ID']);
  $const_page_num = mysqli_real_escape_string($conn,$_GET['PAGE_NUM']);
  $secret_key = $headerObj -> getAuthorization();
}
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
if($const_user_id!="" && $const_page_num!=""){

$offset = ($const_page_num-1)*$content;
$select_sql = "SELECT * FROM  tblotherstransactions WHERE tbl_user_id='{$const_user_id}' ORDER BY id DESC LIMIT {$offset},{$content} ";
$select_query = mysqli_query($conn,$select_sql);
    
while($row = mysqli_fetch_assoc($select_query)){
    if($row['tbl_transaction_type']=="signupbonus"){
      $index['t_title'] = "Signup Bonus";
    }else if($row['tbl_transaction_type']=="commision"){
      $index['t_title'] = $row['tbl_transaction_note'].'-Commision'; 
    }else if($row['tbl_transaction_type']=="checkin"){
      $index['t_title'] = 'Daily CheckIn'; 
    }else if($row['tbl_transaction_type']=="redeemcard"){    
      $index['t_title'] = 'Redeem Card'; 
    }else if($row['tbl_transaction_type']=="deposit bonus"){
      $index['t_title'] = 'Deposit Bonus'; 
    }else if($row['tbl_transaction_type']=="Agent Salary"){
    $index['t_title'] = 'Agent Salary'; 
    }  
      
    $index['t_amount'] = $row['tbl_transaction_amount'];
    $index['t_receive_from'] = $row['tbl_received_from'];
    $index['t_note'] = $row['tbl_transaction_note'];
    $index['t_time_stamp'] = $row['tbl_time_stamp'];

    array_push($resArr['data'], $index);
}

$numRows = mysqli_num_rows($select_query);

if($numRows > 0){
  $resArr['status_code'] = "success";
}else{
  $resArr['status_code'] = "no-records-found";
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