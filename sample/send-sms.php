<?php
include "../components/helper-functions.php";

class SmsManager {
    private $conn;
    private $helperFunctions;
    private $curr_date = "";
    private $curr_time = "";
    
    private $const_otp_length = 6;
    private $const_day_otp_limit = 5;
    private $const_otp_frequency = 120; //in milliseconds
    private $is_otp_allowed = false;
    private $const_sms_api = "";
    
    private $const_new_user_id = "";
    private $const_inp_mobile = "";
    
    private $resArr = [
        "status_code" => "failed"
    ];

    public function __construct($conn, HelperFunctions $helperFunctions, $curr_date, $curr_time) {
        $this->conn = $conn;
        $this->helperFunctions = $helperFunctions;
        $this->curr_date = $curr_date;
        $this->curr_time = $curr_time;
    }

    public function __destruct() {
        // Connection handled by router
    }
    
    private function returnRequest() {
        echo json_encode($this->resArr);
        exit();
    }
    
    private function getParams() {
        $json = file_get_contents('php://input');
        $data = json_decode($json);

        if (is_object($data) && property_exists($data, 'SMS_MOBILE')) {
            $this->const_inp_mobile = $data->SMS_MOBILE;
        } else {
            $this->resArr['status_code'] = "invalid_params";
            $this->returnRequest();
        }
    }
    
    function checkOTPTimeGap(){
      $return = false;
        
      $sql = $this->conn->prepare("SELECT * FROM tblrecentotp WHERE tbl_mobile_num=? ORDER BY id DESC LIMIT 1 ");
      $sql->bind_param("s", $this->const_inp_mobile);
      $sql->execute();
      $sql_query = $sql->get_result();
    
      if ($sql_query && mysqli_num_rows($sql_query) > 0) {
        $resp_data = mysqli_fetch_assoc($sql_query);
        $now_otp_datetime = $this->curr_date.' '.$this->curr_time;
        $last_otp_datetime = $resp_data['tbl_otp_date'].' '.$resp_data['tbl_otp_time'];
      
        $seconds_gap = $this->helperFunctions->getSecondsBetDates($last_otp_datetime,$now_otp_datetime);
        if($seconds_gap <= $this->const_otp_frequency){
          $return = true;
        }
      }
        
      return $return;
    }
    
    private function getServiceDetails(){
        $returnVal = true;
    
        $sql = "SELECT * FROM tblservices WHERE tbl_service_value!='' ";
        $sql_query = mysqli_query($this->conn, $sql);
    
        if (mysqli_num_rows($sql_query) > 0) {
        
        while($resp_data = mysqli_fetch_array($sql_query)){
            if($resp_data['tbl_service_name']=="SMS_TOKEN"){
              $this->const_sms_api = $resp_data['tbl_service_value'];
            }else if($resp_data['tbl_service_name']=="OTP_ALLOWED"){
              $this->is_otp_allowed = $this->helperFunctions->stringToBoolean($resp_data['tbl_service_value']);
            }
        }
        
        }else{
          $returnVal = false;  
        }
        
        return $returnVal;
    }
    
    private function checkOTPLimit(){
        $return = false;
        
        $sql = $this->conn->prepare("SELECT id FROM tblrecentotp WHERE tbl_mobile_num=? AND tbl_otp_date=? ");
        $sql->bind_param("ss", $this->const_inp_mobile, $this->curr_date);
        $sql->execute();
        $sql_query = $sql->get_result();
        
        if ($sql_query && mysqli_num_rows($sql_query) < $this->const_day_otp_limit) {
            $return = true;
        }
        
        return $return;
    }
    
    
    function sendNewOTP($otp){
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL =>
            "https://dvhosting.in/api-sms-v3.php?api_key=" .
            $this->const_sms_api .
            "&otp=" .
            $otp .
            "&number=" .
            $this->const_inp_mobile,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $jsonArr = json_decode($response, true);
        return $jsonArr["message"][0];
    }
    
    function decodeSMSResponse($smsResponse,$otp){
        $returnVal = false;
        
        if($smsResponse=="Message sent successfully"){
          $insert_sql = $this->conn->prepare("INSERT INTO tblrecentotp(tbl_mobile_num,tbl_otp,tbl_otp_date,tbl_otp_time) VALUES(?,?,?,?)");
          $insert_sql->bind_param("ssss", $this->const_inp_mobile,$otp,$this->curr_date,$this->curr_time);
          $insert_sql->execute();    
            
          $returnVal = true;
        }
        
        return $returnVal;
    }
    
    
    private function initiateFinalProcess(){
        global $GLOBAL_OTP;
        if(isset($GLOBAL_OTP)){
            // Testing bypass
            $this->resArr["status_code"] = "success";
            return;
        }
        $new_otp = $this->helperFunctions->generateRandNumber(6);
        
        // if($this->is_otp_allowed && $this->decodeSMSResponse($this->sendNewOTP($new_otp), $new_otp)){
        if($this->decodeSMSResponse($this->sendNewOTP($new_otp), $new_otp)){
            $this->resArr["status_code"] = "success";
        }else if(!$this->is_otp_allowed){
            $this->resArr["status_code"] = "fail";
        }
    }

    private function processRequest(){
        if($this->const_inp_mobile!=""){
            if($this->is_otp_allowed){
                if($this->checkOTPLimit()){
                      
                    if(!$this->checkOTPTimeGap()){
                      $this->initiateFinalProcess();
                    }else{
                      $this->resArr["status_code"] = "otp_limit_error"; 
                    }
                      
                }else{
                    $this->resArr["status_code"] = "otp_limit_error"; 
                }
                    
            }else{
                $this->initiateFinalProcess();
            }
        }else{
            $this->resArr["status_code"] = "invalid_params";
        }
        
        $this->returnRequest();
    }
    
    public function process() {
        $this->getParams();
        $this->getServiceDetails();
        $this->processRequest();
    }
}



// Initializing database connection
if ($conn->connect_error) {
    die("db_conn_error");
}


// initializing new required objects
$helperFunctions = new HelperFunctions();
$headerObj = new RequestHeaders();

// initializing new object & calling function
$smsManager = new SmsManager($conn, $helperFunctions, $curr_date, $curr_time);
$smsManager->process();
?>