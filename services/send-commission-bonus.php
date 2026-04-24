<?php
class InitiateCommission {
    private $conn = "";
    private $const_user_id = "";
    private $const_user_joined_under = "";
    private $const_total_amount = ""; 
    private $level1_bonus = ""; 
    private $level2_bonus = "";
    private $level3_bonus = "";
    private $const_transaction_type = "commision";
    private $response = false;

    function __construct($conn, $userId ,$joinedUnder ,$totalAmount){
      $this->conn = $conn;
      $this->const_user_id = $userId;
      $this->const_user_joined_under = $joinedUnder;
      $this->const_total_amount = $totalAmount;
    }
    
    private function getDateTime(){
        // getting current date & times
        date_default_timezone_set("Asia/Kolkata");
        return date("d-m-Y h:i a");
    }
    
    private function getNumberFormat($number = 0, $decimalPoint=2){
        $multiplier = pow(10, $decimalPoint);
          
        // Truncate without rounding
        $truncated = floor($number * $multiplier) / $multiplier;
        return number_format($truncated, $decimalPoint, '.', '');
    }
  
    private function getServiceDetails(){
        $returnVal = true;
    
        $sql = "SELECT * FROM tblservices WHERE tbl_service_value!='' ";
        $sql_query = mysqli_query($this->conn, $sql);
    
        if (mysqli_num_rows($sql_query) > 0) {
        
          while($resp_data = mysqli_fetch_array($sql_query)){
            if($resp_data['tbl_service_name']=="COMISSION_BONUS"){
                
              $commissionBonus = $resp_data["tbl_service_value"];
              $resArray = explode(",", $commissionBonus);
          
              if (count($resArray) == 3 || count($resArray) == 1) {
                if (count($resArray) == 3) {
                  $this->level1_bonus = $resArray[0] / 100;
                  $this->level2_bonus = $resArray[1] / 100;
                  $this->level3_bonus = $resArray[2] / 100;
                } else {
                  $this->level1_bonus = $commissionBonus;
                  $this->level2_bonus = $commissionBonus;
                  $this->level3_bonus = $commissionBonus;
                }
              }else{
                $returnVal = false;  
              }
            }
          }
          
        }else{
          $returnVal = false;  
        }
        
        return $returnVal;
    }
    
    private function distributeLevelBonus($user_id, $referal_id, $level){
        $level_bonus = 0;
        if($level==1){
            $level_bonus = $this->level1_bonus;
            $transaction_message = "Level 1";
        }else if($level==2){
            $level_bonus = $this->level2_bonus;
            $transaction_message = "Level 2";
        }else if($level==3){
            $level_bonus = $this->level3_bonus;
            $transaction_message = "Level 3";
        }
        
        if($level_bonus > 0){
          $date_time = $this->getDateTime();
          $bonus_amount = $this->getNumberFormat(($this->const_total_amount*($level_bonus/100)), 2);
          
          $sql = $this->conn->prepare("UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE tbl_uniq_id = ?");
          $sql->bind_param("ss",$bonus_amount,$user_id);
          $sql->execute();
          
          $insert_sql = $this->conn->prepare("INSERT INTO tblotherstransactions(tbl_user_id,tbl_received_from,tbl_transaction_type,tbl_transaction_amount,tbl_transaction_note,tbl_time_stamp) VALUES(?,?,?,?,?,?)");
          $insert_sql->bind_param("ssssss",
            $user_id,
            $referal_id,
            $this->const_transaction_type,
            $bonus_amount,
            $transaction_message,
            $date_time
          );
          $insert_sql->execute();
        }
    }
    
    private function fetchLevelData($user_id, $joined_under, $level) {
        $select_sql = "SELECT tbl_joined_under FROM tblusersdata WHERE tbl_uniq_id='{$joined_under}' ";
        $result = $this->conn->query($select_sql);
        
        $reponse = "";
        if ($result->num_rows > 0) {
            $resp_data = $result->fetch_assoc();
            $user_joined_under = $resp_data['tbl_joined_under'];
            
            // distribute level bonus
            $this->distributeLevelBonus($joined_under, $user_id, $level);
            
            $reponse = $user_joined_under;
        }
        
        return $reponse;
    }
    
    private function processAllLevels(){
        // level 1
        $level1_user = $this->fetchLevelData($this->const_user_id, $this->const_user_joined_under, 1);
        
        if($level1_user!=""){
            // level 2
            $level2_user = $this->fetchLevelData("", $level1_user, 2);
            
            if($level2_user!=""){
                // level 3
                $this->fetchLevelData("", $level2_user, 3);
            }
        }
    }
    
    public function process() {
        if($this->getServiceDetails()){
            $this->response = $this->processAllLevels();
        }
        
        return $this->response;
    }

    function __destruct(){
    }
}
?>