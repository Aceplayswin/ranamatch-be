<?php
$const_user_id = "";

if(isset($_GET['USER_ID'])){
  $const_user_id = mysqli_real_escape_string($conn,$_GET['USER_ID']);
}

if($const_user_id!=""){

    $secret_key = $headerObj -> getAuthorization();
   
    if($secret_key=="null" || $secret_key==""){
      $resArr['status_code'] = "authorization_error";
      echo json_encode($resArr);
      return;
    }else{
      $select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
      $select_query = mysqli_query($conn, $select_sql);

      if (mysqli_num_rows($select_query) <= 0) {
        $resArr['status_code'] = "authorization_error";
        echo json_encode($resArr);
        return;  
      }
    }
   
}else{
    $resArr['status_code'] = "invalid_params";
    echo json_encode($resArr);
    return;
}

$data_sql = "SELECT * FROM  tblusersactivity WHERE tbl_user_id='{$const_user_id}' ORDER BY id DESC";
$data_query = mysqli_query($conn,$data_sql);
    
while($row = mysqli_fetch_assoc($data_query)){
   $index['a_uniq_id'] = $row['tbl_uniq_id'];
   $index['a_device_ip'] = $row['tbl_device_ip'];
   $index['a_device_info'] = $row['tbl_device_info'];
   $index['a_time_stamp'] = $row['tbl_time_stamp'];

   array_push($resArr['data'], $index);
}

$resArr['status_code'] = "success";

mysqli_close($conn);
echo json_encode($resArr);
?>