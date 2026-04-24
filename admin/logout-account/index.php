<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
   
session_start();
session_destroy();

header('location:../index.php');
exit();
?>