<?php
// ... (previous code remains unchanged)

if (isset($_POST['submit'])){
  if(!$IS_PRODUCTION_MODE){
    echo "Game is under Demo Mode. So, you can not add or modify.";
    return;
  }
  
  $auth_user_id = mysqli_real_escape_string($conn, $_POST["signup_mobile"] ?? '');
  $auth_user_password = mysqli_real_escape_string($conn, password_hash($_POST["signup_password"] ?? '', PASSWORD_BCRYPT));
  
  $user_access_list = "";
  
  $access_fields = [
    'access_match', 'access_users_data', 'access_recent_played', 'access_recharge',
    'access_withdraw', 'access_help', 'access_message',
    'access_gift', 'access_settings', 'access_pandl', 'access_admins'
  ];

  foreach ($access_fields as $field) {
    if (isset($_POST[$field]) && $_POST[$field] == 'on') {
      $user_access_list .= $field . ',';
    }
  }
  
  $user_access_list = rtrim($user_access_list, ',');

  // ... (rest of the code remains unchanged)

  if($auth_user_id != ""){
    $pre_sql = "SELECT * FROM tbladmins";
    $pre_result = mysqli_query($conn, $pre_sql) or die('error');
    
    if(mysqli_num_rows($pre_result) < $ADMIN_ACCOUNTS_LIMIT){
      $pre_sql = "SELECT * FROM tbladmins WHERE tbl_uniq_id='$unique_id' or tbl_user_id='$auth_user_id' ";
      $pre_result = mysqli_query($conn, $pre_sql) or die('error');
    
      if (mysqli_num_rows($pre_result) <= 0){
        $insert_sql = "INSERT INTO tbladmins(tbl_uniq_id, tbl_user_id, tbl_user_password, tbl_user_access_list, tbl_date_time, tbl_auth_secret) 
                       VALUES(?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        $tbl_auth_secret = generateRandomString(); // Generate a random string for tbl_auth_secret
        mysqli_stmt_bind_param($stmt, "ssssss", $unique_id, $auth_user_id, $auth_user_password, $user_access_list, $curr_date_time, $tbl_auth_secret);
        $insert_result = mysqli_stmt_execute($stmt);
        if($insert_result){
          echo "<script>alert('New account Created!');window.history.back();</script>";
        } else {
          echo "Error creating account: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
      } else {
        echo "Entered mobile or uniqid is already registered!";
      }
    } else {
      echo "Maximum number of admin accounts reached!";
    }
  }
}

// ... (rest of the code remains unchanged)
?>