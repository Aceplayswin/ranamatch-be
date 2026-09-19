<?php
include "../components/helper-functions.php";

class AccountManager {
    private $conn;
    private $helperFunctions;
    private $curr_date = "";
    private $curr_time = "";
    
    private $const_userid_length = 7;
    private $is_otp_allowed = false;
    private $is_signup_allowed = false;
    private $signup_bonus = 0;
    
    private function debug_log($msg) {
        file_put_contents(__DIR__ . "/register_debug.log", date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }
    
    private $const_inp_user_id = "";
    private $const_inp_username = "";
    private $const_inp_mobile = "";
    private $const_inp_password = "";
    private $const_inp_otp = "";
    private $const_inp_refercode = "";
    
    private $const_empty_val = "";
    private $const_zero_val = "0";
    private $const_true_val = "true";
    private $const_account_level = "2";
    
    private $const_default_invite_code = "";
    
    private $resArr = [
        "data" => [],
        "status_code" => "failed"
    ];

    public function __construct($conn, HelperFunctions $helperFunctions, $curr_date, $curr_time, $default_referal) {
        $this->conn = $conn;
        $this->helperFunctions = $helperFunctions;
        $this->curr_date = $curr_date;
        $this->curr_time = $curr_time;
        $this->const_inp_refercode = $default_referal;
        $this->const_default_invite_code = $default_referal;
    }

    public function __destruct() {
        // Connection handled by router
    }
    
    private function returnRequest() {
        $messages = [
            "success" => "Registration successful!",
            "invalid_refer_code" => "The referral code provided is invalid or inactive.",
            "already_registered" => "This mobile number is already registered. Please login instead.",
            "account_suspended" => "This account has been suspended.",
            "username_exists" => "This username is already taken. Please choose a different username.",
            "username_empty" => "Username cannot be empty.",
            "username_requires_alphabet" => "Username must contain at least one letter.",
            "invalid_otp" => "The OTP entered is incorrect or expired.",
            "invalid_mobile" => "Please enter a valid 10-digit mobile number.",
            "password_weak" => "Password must be at least 6 characters long.",
            "signup_not_allowed" => "Registration is currently disabled by administrator.",
            "invalid_params" => "Missing required registration parameters.",
            "db_error_register" => "Database error during registration. Please try again."
        ];
        
        $code = $this->resArr['status_code'] ?? 'failed';
        $this->resArr['message'] = $messages[$code] ?? ("Registration failed: " . $code);
        $this->resArr['status_message'] = $this->resArr['message'];
        
        echo json_encode($this->resArr);
        exit();
    }
    
    private function getParams() {
        $json = file_get_contents('php://input');
        $data = json_decode($json);

        if (is_object($data) && property_exists($data, 'SIGNUP_MOBILE') && property_exists($data, 'SIGNUP_PASSWORD') && property_exists($data, 'SIGNUP_OTP')) {
            $this->const_inp_mobile = $data->SIGNUP_MOBILE;
            $this->const_inp_password = $data->SIGNUP_PASSWORD;
            $this->const_inp_otp = $data->SIGNUP_OTP;
            $this->const_inp_username = $data->SIGNUP_USERNAME ?? "";
            
            if(property_exists($data, 'SIGNUP_INVITE_CODE')){
                if($data->SIGNUP_INVITE_CODE!=""){
                   $this->const_inp_refercode = $data->SIGNUP_INVITE_CODE; 
                }
            }
        } else {
            $this->resArr['status_code'] = "invalid_params";
            $this->returnRequest();
        }
    }
    
    private function getServiceDetails(){
        $returnVal = true;
    
        $sql = "SELECT * FROM tblservices WHERE tbl_service_value!='' ";
        $sql_query = mysqli_query($this->conn, $sql);
    
        if (mysqli_num_rows($sql_query) > 0) {
        
        while($resp_data = mysqli_fetch_array($sql_query)){
            if($resp_data['tbl_service_name']=="SIGNUP_ALLOWED"){
              $this->is_signup_allowed = $this->helperFunctions->stringToBoolean($resp_data['tbl_service_value']);
            }else if($resp_data['tbl_service_name']=="SIGNUP_BONUS"){
              $this->signup_bonus = $resp_data['tbl_service_value'];
            }else if($resp_data['tbl_service_name']=="OTP_ALLOWED"){
              $this->is_otp_allowed = $this->helperFunctions->stringToBoolean($resp_data['tbl_service_value']);
            }
        }
        
        }else{
          $returnVal = false;  
        }
        
        return $returnVal;
    }
    
    private function filterMobileNumber(){
        $reponse = true;
        
        $bannedMobNumList = "1234567890,1234567899,0987654321";
        $bannedMobNumber = explode(",", $bannedMobNumList);
        if (strlen($this->const_inp_mobile) != 10 || in_array($this->const_inp_mobile, $bannedMobNumber)) {
          $reponse = false;
        }
        
        return $reponse;
    }
    
    function isIncrementingArray($array) {
        $response = true;

        for ($i = 1; $i < count($array); $i++) {
            // Check if the difference between the current and previous element is greater than 5
            if (($array[$i] - $array[$i - 1]) > 5) {
              $response = false;
              break;
            }
        }

        return $response;
    }
    
    function checkForDuplicateId($unique_id){
        $returnVal = false;
        
        $sql = $this->conn->prepare("SELECT tbl_mobile_num FROM tblusersdata WHERE tbl_mobile_num=? OR tbl_uniq_id=? ");
        $sql->bind_param("ss", $this->const_inp_mobile, $unique_id);
        $sql->execute();
        $sql_query = $sql->get_result();
        if ($sql_query === false) {
            return false;
        }
    
        // Ensure username column exists (Safe Version)
        $col_check = mysqli_query($this->conn, "SHOW COLUMNS FROM tblusersdata LIKE 'tbl_user_name'");
        if (mysqli_num_rows($col_check) == 0) {
            mysqli_query($this->conn, "ALTER TABLE tblusersdata ADD COLUMN tbl_user_name VARCHAR(255) AFTER tbl_uniq_id");
            mysqli_query($this->conn, "ALTER TABLE tblusersdata ADD UNIQUE (tbl_user_name)");
        }

        if (mysqli_num_rows($sql_query) <= 0) {
          // Check if username already exists if provided
          if($this->const_inp_username != ""){
            $check_username_sql = $this->conn->prepare("SELECT tbl_user_name FROM tblusersdata WHERE tbl_user_name=?");
            $check_username_sql->bind_param("s", $this->const_inp_username);
            $check_username_sql->execute();
            $sql_query = $check_username_sql->get_result();
            if ($sql_query === false) {
                return false;
            }
            if(mysqli_num_rows($sql_query) > 0){
              return false;
            }
          }

          $sql_records = "SELECT tbl_mobile_num FROM tblusersdata ORDER BY id DESC LIMIT 5";
          $sql_records_query = mysqli_query($this->conn, $sql_records);
          
          $last_records_array=array();
          if (mysqli_num_rows($sql_records_query) >= 5) {
              
            while($row = mysqli_fetch_array($sql_records_query)){
              array_push($last_records_array,$row['tbl_mobile_num']);
            }
        
            if($this->isIncrementingArray($last_records_array)){
              $returnVal = false;
            }else{
              $returnVal = true;
            }
          }else{
            $returnVal = true;  
          }
        }
        
        return $returnVal;
    }
    
    private function checkForNewID(){
        $return = "";
          
        for ($x = 0; $x <= 3; $x++) {
            $temp_unique_id = $this->helperFunctions->generateRandNumber($this->const_userid_length);
            
            if($this->checkForDuplicateId($temp_unique_id)==true){
              $return = $temp_unique_id;
              break;
            }
        }
          
        if($return==""){
            $return = "failed"; 
        }
        
        return $return;
    }
    
    private function checkAccountInfo(){
        $this->debug_log("Checking account info for: " . $this->const_inp_mobile . " / " . $this->const_inp_username);
        $return = true;
        
        $sql = $this->conn->prepare("SELECT tbl_account_status FROM tblusersdata WHERE tbl_mobile_num=? ");
        $sql->bind_param("s", $this->const_inp_mobile);
        $sql->execute();
        $sql_query = $sql->get_result();
        if ($sql_query === false) {
            return true; 
        }
        
        if (mysqli_num_rows($sql_query) > 0) {
            $resp_data = mysqli_fetch_assoc($sql_query);
            $account_status = $resp_data['tbl_account_status'];
            
            if($account_status=="true"){
                $return = false;
                $this->resArr["status_code"] = "already_registered";
            }else if($account_status=="ban"){
                $return = false;
                $this->resArr["status_code"] = "account_suspended";
            }
            
        }else{
          // Check if username already exists separately
          if($this->const_inp_username != ""){
             $check_user_sql = $this->conn->prepare("SELECT id FROM tblusersdata WHERE tbl_user_name=? LIMIT 1 ");
             $check_user_sql->bind_param("s", $this->const_inp_username);
             $check_user_sql->execute();
             $check_user_query = $check_user_sql->get_result();
             if ($check_user_query === false) {
                 return true;
             }
             if(mysqli_num_rows($check_user_query) > 0){
                 $return = false;
                 $this->resArr["status_code"] = "username_exists";
                 return $return;
             }
          }

          $response = $this->checkForNewID();
          
          if($response!="failed"){
            $this->const_inp_user_id = $response;
          }  
        }
        
        return $return;
    }
    
    private function checkInviteCode($invite_code){
        $return = false;
        
        if($invite_code==""){
           return false;
        }
        
        if($invite_code==$this->const_default_invite_code){
            return true;
        }
        
        // 1. Check existing player referral code
        $sql = $this->conn->prepare("SELECT tbl_account_status FROM tblusersdata WHERE tbl_uniq_id=? ");
        $sql->bind_param("s", $invite_code);
        $sql->execute();
        $sql_query = $sql->get_result();
        
        if (mysqli_num_rows($sql_query) > 0) {
            $resp_data = mysqli_fetch_assoc($sql_query);
            $account_status = $resp_data['tbl_account_status'];
            
            if($account_status=="true"){
                $return = true;
            }
        }

        // 2. Check affiliate campaign link code (e.g. LNK-XXXXXX)
        if (!$return) {
            $linkSql = $this->conn->prepare("SELECT id FROM affiliate_links WHERE code=? LIMIT 1");
            $linkSql->bind_param("s", $invite_code);
            $linkSql->execute();
            if (mysqli_num_rows($linkSql->get_result()) > 0) {
                $return = true;
            }
        }

        // 3. Check affiliate partner code (e.g. AFF-XXXXXX)
        if (!$return) {
            $affSql = $this->conn->prepare("SELECT id FROM affiliates WHERE affiliate_code=? AND status IN ('approved', 'active') LIMIT 1");
            $affSql->bind_param("s", $invite_code);
            $affSql->execute();
            if (mysqli_num_rows($affSql->get_result()) > 0) {
                $return = true;
            }
        }

        // 4. Check agent referral code (agent_code, username, or agent ID)
        if (!$return) {
            $agSql = $this->conn->prepare("SELECT id FROM agents WHERE (agent_code=? OR username=? OR CAST(id AS CHAR)=?) AND status IN ('active', 'approved') LIMIT 1");
            $agSql->bind_param("sss", $invite_code, $invite_code, $invite_code);
            $agSql->execute();
            if (mysqli_num_rows($agSql->get_result()) > 0) {
                $return = true;
            }
        }
        
        return $return;
    }
    
    private function checkOTP(){
        $this->debug_log("Checking OTP: " . $this->const_inp_otp . " for " . $this->const_inp_mobile);
        
        // =========================================================================
        // TEMPORARY ADMIN/MASTER OTP BYPASS - FOR TESTING (REMOVE BEFORE PRODUCTION)
        // Master OTPs: 123456, 999999, 888888, 000000, 111111, 777777
        // =========================================================================
        $admin_otps = ['123456', '999999', '888888', '000000', '111111', '777777'];
        if (in_array(trim($this->const_inp_otp), $admin_otps)) {
            $this->debug_log("Admin master OTP accepted: " . $this->const_inp_otp);
            return true;
        }

        $returnVal = false;
        
        if($this->is_otp_allowed){
            $sql = $this->conn->prepare("SELECT * FROM tblrecentotp WHERE tbl_mobile_num=? AND tbl_otp_date=? AND tbl_otp=? ORDER BY id DESC LIMIT 1 ");
            $sql->bind_param("sss", $this->const_inp_mobile, $this->curr_date, $this->const_inp_otp);
            $sql->execute();
            $sql_query = $sql->get_result();
            if ($sql_query === false) {
                $this->debug_log("OTP DB check failed");
                return false;
            }
            
            if (mysqli_num_rows($sql_query) > 0) {
                $this->debug_log("OTP found in DB");
                $returnVal = true;
            } else {
                $this->debug_log("OTP NOT found in DB");
            }
        }else{
           $this->debug_log("OTP service disabled");
           $this->resArr['status_code'] = "otp_service_disabled";
           $returnVal = false;  
        }
        
        return $returnVal;
    }
    
    private function addNewTransaction($transaction_amount, $transaction_note = ''){
        if($transaction_amount <= 0){
            return;
        }
        
        $receive_from = "app";
        $transaction_type = "signupbonus";
        $transaction_date_time = $this->curr_date." ".$this->curr_time;
        
        $insert_sql = $this->conn->prepare("INSERT INTO tblotherstransactions(tbl_user_id,tbl_received_from,tbl_transaction_type,tbl_transaction_amount,tbl_transaction_note,tbl_time_stamp) VALUES(?,?,?,?,?,?)");
        $insert_sql->bind_param("ssssss", $this->const_inp_user_id, $receive_from, $transaction_type,$transaction_amount,$transaction_note,$transaction_date_time);
        $insert_sql->execute();
    }
    
    
    private function createNewAccount(){
        $this->debug_log("Creating new account session");
        $account_avatar = $this->helperFunctions->generateRandInt(1,9);
        $account_name = "MEMBER".$this->helperFunctions->generateRandNumber(4);
        $curr_date_time = $this->curr_date." ".$this->curr_time;
        $account_auth_secret = $this->helperFunctions->generateRandString(30);
        $account_password = password_hash($this->const_inp_password,PASSWORD_BCRYPT);
        
        if($this->signup_bonus > 0){
            $const_account_balance = $this->signup_bonus;
        }else{
            $const_account_balance = $this->const_zero_val;
        }
        
        // Insert with username
        $insert_sql = $this->conn->prepare(
            "INSERT INTO tblusersdata(tbl_uniq_id,tbl_user_name,tbl_auth_secret,tbl_avatar_id,tbl_mobile_num,tbl_email_id,tbl_full_name,tbl_password,tbl_balance,tbl_requiredplay_balance,tbl_withdrawl_balance,tbl_commission_balance,tbl_freezed_balance,tbl_joined_under,tbl_last_active_date,tbl_last_active_time,tbl_account_level,tbl_account_status,tbl_user_joined) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $insert_sql->bind_param(
            "sssssssssssssssssss",
            $this->const_inp_user_id,
            $this->const_inp_username,
            $account_auth_secret,
            $account_avatar,
            $this->const_inp_mobile,
            $this->const_empty_val,
            $account_name,
            $account_password,
            $const_account_balance,
            $this->const_zero_val,
            $this->const_zero_val,
            $this->const_zero_val,
            $this->const_zero_val,
            $this->const_inp_refercode,
            $this->curr_date,
            $this->curr_time,
            $this->const_account_level,
            $this->const_true_val,
            $curr_date_time
        );
        $insert_sql->execute();

        if ($insert_sql->error == "") {
            $this->debug_log("Account entry inserted for: " . $this->const_inp_user_id);
            // this will add new transaction if signup balance is > 0
            $this->addNewTransaction($const_account_balance);

            // Attribute registration to affiliate if invited by affiliate code or link
            if (!empty($this->const_inp_refercode)) {
                $refCode = trim($this->const_inp_refercode);
                $affId = 0;
                $linkId = 0;

                $chkLink = $this->conn->prepare("SELECT id, affiliate_id FROM affiliate_links WHERE code = ? LIMIT 1");
                $chkLink->bind_param("s", $refCode);
                $chkLink->execute();
                $linkRow = mysqli_fetch_assoc($chkLink->get_result());
                if ($linkRow) {
                    $affId = (int)$linkRow['affiliate_id'];
                    $linkId = (int)$linkRow['id'];
                } else {
                    $chkAff = $this->conn->prepare("SELECT id FROM affiliates WHERE affiliate_code = ? LIMIT 1");
                    $chkAff->bind_param("s", $refCode);
                    $chkAff->execute();
                    $affRow = mysqli_fetch_assoc($chkAff->get_result());
                    if ($affRow) {
                        $affId = (int)$affRow['id'];
                    }
                }

                if ($affId > 0) {
                    $createdUserId = $insert_sql->insert_id ?: (int)$this->const_inp_user_id;
                    $insRef = $this->conn->prepare(
                        "INSERT INTO affiliate_referrals (affiliate_id, link_id, user_id, signup_at, status) 
                         VALUES (?, ?, ?, NOW(), 'signup')"
                    );
                    $insRef->bind_param("iii", $affId, $linkId, $createdUserId);
                    $insRef->execute();
                    $this->debug_log("Affiliate referral linked: affId={$affId}, linkId={$linkId}, userId={$createdUserId}");
                }
            }
            
            $index['account_id'] = $this->const_inp_user_id;
            $index['account_mobile_num'] = $this->const_inp_mobile;
            $index['account_balance'] = $const_account_balance;
            $index['account_w_balance'] = $this->const_zero_val;
            $index['account_refered_by'] = $this->const_inp_refercode;
            $index['auth_secret_key'] = $account_auth_secret;
            array_push($this->resArr['data'], $index);
        
            $this->resArr['status_code'] = "success";
        } else {
            $this->debug_log("Account creation DB failure: " . $insert_sql->error);
            error_log("Insert error: " . $insert_sql->error);
            $this->resArr['status_code'] = "db_error_register";
        }
    }
    
    private function validateDetails(){
        if(!$this->filterMobileNumber()){
           $this->resArr['status_code'] = "invalid_mobile"; 
        }else if($this->const_inp_username == ""){
           $this->resArr['status_code'] = "username_empty";
        }else if(!preg_match('/[a-zA-Z]/', $this->const_inp_username)){
           $this->resArr['status_code'] = "username_requires_alphabet"; 
        }else if(strlen($this->const_inp_password) < 6){
           $this->resArr['status_code'] = "password_weak"; 
        }else if(!$this->is_signup_allowed){
           $this->resArr['status_code'] = "signup_not_allowed"; 
        }else{
            if($this->checkAccountInfo()){
              if($this->const_inp_mobile!="" && $this->const_inp_refercode==""){
                  
                $this->createNewAccount();
                
              }else if($this->const_inp_mobile!="" && $this->const_inp_refercode!=""){
                  
                  if($this->checkInviteCode($this->const_inp_refercode)){
                    $this->createNewAccount();  
                  }else{
                    $this->resArr['status_code'] = "invalid_refer_code";  
                  }
                  
              }else{
                  $this->resArr['status_code'] = "invalid_params";
              }
            }
        }
    }
    
    private function processRequest(){
        if($this->const_inp_mobile!="" && $this->const_inp_password!="" && $this->const_inp_otp!=""){
            if($this->checkOTP()){
               $this->validateDetails(); 
            }else{
               $this->resArr['status_code'] = "invalid_otp";
            }
            
        }else{
            $this->resArr['status_code'] = "invalid_params";
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

// initializing new object & calling function
$accountManager = new AccountManager($conn, $helperFunctions, $curr_date, $curr_time, $DEFAULT_ACCOUNT_ID);
$accountManager->process();
?>