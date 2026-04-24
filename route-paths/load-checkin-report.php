<?php

  if(isset($_POST['USER_ID'])){
    $user_id = mysqli_real_escape_string($conn, $_POST['USER_ID']);
  }

  if(isset($_GET['USER_ID'])){
    $user_id = mysqli_real_escape_string($conn, $_GET['USER_ID']);
  }
  
  $select_transaction_sql = "SELECT * FROM tblotherstransactions WHERE tbl_transaction_type='checkin' AND tbl_user_id='{$user_id}' ORDER BY id DESC ";
  $select_transaction_query = mysqli_query($conn,$select_transaction_sql);
  
  if(mysqli_num_rows($select_transaction_query) > 0){
    $select_transaction_data = mysqli_fetch_assoc($select_transaction_query);
    $transaction_note = $select_transaction_data['tbl_transaction_note'];

    $resArr['checkin_days'] = $transaction_note;  
    $resArr['status_code'] = "success";  
    
  }else{
    $resArr['status_code'] = "no_data";
  }

 mysqli_close($conn);
 echo json_encode($resArr);
?>