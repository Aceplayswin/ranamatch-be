<?php
header("Cache-Control: no cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../logout-account');
}

// Get today's date and yesterday's date
$today = date("Y-m-d");
$yesterday = date("Y-m-d", strtotime("-1 day"));

// Query to count bets played today
$today_query = "SELECT COUNT(*) AS today_count FROM tblmatchplayed WHERE DATE(tbl_match_time) = '$today'";
$today_result = mysqli_query($conn, $today_query);
$today_count = mysqli_fetch_assoc($today_result)['today_count'];

// Query to count bets played yesterday
$yesterday_query = "SELECT COUNT(*) AS yesterday_count FROM tblmatchplayed WHERE DATE(tbl_match_time) = '$yesterday'";
$yesterday_result = mysqli_query($conn, $yesterday_query);
$yesterday_count = mysqli_fetch_assoc($yesterday_result)['yesterday_count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Bet Summary</title>
    <link href='../style.css' rel='stylesheet'>
</head>
<body>
<div class="mh-100vh w-100">
    <div class="row-view sb-view">
        <?php include "../components/side-menu.php"; ?>
        <div class="w-100 h-100vh pd-10 br-all ovflw-y-scroll hide-native-scrollbar">
            <p>Dashboard > Bet Summary</p>
            <div class="w-100 row-view j-start mg-t-20">
                <h1 class="mg-l-15">Bet Summary</h1>
            </div>
            <div class="pd-10 mg-t-30 bx-shdw br-r-5">
                <h2>Number of Bets Played</h2>
                <p>Today: <strong><?php echo $today_count; ?></strong></p>
                <p>Yesterday: <strong><?php echo $yesterday_count; ?></strong></p>
            </div>
        </div>
    </div>
</div>
<script src="../script.js"></script>
</body>
</html>
