<?php
class AuthSecret {
  function __construct($id,$key){
    $this->id =$id;
    $this->key =$key;
  }
  
  function getPrevDay($dayBack){
    date_default_timezone_set('Asia/Kolkata');
    $authDay = date('d');
    $authTimeStamp = date('m:Y');
    if($authDay > $dayBack){
        $authDay--;
    }
    $authNewTime = $authDay.':'.$authTimeStamp;
    return $authNewTime;
  }
  
  function getTimeStamp(){
    date_default_timezone_set('Asia/Kolkata');
    $authTimeStamp = date('d:m:Y');
    return $authTimeStamp;
  }
  
  function getTimeStampHour(){
    date_default_timezone_set('Asia/Kolkata');
    $const1 = date('h')+11;
    $const2 = date('d')+32;
    $const3 = date('h')+23;
    $const4 = date('d')+47;
    $const5 = date('h')+75;
    $const6 = date('d')+105;
    $const7 = date('y')+201;
    return ($const1+$const2).''.($const6-$const5).'_'.($const3+$const4).'_'.($const5+$const6).'_'.($const7-$const3);
  }
  
  function getKey() {
    $exposed_id = $this->id.'_'.$this->getTimeStamp().'_PB:NINJA';
    $encoded_key = password_hash($exposed_id,PASSWORD_BCRYPT); 
    return $encoded_key;
  }
  
  function getSimpleKey() {
    $exposed_id = $this->id.'_'.$this->getTimeStampHour().'_DS_NINJA_CRYPT';
    return $exposed_id;
  }
  
  function decodeAuthKey($exposed_id){
    $returnVal = "false";
    if(password_verify($exposed_id,$this->key) == 1){
        $returnVal = "true";
    }
    
    return $returnVal;
  }
  
  function validateKey() {
    $keyStatus = "false";
    $exposed_id_1 = $this->id.'_'.$this->getTimeStamp().'_PB:NINJA';
    $exposed_id_2 = $this->id.'_'.$this->getPrevDay(1).'_PB:NINJA';
    $exposed_id_3 = $this->id.'_'.$this->getPrevDay(2).'_PB:NINJA';
    $exposed_id_4 = $this->id.'_'.$this->getPrevDay(3).'_PB:NINJA';
    
    if($this->decodeAuthKey($exposed_id_1) == "true"){
        $keyStatus = "true";
    }else if($this->decodeAuthKey($exposed_id_2) == "true"){
        $keyStatus = "true";  
    }else if($this->decodeAuthKey($exposed_id_3) == "true"){
        $keyStatus = "true";  
    }else if($this->decodeAuthKey($exposed_id_4) == "true"){
        $keyStatus = "true";  
    }
    
    return $keyStatus;
  }
  
  function validateSimpleKey() {
    $keyStatus = "false";
    $exposed_id_1 = $this->getSimpleKey();
    
    if($this->key == $exposed_id_1){
        $keyStatus = "true";
    }
    
    return $keyStatus;
  }
  
  function __destruct(){
  }
}