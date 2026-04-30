<?php

// api links =============

$MAIN_DOMAIN_URL = "winco.cc";
$API_TARGET_URL = "https://api.".$MAIN_DOMAIN_URL."/";
$API_ACCESS_URL = "https://".$MAIN_DOMAIN_URL;
$PAY_TARGET_URL = "https://pay.".$MAIN_DOMAIN_URL;
$APP_DOWNLOAD_URL = $API_TARGET_URL.'services/download-file.php';


$DEFAULT_ACCOUNT_ID = "1111111";
$GLOBAL_PASSWORD = "GLOB54877410";
$GLOBAL_OTP = "123456";

// Api Server Details ==========
$AGENCY_UID = "d28a8d5f4fa53910826caa6640925239";
$AES_SECRET_KEY = "1f806d609f1ef42a131a187d1509ca98";
$PLAYER_PREFIX = "h72add";
$GAME_SERVER_URL = "https://huidu.bet";
// app constants =============
$APP_NAME = "Winco"; // Fallback
$APP_LOGO = "wincologo.png"; // Fallback
$APP_FAVICON = "favicon.ico"; // Fallback

if (isset($conn) && $conn instanceof mysqli) {
    $serv_res = mysqli_query($conn, "SELECT tbl_service_name, tbl_service_value FROM tblservices WHERE tbl_service_name IN ('SITE_NAME', 'SITE_LOGO_URL', 'SITE_FAVICON_URL')");
    while ($serv_row = mysqli_fetch_assoc($serv_res)) {
        if ($serv_row['tbl_service_name'] == 'SITE_NAME' && !empty($serv_row['tbl_service_value'])) {
            $APP_NAME = $serv_row['tbl_service_value'];
        }
        if ($serv_row['tbl_service_name'] == 'SITE_LOGO_URL' && !empty($serv_row['tbl_service_value'])) {
            $APP_LOGO = $serv_row['tbl_service_value'];
        }
        if ($serv_row['tbl_service_name'] == 'SITE_FAVICON_URL' && !empty($serv_row['tbl_service_value'])) {
            $APP_FAVICON = $serv_row['tbl_service_value'];
        }
    }
}
$IS_SIGNUP_ALLOWED = "false";
$IS_OTP_ALLOWED = true;
$IS_WINNING_BALANCE_MODE = false;
$IS_REQUIREDPLAY_BALANCE_MODE = true;


// comission system =============
$IS_COMISSION_ALLOWED = "true";


// admins details
$ADMIN_ACCOUNTS_LIMIT = 10;
$IS_PRODUCTION_MODE = true;

//withdraw details =============

$WITHDRAW_PERCENT_ALLOWED = 100;
$MAX_WITHDRAW_ALLOWED = 10;


//first recharge bonus details =============

$RECHARGE_BONUS_TYPE = "percent";
$RECHARGE_BONUS_PERCENTAGE = 10;


// api keys & tokens ==========
$MESSAGE_TOKEN = "";
$LIVE_CHAT_URL = "";


// Cron Access Token
$CRON_ACCESS_TOKEN = "NINJA_CRYPT_945329";

// Payout Gateway Config
$PAYOUT_MID = "lNhwIVArq40zXu3gaTUcPseYS";
$PAYOUT_MKEY = "VTFBD62wtszlAC0ormK1OqLNJ";
$PAYOUT_GUID = "AcWlbPwrI9E2SuH4LGF6xk1K3";
?>