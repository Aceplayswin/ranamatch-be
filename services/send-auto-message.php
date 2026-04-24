<?php
define("ACCESS_SECURITY","true");
include '../security/config.php';
include '../security/access_codes.php';

date_default_timezone_set('Asia/Kolkata');
$curr_time = date('H:i');

function notify($data,$messageToken){
  $url="https://fcm.googleapis.com/fcm/send";
  $fields=json_encode(array('to'=>'/topics/allusers','notification'=>$data));

  $ch = curl_init();

   curl_setopt($ch, CURLOPT_URL, $url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
   curl_setopt($ch, CURLOPT_POST, 1);
   curl_setopt($ch, CURLOPT_POSTFIELDS, ($fields));

   $headers = array();
   $headers[] = 'Authorization: key ='.$messageToken;
   $headers[] = 'Content-Type: application/json';
   curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

   $result = curl_exec($ch);
   if (curl_errno($ch)) {
     echo 'Error:' . curl_error($ch);
   }

   curl_close($ch);
}

function sendNotification($title,$message,$messageToken){
 $data=array(
  'title'=> $title,
  'body'=> $message,
  'icon'=>'noti_icon'
 );

 notify($data,$messageToken);
}

$search_sql = "SELECT * FROM automessages WHERE message_set_on='{$curr_time}' AND status='true'";
$search_result = mysqli_query($conn, $search_sql) or die('search failed');

if (mysqli_num_rows($search_result) > 0){
  while ($row = mysqli_fetch_assoc($search_result)){
    sendNotification($row['message_title'],$row['message_desc'],$messageToken);
  }
}

mysqli_close($conn);
?>