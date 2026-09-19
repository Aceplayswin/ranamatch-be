<?php
$resArr["slideShowList"] = [];
$resArr["navbarCategories"] = [];
$resArr["noticeArr"] = [];
$resArr["data"] = [];

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
$script_dir = dirname(dirname($script_name));
$script_dir = str_replace('\\', '/', $script_dir);
$base_path = ($script_dir === '/' || $script_dir === '.' || $script_dir === '') ? '/' : rtrim($script_dir, '/') . '/';
$current_host = $protocol . "://" . $host . $base_path;
$current_host = preg_replace('/([^:])(\/{2,})/', '$1/', str_replace('\\', '/', $current_host));
if (substr($current_host, -1) !== '/') {
    $current_host .= '/';
}


function formatNumber($number)
{
    return number_format($number, 2, ".", "");
}

$user_id = "";
$secret_key = "";

if (isset($_GET["USER_ID"])) {
    $user_id = mysqli_real_escape_string($conn, $_GET["USER_ID"]);
}

// Entry Logging
file_put_contents(__DIR__ . "/user_debug.log", date('Y-m-d H:i:s') . " | REQ | User: $user_id | Token: " . substr($headerObj->getAuthorization(), 0, 10) . "...\n", FILE_APPEND);

if ($user_id != "") {
    $secret_key = $headerObj->getAuthorization();
    if ($secret_key === "null" || $secret_key === "") {
        $secret_key = $_GET['AuthToken'] ?? $_REQUEST['AuthToken'] ?? "";
    }
}


$account_status = "true";
$const_account_level = "";
$const_avatar_id = "";
$const_fullname = "";
$const_username = "Guest";
$const_mobile_num = "";
$const_email = "";
$const_account_balance = "0.00";
$const_account_withdrawl_balance = "0.00";
$const_account_commission_balance = "0.00";
$const_account_last_active = "";
$const_account_casino_bonus = "0.00";
$const_account_sports_bonus = "0.00";
$const_account_total_balance = "0.00";
$const_account_bonus_balance = "0.00";
$const_account_exposure = "0.00";
$active_bonus_id = 0;
$required_play = 0;

$is_guest = ($user_id === "guest" && $secret_key === "guest");
if ($is_guest) {
    $res_data = [
        "tbl_is_bonus_locked" => 0,
        "tbl_bonus_balance" => 0,
        "tbl_sports_bonus" => 0,
        "tbl_requiredplay_balance" => 0
    ];
    $const_account_level = "1";
    $const_avatar_id = "1";
    $const_fullname = "Demo Play";
    $const_username = "Guest";
    $const_mobile_num = "0000000000";
    $const_email = "guest@demo.com";
    $const_account_balance = "0.00";
    $const_account_casino_bonus = "0.00";
    $const_account_sports_bonus = "0.00";
    $const_account_total_balance = "0.00";
    $const_account_bonus_balance = "0.00";
    $const_account_withdrawl_balance = "0.00";
    $const_account_commission_balance = "0.00";
    $const_account_last_active = date("d-m-Y h:i:s a");
    $const_account_exposure = "0.00";

    $notices_sql = "SELECT * FROM tblallnotices WHERE tbl_user_id='guest' AND tbl_notice_status='true' ORDER BY id DESC LIMIT 1";
    $notices_query = mysqli_query($conn, $notices_sql);
    if (mysqli_num_rows($notices_query) > 0) {
        $noticeResp = mysqli_fetch_assoc($notices_query);
        $noticeId = $noticeResp['id'];
        $noticeTitle = $noticeResp['tbl_notice_title'];
        $noticeNote = $noticeResp['tbl_notice_note'];
        array_push($resArr['noticeArr'], $noticeTitle, $noticeNote);
        mysqli_query($conn, "UPDATE tblallnotices SET tbl_notice_status = 'false' WHERE id = '{$noticeId}'");
    }
} else {
    $select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$user_id}' AND tbl_auth_secret ='{$secret_key}' ";
    $select_query = mysqli_query($conn, $select_sql);

    // If a user ID and secret key are provided, but the database has no match, the session is invalid (e.g. logged in elsewhere)
    if ($user_id != "" && $secret_key != "" && mysqli_num_rows($select_query) == 0) {
        $resArr["status_code"] = "session_expired";
        $resArr["message"] = "Logged in from another device";
        echo json_encode($resArr);
        exit();
    }

    if (mysqli_num_rows($select_query) > 0) {
        $res_data = mysqli_fetch_assoc($select_query);

        // Fetch active bonus details
        $active_bonus_id = (int) ($res_data["tbl_active_bonus_id"] ?? 0);
        $required_play = (float) ($res_data["tbl_requiredplay_balance"] ?? 0);
        $bonus_balance = (float) ($res_data["tbl_bonus_balance"] ?? 0);
        $sports_bonus = (float) ($res_data["tbl_sports_bonus"] ?? 0);
        $account_balance = (float) ($res_data["tbl_balance"] ?? 0);
        $total_balance = $account_balance + $bonus_balance + $sports_bonus;

        // --- AUTO-SETTLEMENT LOGIC START ---
        if ($active_bonus_id > 0 && $required_play <= 0.01) {
            mysqli_begin_transaction($conn);
            try {
                $settle_amount = $bonus_balance;
                if ($settle_amount > 0) {
                    mysqli_query($conn, "UPDATE tblusersdata SET 
                        tbl_balance = tbl_balance + {$settle_amount}, 
                        tbl_bonus_balance = 0, 
                        tbl_sports_bonus = 0, 
                        tbl_is_bonus_locked = 0, 
                        tbl_active_bonus_id = 0, 
                        tbl_requiredplay_balance = 0 
                        WHERE tbl_uniq_id = '{$user_id}'");

                    mysqli_query($conn, "UPDATE tbl_bonus_redemptions SET 
                        status = 'completed', 
                        wagering_completed = wagering_required, 
                        completed_at = CURRENT_TIMESTAMP 
                        WHERE user_id = '{$user_id}' AND bonus_id = {$active_bonus_id} AND status = 'active'");

                    $trans_uniq_id = $headerObj->getRandomString(15);
                    $bonus_note = "Bonus Rollover Completed (Unlocked to Real Balance)";
                    mysqli_query($conn, "INSERT INTO tblothertransactions(tbl_uniq_id, tbl_user_id, tbl_trans_amount, tbl_trans_type, tbl_trans_details, tbl_time_stamp) 
                        VALUES ('{$trans_uniq_id}', '{$user_id}', '{$settle_amount}', 'Credit', '{$bonus_note}', '{$curr_date_time}')");

                    $account_balance += $settle_amount;
                    $bonus_balance = 0;
                    $sports_bonus = 0;
                    $active_bonus_id = 0;
                    $required_play = 0;
                    $total_balance = $account_balance;

                    array_push($resArr['noticeArr'], "Bonus Completed!", "Congratulations! Your bonus of ₹" . number_format($settle_amount, 2) . " has been converted to REAL balance.");
                }
                mysqli_commit($conn);
            } catch (Exception $e) {
                mysqli_rollback($conn);
            }
        }
        // --- AUTO-SETTLEMENT LOGIC END ---

        $const_account_level = $res_data["tbl_account_level"];
        $const_avatar_id = $res_data["tbl_avatar_id"];
        $const_fullname = $res_data["tbl_full_name"];
        $const_username = $res_data["tbl_user_name"] ?? $res_data["tbl_full_name"];
        $const_mobile_num = $res_data["tbl_mobile_num"];
        $const_email = $res_data["tbl_email_id"] ?? "—";
        $const_account_balance = formatNumber($account_balance);
        $const_account_casino_bonus = formatNumber($bonus_balance);
        $const_account_sports_bonus = formatNumber($sports_bonus);
        $const_account_total_balance = formatNumber($total_balance);
        $const_account_bonus_balance = formatNumber($bonus_balance);
        $const_account_withdrawl_balance = formatNumber($res_data["tbl_withdrawl_balance"]);
        $const_account_commission_balance = formatNumber($res_data["tbl_commission_balance"]);
        $const_account_last_active = $res_data["tbl_last_active_date"] . ' ' . $res_data["tbl_last_active_time"];

        // Calculate exposure dynamically from active bets (where status = 'wait')
        $exposure_res = mysqli_query($conn, "SELECT SUM(tbl_match_cost) AS total_exposure FROM tblmatchplayed WHERE tbl_user_id = '{$user_id}' AND tbl_match_status = 'wait'");
        $exposure_row = mysqli_fetch_assoc($exposure_res);
        $const_account_exposure = formatNumber($exposure_row['total_exposure'] ?? 0);

        function replaceNoticePlaceholders($text, $conn, $user_id, $res_data, $account_balance) {
            if (empty($text)) return $text;
            
            $real_username = !empty($res_data['tbl_user_name']) ? $res_data['tbl_user_name'] : (!empty($res_data['tbl_full_name']) ? $res_data['tbl_full_name'] : 'User');
            $user_fullname = !empty($res_data['tbl_full_name']) ? $res_data['tbl_full_name'] : $real_username;
            $user_email = !empty($res_data['tbl_email_id']) ? $res_data['tbl_email_id'] : 'N/A';
            $user_phone = !empty($res_data['tbl_mobile_num']) ? $res_data['tbl_mobile_num'] : 'N/A';
            $user_level = !empty($res_data['tbl_account_level']) ? $res_data['tbl_account_level'] : '1';

            // Fetch User Stats
            $wins_q = mysqli_query($conn, "SELECT COUNT(*) as win_count FROM tblmatchplayed WHERE tbl_user_id = '$user_id' AND tbl_match_status IN ('profit', 'cashout', 'win')");
            $total_wins = ($wins_r = mysqli_fetch_assoc($wins_q)) ? (int)($wins_r['win_count'] ?? 0) : 0;

            $last_game_q = mysqli_query($conn, "SELECT tbl_project_name FROM tblmatchplayed WHERE tbl_user_id = '$user_id' ORDER BY id DESC LIMIT 1");
            $last_played_game = ($last_game_r = mysqli_fetch_assoc($last_game_q)) ? ($last_game_r['tbl_project_name'] ?: 'None') : 'None';

            $dep_q = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) as total_dep FROM tblusersrecharge WHERE tbl_user_id = '$user_id' AND tbl_request_status = 'success'");
            $total_deposit = ($dep_r = mysqli_fetch_assoc($dep_q)) ? (float)($dep_r['total_dep'] ?? 0) : 0;

            $placeholders = [
                '@username' => $real_username,
                '@name' => $user_fullname,
                '@fullname' => $user_fullname,
                '@userid' => $user_id,
                '@id' => $user_id,
                '@uid' => $user_id,
                '@balance' => '₹' . number_format($account_balance, 2),
                '@rawbalance' => number_format($account_balance, 2, '.', ''),
                '@mail' => $user_email,
                '@email' => $user_email,
                '@phone' => $user_phone,
                '@mobile' => $user_phone,
                '@lastplayed' => $last_played_game,
                '@last_played' => $last_played_game,
                '@lastgame' => $last_played_game,
                '@wins' => (string)$total_wins,
                '@total_wins' => (string)$total_wins,
                '@deposit' => '₹' . number_format($total_deposit, 2),
                '@total_deposit' => '₹' . number_format($total_deposit, 2),
                '@level' => (string)$user_level
            ];

            foreach ($placeholders as $tag => $val) {
                $text = str_ireplace($tag, $val, $text);
            }
            return $text;
        }

        // 1. Check active broadcasts from tbl_broadcasts
        $now_bc = date('Y-m-d H:i:s');
        $bc_sql = "SELECT * FROM tbl_broadcasts 
                   WHERE (start_time IS NULL OR start_time <= '$now_bc' OR start_time <= NOW()) 
                   AND (end_time IS NULL OR end_time >= '$now_bc' OR end_time >= NOW()) 
                   AND (target_type = 'all' OR target_type = 'filter' OR target_type = '' OR target_type IS NULL OR target_uid = '$user_id')
                   ORDER BY id DESC LIMIT 1";
        $bc_res = mysqli_query($conn, $bc_sql);
        if ($bc_res && mysqli_num_rows($bc_res) > 0) {
            $bc_row = mysqli_fetch_assoc($bc_res);
            $can_show = true;
            
            // Check filters
            if (!empty($bc_row['filter_min_wins'])) {
                $w_chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tblmatchplayed WHERE tbl_user_id='$user_id' AND tbl_match_status IN ('profit','cashout','win')"));
                if ((int)($w_chk['c'] ?? 0) < (int)$bc_row['filter_min_wins']) $can_show = false;
            }
            if ($can_show && !empty($bc_row['filter_min_balance'])) {
                if ($account_balance < (float)$bc_row['filter_min_balance']) $can_show = false;
            }
            if ($can_show && !empty($bc_row['filter_min_deposit'])) {
                $d_chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) as c FROM tblusersrecharge WHERE tbl_user_id='$user_id' AND tbl_request_status='success'"));
                if ((float)($d_chk['c'] ?? 0) < (float)$bc_row['filter_min_deposit']) $can_show = false;
            }

            if ($can_show) {
                $bc_title = replaceNoticePlaceholders($bc_row['title'], $conn, $user_id, $res_data, $account_balance);
                $bc_msg = replaceNoticePlaceholders($bc_row['message'], $conn, $user_id, $res_data, $account_balance);
                $resArr['noticeId'] = (string)$bc_row['id'];
                array_push($resArr['noticeArr'], $bc_title, $bc_msg);
            }
        }

        // 2. Fallback: User-specific notices from tblallnotices
        if (count($resArr['noticeArr']) == 0) {
            $notices_sql = "SELECT * FROM tblallnotices WHERE tbl_user_id='{$user_id}' AND tbl_notice_status='true' ORDER BY id DESC LIMIT 1";
            $notices_query = mysqli_query($conn, $notices_sql);
            if (mysqli_num_rows($notices_query) > 0) {
                $noticeResp = mysqli_fetch_assoc($notices_query);
                $noticeId = $noticeResp['id'];
                $noticeTitle = replaceNoticePlaceholders($noticeResp['tbl_notice_title'], $conn, $user_id, $res_data, $account_balance);
                $noticeNote = replaceNoticePlaceholders($noticeResp['tbl_notice_note'], $conn, $user_id, $res_data, $account_balance);
                array_push($resArr['noticeArr'], $noticeTitle, $noticeNote);

                $update_sql = "UPDATE tblallnotices SET tbl_notice_status = 'false' WHERE id = '{$noticeId}'";
                mysqli_query($conn, $update_sql);
            }
        }
    }
}

// Always fetch branding and service data, regardless of login status
$service_app_status = "";
$service_min_recharge = "500";
$service_min_withdraw = "1000";
$service_recharge_option = "500,1000,2000,5000,10000,25000,50000";
$service_telegram_url = "";
$service_imp_message = "";
$service_imp_alert = "";
$service_site_logo = "";
$service_whatsapp = "";
$service_support_url = "";

$service_site_address = "";
$service_site_tagline = "";
$service_site_marquee = "";
$service_site_name = "";
$service_social_links = [];
$service_brand_color = "";
$service_brand_gradient_end = "";
$service_bg_color = "";
$service_text_color = "";

$services_sql = "SELECT * FROM tblservices";
$services_query = mysqli_query($conn, $services_sql);
while ($row = mysqli_fetch_array($services_query)) {
    if ($row['tbl_service_name'] == "APP_STATUS") {
        $service_app_status = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "RECHARGE_OPTIONS") {
        $service_recharge_option = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "MIN_RECHARGE") {
        $service_min_recharge = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "MIN_WITHDRAW") {
        $service_min_withdraw = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "TELEGRAM_URL") {
        $service_telegram_url = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_LOGO_URL") {
        $val = $row['tbl_service_value'];
        $service_site_logo = (strpos($val, 'http') === 0) ? $val : $current_host . $val;
    } else if ($row['tbl_service_name'] == "CONTACT_WHATSAPP") {
        $service_whatsapp = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "CONTACT_SUPPORT_URL") {
        $service_support_url = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "IMP_MESSAGE") {
        $service_imp_message = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "IMP_ALERT") {
        $service_imp_alert = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_ADDRESS") {
        $service_site_address = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_TAGLINE") {
        $service_site_tagline = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_MARQUEE") {
        $service_site_marquee = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_SOCIAL_LINKS") {
        $service_social_links = json_decode($row['tbl_service_value'], true) ?: [];
    } else if ($row['tbl_service_name'] == "SITE_NAME") {
        $service_site_name = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_BRAND_COLOR") {
        $service_brand_color = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_BRAND_GRADIENT_END") {
        $service_brand_gradient_end = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_BG_COLOR") {
        $service_bg_color = $row['tbl_service_value'];
    } else if ($row['tbl_service_name'] == "SITE_TEXT_COLOR") {
        $service_text_color = $row['tbl_service_value'];
    }
}

// Set defaults if database values are missing
if (empty($service_site_marquee)) {
    $service_site_marquee = "Welcome to Winco! Experience world-class betting and gaming. Sign up now to get exclusive bonuses and daily rewards. Fast 24/7 withdrawals.";
}
if (empty($service_site_name)) {
    $service_site_name = "Winco";
}

// --- Cashback Logic Start ---
$claimable_cashback = 0;
$cashback_log_id = 0;
if ($user_id != "" && !$is_guest) {
    $cashback_sql = "SELECT id, cashback_amount FROM tbl_cashback_logs WHERE user_id = '$user_id' AND status = 'pending_claim' ORDER BY id DESC LIMIT 1";
    $cashback_res = mysqli_query($conn, $cashback_sql);
    if ($crow = mysqli_fetch_assoc($cashback_res)) {
        $claimable_cashback = (float) $crow['cashback_amount'];
        $cashback_log_id = (int) $crow['id'];
    }
}

$config_res = mysqli_query($conn, "SELECT claim_mode FROM tbl_cashback_config WHERE id = 1");
$crow_config = mysqli_fetch_assoc($config_res);
$cashback_mode = $crow_config['claim_mode'] ?? 'automatic';
// --- Cashback Logic End ---

$sliders_sql = "SELECT * FROM tblsliders WHERE tbl_slider_status='true' ";
$sliders_query = mysqli_query($conn, $sliders_sql);
while ($row = mysqli_fetch_array($sliders_query)) {
    $slideIndex['slider_img'] = $current_host . $row["tbl_slider_img"];
    $slideIndex['slider_action'] = $row["tbl_slider_action"];

    array_push($resArr['slideShowList'], $slideIndex);
}

$index = [];
$index["account_id"] = $user_id;
$index["account_level"] = $const_account_level;
$index["account_avatar_id"] = $const_avatar_id;
$index["account_username"] = $const_username;
$index["account_full_name"] = $const_fullname;
$index["account_email"] = $const_email;
$index["account_mobile"] = $const_mobile_num;
$index["account_mobile_num"] = $const_mobile_num;
$index["account_balance"] = $const_account_balance;
$index["account_b_balance"] = $const_account_bonus_balance;
$index["account_w_balance"] = $const_account_withdrawl_balance;
$index["account_c_balance"] = $const_account_commission_balance;
$index["account_exposure"] = $const_account_exposure;
$index["account_last_active"] = $const_account_last_active;

$index["account_casino_bonus"] = $const_account_casino_bonus;
$index["account_sports_bonus"] = $const_account_sports_bonus;
$index["account_total_balance"] = $const_account_total_balance;

$index["tbl_requiredplay_balance"] = $required_play;
$index["tbl_active_bonus_id"] = $active_bonus_id;
$index["tbl_is_bonus_locked"] = (int) ($res_data["tbl_is_bonus_locked"] ?? 0);
$index["tbl_bonus_balance"] = (float) ($res_data["tbl_bonus_balance"] ?? 0);
$index["tbl_sports_bonus"] = (float) ($res_data["tbl_sports_bonus"] ?? 0);

$index["wagering_required"] = 0;
$index["wagering_completed"] = 0;
if ($active_bonus_id > 0) {
    $wager_sql = "SELECT wagering_required, wagering_completed 
                  FROM tbl_bonus_redemptions 
                  WHERE user_id='{$user_id}' AND bonus_id={$active_bonus_id} AND status='active' 
                  ORDER BY id DESC LIMIT 1";
    $wager_res = mysqli_query($conn, $wager_sql);
    if ($wager_row = mysqli_fetch_assoc($wager_res)) {
        $initial_req = (float) $wager_row["wagering_required"];
        $current_rem = (float) ($res_data["tbl_requiredplay_balance"] ?? 0);
        $index["wagering_required"] = $initial_req;
        $completed = $initial_req - $current_rem;
        $index["wagering_completed"] = ($completed > 0) ? (float) formatNumber($completed) : 0;
    }
}

$index["service_app_status"] = $service_app_status;
$index["service_min_recharge"] = $service_min_recharge;
$index["service_min_withdraw"] = $service_min_withdraw;
$index["service_recharge_options"] = $service_recharge_option;
$index["service_recharge_option"] = $service_recharge_option;
$index["service_telegram_url"] = $service_telegram_url;
$index["service_whatsapp"] = $service_whatsapp;
$index["service_support_url"] = $service_support_url;
$index["service_site_logo"] = $service_site_logo;
$index["claimable_cashback"] = $claimable_cashback;
$index["cashback_log_id"] = $cashback_log_id;
$index["cashback_mode"] = $cashback_mode;

$index["service_address"] = $service_site_address;
$index["service_tagline"] = $service_site_tagline;
$index["service_marquee"] = $service_site_marquee;
$index["service_social_links"] = $service_social_links;
$index["service_site_name"] = $service_site_name;
$index["service_brand_color"] = $service_brand_color;
$index["service_brand_gradient_end"] = $service_brand_gradient_end;
$index["service_bg_color"] = $service_bg_color;
$index["service_text_color"] = $service_text_color;

$index["service_livechat_url"] = $LIVE_CHAT_URL ?? "";
$index["service_app_download_url"] = $APP_DOWNLOAD_URL ?? "";
$index["service_payment_url"] = $PAY_TARGET_URL ?? "";
$index["service_imp_message"] = $service_imp_message;

$resArr["promo_banners"] = [];
$promo_sql = "SELECT id, image_path, action_url FROM tbl_promotions WHERE status = 'true' ORDER BY id DESC";
$promos = mysqli_query($conn, $promo_sql);
if ($promos) {
    while ($p = mysqli_fetch_assoc($promos)) {
        $img = $current_host . $p['image_path'];
        array_push($resArr["promo_banners"], [
            "id" => $p['id'],
            "title" => "Promotion",
            "description" => "",
            "image" => $img,
            "category" => "all",
            "type" => "standard",
            "action" => $p['action_url'] ?: "#"
        ]);
    }
}

$important_alert = explode(",", $service_imp_alert, 2);
if ($user_id != "" && $user_id != "guest" && count($important_alert) > 1 && count($resArr['noticeArr']) <= 0) {
    $alt_title = replaceNoticePlaceholders($important_alert[0], $conn, $user_id, $res_data, $account_balance);
    $alt_note = replaceNoticePlaceholders($important_alert[1], $conn, $user_id, $res_data, $account_balance);
    array_push($resArr['noticeArr'], $alt_title, $alt_note);
}

$nav_sql = "SELECT DISTINCT navbar_category FROM tbl_games WHERE game_status = 1 AND navbar_category IS NOT NULL AND navbar_category != '' ORDER BY navbar_category ASC";
$nav_result = mysqli_query($conn, $nav_sql);
if ($nav_result) {
    while ($row = mysqli_fetch_assoc($nav_result)) {
        array_push($resArr["navbarCategories"], $row["navbar_category"]);
    }
}

array_push($resArr["data"], $index);
$resArr["status_code"] = "success";

mysqli_close($conn);
echo json_encode($resArr);
?>