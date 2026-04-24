<?php
$const_user_id = "";
$const_page_num = "";
$content = 10;

if (isset($_GET['USER_ID']) && isset($_GET['PAGE_NUM'])) {
  $const_user_id = mysqli_real_escape_string($conn, $_GET['USER_ID']);
  $const_page_num = mysqli_real_escape_string($conn, $_GET['PAGE_NUM']);
  $secret_key = $headerObj->getAuthorization();
}
$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$const_user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if (mysqli_num_rows($select_query) > 0) {


  $offset = ($const_page_num - 1) * $content;
  $select_sql = "SELECT * FROM tblusersrecharge WHERE tbl_user_id='{$const_user_id}' ORDER BY id DESC LIMIT {$offset},{$content} ";
  $select_query = mysqli_query($conn, $select_sql);

  while ($row = mysqli_fetch_assoc($select_query)) {
    $index['r_uniq_id'] = $row['tbl_uniq_id'];
    $index['r_amount'] = $row['tbl_recharge_amount'];
    $index['r_mode'] = $row['tbl_recharge_mode'];
    $index['r_details'] = $row['tbl_recharge_details'];
    $index['r_date'] = $row['tbl_time_stamp'];
    $index['r_time'] = $row['tbl_time_stamp']; // Keep both for safety, but with full data
    $index['r_status'] = $row['tbl_request_status'];

    array_push($resArr['data'], $index);
  }

  $numRows = mysqli_num_rows($select_query);

  if ($numRows > 0) {
    $resArr['status_code'] = "success";
  } else {
    $resArr['status_code'] = "no-records-found";
  }
} else {
  $resArr["status_code"] = "authorization_error";
}
mysqli_close($conn);
echo json_encode($resArr);
?>