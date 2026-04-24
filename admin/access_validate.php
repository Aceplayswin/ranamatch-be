<?php

class AccessValidate {
  function __construct(){
      $this->accessList = "";
  }
  
  function validate() {
    $returnVal = "true";
    
    if (!isset($_SESSION["admin_user_id"])) {
      $returnVal = "false";
    }

    if (!isset($_SESSION["admin_access_list"])) {
      $returnVal = "false";
    }else{
      $account_access = $_SESSION["admin_access_list"];
      $this->accessList = explode (",", $account_access);
    }
    
    return $returnVal;
      
  }
  
  function isAllowed($data){
      $returnVal = "false";
      
      if(in_array($data, $this->accessList)){
          $returnVal = "true";
      }
      
      return $returnVal;
  }
  
  function __destruct(){
  }
}