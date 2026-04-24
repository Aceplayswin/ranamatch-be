<?php
define("ACCESS_SECURITY","true");
include '../security/config.php';

// Filter the excel data 
function filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str); 
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
} 
 
// Excel file name for download 
$fileName = "vipclub-users.xls"; 
 
// Column names 
$fields = array('Email id', 'Phone Number'); 
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 
 
// Fetch records from database 
$select_sql = "SELECT * FROM usersdata ORDER BY id ASC"; 
$select_query = mysqli_query($conn,$select_sql);

if(mysqli_num_rows($select_query) > 0){
    // Output each row of the data 
    while($row = mysqli_fetch_assoc($select_query)){
        if($row['user_mobile_num'] != "6033229651" && $row['user_mobile_num'] != "6033229652" && $row['user_mobile_num'] != "6033229653"){
            $lineData = array($row['user_email_id'], $row['user_mobile_num']); 
            array_walk($lineData, 'filterData'); 
            $excelData .= implode("\t", array_values($lineData)) . "\n";    
        }
    } 
}else{ 
    $excelData .= 'No records found...'. "\n"; 
} 
 
// Headers for download 
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
 
// Render excel data 
echo $excelData; 
 
exit;