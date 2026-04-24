<?php
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

?>