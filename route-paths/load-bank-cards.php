<?php
$const_user_id = "";
$content = 5;

if(isset($_GET['USER_ID'])){
 $const_user_id = mysqli_real_escape_string($conn,$_GET["USER_ID"]);   
}
$secret_key = $headerObj -> getAuthorization();
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {
$select_sql = "SELECT * FROM tblallbankcards WHERE tbl_user_id='$const_user_id' ORDER BY id DESC LIMIT $content ";
$select_query = mysqli_query($conn,$select_sql);
    
while($row = mysqli_fetch_assoc($select_query)){
  $index['c_bank_id'] = $row['tbl_uniq_id'];
  $index['c_beneficiary'] = $row['tbl_beneficiary_name'];
  $index['c_bank_name'] = $row['tbl_bank_name'];
  $index['c_bank_account'] = $row['tbl_bank_account'];
  $index['c_bank_ifsc_code'] = $row['tbl_bank_ifsc_code'];
  $index['c_is_primary'] = $row['tbl_bank_card_primary'];

  array_push($resArr['data'], $index);
}

if(mysqli_num_rows($select_query) > 0){ 
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