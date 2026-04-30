<?php
define("ACCESS_SECURITY","true");
include 'config.php';
include 'constants.php';
include 'license.php';

date_default_timezone_set("Asia/Kolkata");
$curr_date = date("d-m-Y");
$curr_time = date("h:i:s a");

// ======================================>>>
$num_tables = 0;
if ($is_db_connected=="true") {
    
$search_sql = "SHOW TABLES";
$search_res = mysqli_query($conn,$search_sql);
$num_tables = mysqli_num_rows($search_res);

if($num_tables > 0){
  echo "Database tables already setup!";
  return;
}

}else{
  echo "Connection not setup!";
  return;
}

// sql tblusersdata table
$sql = "CREATE TABLE IF NOT EXISTS tblusersdata (
id INT(11) AUTO_INCREMENT PRIMARY KEY,
tbl_uniq_id VARCHAR(100) NOT NULL,
tbl_auth_secret VARCHAR(150) NOT NULL,
tbl_avatar_id VARCHAR(10) NOT NULL,
tbl_mobile_num VARCHAR(30) NOT NULL,
tbl_email_id VARCHAR(50) NOT NULL,
tbl_full_name VARCHAR(50) NOT NULL,
tbl_password VARCHAR(250) NOT NULL,
tbl_balance DECIMAL(20,2) NOT NULL,
tbl_requiredplay_balance DECIMAL(20,2) NOT NULL,
tbl_withdrawl_balance DECIMAL(20,2) NOT NULL,
tbl_commission_balance DECIMAL(20,2) NOT NULL,
tbl_freezed_balance DECIMAL(20,2) NOT NULL,
tbl_joined_under VARCHAR(50) NOT NULL,
tbl_last_active_date VARCHAR(50) NOT NULL,
tbl_last_active_time VARCHAR(50) NOT NULL,
tbl_account_level VARCHAR(15) NOT NULL,
tbl_account_status VARCHAR(15) NOT NULL,
tbl_user_joined VARCHAR(50) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
  echo "Table tblusersdata created".'<br>';
} else {
  echo "Error creating table: " . $conn->error;
}


// insert new data
$user_unique_id = "1111111";
$user_mobile_num = "1234567890";
$user_avatar_id = "1";

$user_password = "12345678";
$user_password = mysqli_real_escape_string($conn,password_hash($user_password,PASSWORD_BCRYPT));
 
$unknown_data = "unknown";
$empty_data = "";
$zero_data = "0";
$account_level = "1";
$false_value = "false";
$true_value = "true";
$curr_date_time = $curr_date . " " . $curr_time;
 
$insert_user_sql = $conn->prepare("INSERT INTO tblusersdata(tbl_uniq_id,tbl_auth_secret,tbl_avatar_id,tbl_mobile_num,tbl_email_id,tbl_full_name,tbl_password,tbl_balance,tbl_requiredplay_balance,tbl_withdrawl_balance,tbl_commission_balance,tbl_freezed_balance,tbl_joined_under,tbl_last_active_date,tbl_last_active_time,tbl_account_level,tbl_account_status,tbl_user_joined) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$insert_user_sql->bind_param("ssssssssssssssssss", $user_unique_id,$empty_data,$user_avatar_id,$user_mobile_num,$unknown_data, $unknown_data, $user_password, $zero_data, $zero_data, $zero_data, $zero_data, $zero_data,$empty_data, $curr_date, $curr_time, $account_level, $true_value, $curr_date_time);
$insert_user_sql->execute();

if ($insert_user_sql->error == "") {
  echo "(tblusersdata) Data inserted successfully";
} else {
  echo "Error: Insert (tblusersdata) Data" . $conn->error;
}


// ======================================>>>

// sql tblusersrecharge table
$sql = "CREATE TABLE IF NOT EXISTS tblusersrecharge (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(200) NOT NULL,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_recharge_amount DECIMAL(20,2) NOT NULL,
    tbl_recharge_mode VARCHAR(20) NOT NULL,
    tbl_recharge_details VARCHAR(200) NOT NULL,
    tbl_request_status VARCHAR(15) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblusersrecharge created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

// ======================================>>>

// sql tbluserswithdraw table
$sql = "CREATE TABLE IF NOT EXISTS tbluserswithdraw (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(200) NOT NULL,
    tbl_user_id VARCHAR(100) NOT NULL,
    tbl_withdraw_request VARCHAR(50) NOT NULL,
    tbl_withdraw_amount DECIMAL(20,2) NOT NULL,
    tbl_withdraw_details LONGTEXT NOT NULL,
    tbl_request_status VARCHAR(15) NOT NULL,
    tbl_extra_details VARCHAR(200) NOT NULL,
    tbl_remark VARCHAR(200) NOT NULL DEFAULT 'no remark',
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tbluserswithdraw created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

// ======================================>>>

// sql tblmatchplayed table
$sql = "CREATE TABLE IF NOT EXISTS tblmatchplayed (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(100) NOT NULL,
    tbl_user_id VARCHAR(100) NOT NULL,
    tbl_period_id VARCHAR(50) NOT NULL,
    tbl_invested_on VARCHAR(50) NOT NULL,
    tbl_match_cost DECIMAL(20,2) NOT NULL,
    tbl_lot_size VARCHAR(30) NOT NULL,
    tbl_match_invested DECIMAL(20,2) NOT NULL,
    tbl_match_fee DECIMAL(10,2) NOT NULL,
    tbl_match_profit DECIMAL(20,2) NOT NULL,
    tbl_match_result VARCHAR(30) NOT NULL,
    tbl_last_acbalance DECIMAL(20,2) NOT NULL,
    tbl_match_status VARCHAR(15) NOT NULL,
    tbl_project_name VARCHAR(30) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblmatchplayed created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}
// ======================================>>>

// sql tblrecentotp table
$sql = "CREATE TABLE IF NOT EXISTS tblrecentotp (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_mobile_num VARCHAR(30) NOT NULL,
    tbl_otp VARCHAR(20) NOT NULL,
    tbl_otp_date VARCHAR(30) NOT NULL,
    tbl_otp_time VARCHAR(30) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblrecentotp created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

// ======================================>>>

// sql tblallbankcards table
$sql = "CREATE TABLE IF NOT EXISTS tblallbankcards (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(100) NOT NULL,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_beneficiary_name VARCHAR(50) NOT NULL,
    tbl_bank_name VARCHAR(30) NOT NULL,
    tbl_bank_account VARCHAR(30) NOT NULL,
    tbl_bank_ifsc_code VARCHAR(30) NOT NULL,
    tbl_bank_card_primary VARCHAR(15) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblallbankcards created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// ======================================>>>

// sql tblautopayments table
$sql = "CREATE TABLE IF NOT EXISTS tblautopayments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(50) NOT NULL,
    tbl_payment_amount DECIMAL(20,2) NOT NULL,
    tbl_user_details VARCHAR(150) NOT NULL,
    tbl_payment_refnum VARCHAR(30) NOT NULL,
    tbl_payment_details VARCHAR(150) NOT NULL,
    tbl_payment_mode VARCHAR(20) NOT NULL,
    tbl_payment_status VARCHAR(15) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblautopayments created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// ======================================>>>

// sql tblmerchantrecords table
$sql = "CREATE TABLE IF NOT EXISTS tblmerchantrecords (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_records LONGTEXT NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblmerchantrecords created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// ======================================>>>

// sql tblotherstransactions table
$sql = "CREATE TABLE IF NOT EXISTS tblotherstransactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_received_from VARCHAR(50) NOT NULL,
    tbl_transaction_type VARCHAR(30) NOT NULL,
    tbl_transaction_amount DECIMAL(20,2) NOT NULL,
    tbl_transaction_note VARCHAR(150) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblotherstransactions created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// ======================================>>>

// sql tblusersactivity table
$sql = "CREATE TABLE IF NOT EXISTS tblusersactivity (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(100) NOT NULL,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_device_ip VARCHAR(100) NOT NULL,
    tbl_device_info VARCHAR(100) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblusersactivity created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// ======================================>>>

// sql tblsliders table
$sql = "CREATE TABLE IF NOT EXISTS tblsliders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_slider_img LONGTEXT NOT NULL,
    tbl_slider_action LONGTEXT NOT NULL,
    tbl_slider_status VARCHAR(15) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblsliders created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}
// ======================================>>>

// sql tblservices table
$sql = "CREATE TABLE IF NOT EXISTS tblservices (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_service_name VARCHAR(100) NOT NULL,
    tbl_service_value LONGTEXT NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblservices created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

$allServicesSql = "INSERT INTO tblservices (tbl_service_name, tbl_service_value)
VALUES 
    (
        'APP_STATUS',
        'true'
    ),
    (
        'GAME_STATUS',
        'true'
    ),
    (
        'COMISSION_BONUS',
        '1'
    ),
    (
        'WITHDRAW_TAX',
        '0.05'
    ),
    (
        'MIN_WITHDRAW',
        '100'
    ),
    (
        'MIN_RECHARGE',
        '100'
    ),
    (
        'RECHARGE_OPTIONS',
        '100,200,500,1000,5000,7000'
    ),
    (
        'DEPOSIT_BONUS', 
        'true'
    ),
    (
        'DEPOSIT_BONUS_OPTIONS', 
        '100/10,200/20,500/50,1000/100,5000/500'
    ),
    (
        'TELEGRAM_URL',
        ''
    ),
    (
        'SMS_TOKEN',
        ''
    ),
    (
        'OTP_ALLOWED',
        'true'
    ),
    (
        'SALLARY_PERCENT',
        '1'
    ),
    (
        'SIGNUP_ALLOWED',
        'true'
    ),
    (
        'SIGNUP_BONUS',
        '10'
    ),
    (
        'IMP_MESSAGE',
        ''
    ),
    (
        'IMP_ALERT',
        ''
    );";

if ($conn->query($allServicesSql) === TRUE) {
  echo "(tblservices) Data inserted successfully";
} else {
  echo "Error: " . $allServicesSql . "<br>" . $conn->error;
}


// ======================================>>>

// sql adminauth table
$sql = "CREATE TABLE IF NOT EXISTS tbladmins (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_uniq_id VARCHAR(100) NOT NULL,
    tbl_auth_secret VARCHAR(100) NOT NULL,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_user_password VARCHAR(250) NOT NULL,
    tbl_user_access_list VARCHAR(250) NOT NULL,
    tbl_date_time VARCHAR(30) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tbladmins created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

$admin_uniq_id = "g92TuN2AarC8k0g3jNM7gDoY5gooeEF";
$admin_auth_secret = "3j0gNM791DoY5gooearC0EFg92TuN2A";
$admin_user_id = "9876543210";
$admin_password = "9876543210";
$admin_password = mysqli_real_escape_string($conn,password_hash($admin_password,PASSWORD_BCRYPT));
$admin_account_list = "access_match_control,access_users_data,access_recent_played,access_recharge,access_withdraw,access_template,access_message,access_gift,access_help,access_settings,access_pandl,access_admins";
 
$insert_sql = $conn->prepare("INSERT INTO tbladmins(tbl_uniq_id,tbl_auth_secret,tbl_user_id,tbl_user_password,tbl_user_access_list,tbl_date_time) VALUES(?,?,?,?,?,?)");
$insert_sql->bind_param("ssssss", $admin_uniq_id, $admin_auth_secret, $admin_user_id,$admin_password,$admin_account_list, $curr_date_time);
$insert_sql->execute();

if ($insert_sql->error == "") {
  echo "(tbladmins) Data inserted successfully".'<br>';
} else {
  echo "Error: creating table tbladmins" . $conn->error;
}


// sql tblgiftcards table
$sql = "CREATE TABLE IF NOT EXISTS tblgiftcards (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_giftcard VARCHAR(50) NOT NULL,
    tbl_giftcard_bonus DECIMAL(10,2) NOT NULL,
    tbl_giftcard_limit VARCHAR(50) NOT NULL,
    tbl_giftcard_targeted_id VARCHAR(50) NOT NULL,
    tbl_giftcard_balance_limit VARCHAR(50) NOT NULL,
    tbl_giftcard_status VARCHAR(15) NOT NULL,
    gift_date_time VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblgiftcards created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}


// sql tblallnotices table
$sql = "CREATE TABLE IF NOT EXISTS tblallnotices (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_user_id VARCHAR(50) NOT NULL,
    tbl_notice_title VARCHAR(50) NOT NULL,
    tbl_notice_note VARCHAR(500) NOT NULL,
    tbl_notice_status VARCHAR(15) NOT NULL,
    tbl_time_stamp VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblallnotices created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

// sql tblavailablerewards table
$sql = "CREATE TABLE IF NOT EXISTS tblavailablerewards (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tbl_reward_id VARCHAR(50) NOT NULL,
    tbl_reward_title VARCHAR(50) NOT NULL,
    tbl_reward_bonus DECIMAL(20,2) NOT NULL,
    tbl_reward_status VARCHAR(15) NOT NULL,
    tbl_reward_created VARCHAR(50) NOT NULL
    )";
    
if ($conn->query($sql) === TRUE) {
    echo "Table tblavailablerewards created".'<br>';
} else {
    echo "Error creating table: " . $conn->error;
}

echo "<strong>Congratulations :) Database Tables Created!</strong>";

$conn->close();
?>