<?php
$const_user_id = "";
$const_page_num = "";
$content = 30;

if(isset($_GET['USER_ID']) && isset($_GET['PAGE_NUM'])){
  $const_user_id = mysqli_real_escape_string($conn,$_GET['USER_ID']);
  $const_page_num = mysqli_real_escape_string($conn,$_GET['PAGE_NUM']);
  $secret_key = $headerObj -> getAuthorization();
}
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
$offset = ((int)$const_page_num-1)*$content;
$select_sql = "SELECT * FROM tbluserswithdraw WHERE tbl_user_id='{$const_user_id}' ORDER BY id DESC LIMIT {$offset},{$content} ";
$select_query = mysqli_query($conn,$select_sql);
    
while($row = mysqli_fetch_assoc($select_query)){
  $withdraw_details_arr = explode(",",$row['tbl_withdraw_details']);
  
  $index['w_uniq_id'] = $row['tbl_uniq_id'];
  $index['w_amount'] = $row['tbl_withdraw_amount'];
  $index['w_request'] = $row['tbl_withdraw_request'];
  $index['w_remark'] = $row['tbl_remark'];
  $index['w_beneficiary'] = $withdraw_details_arr[0];
  $index['w_bank_name'] =  $withdraw_details_arr[3];
  $index['w_bank_account'] = $withdraw_details_arr[1];
  $index['w_bank_ifsc'] = $withdraw_details_arr[2];
  $index['w_date'] = $row['tbl_time_stamp'];
  $index['w_time'] = $row['tbl_time_stamp'];
  $index['w_status'] = $row['tbl_request_status'];

  array_push($resArr['data'], $index);
}

$numRows = mysqli_num_rows($select_query);

if($numRows > 0){
  $resArr['status_code'] = "success";
}else{
  $resArr['status_code'] = "no-records-found";
}
} else {
    $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>