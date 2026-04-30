<?php
$resArr["slideShowList"] = [];
$resArr["noticeArr"] = [];
$resArr["data"] = [];

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_host = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";


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
$active_bonus_id = 0;
$required_play = 0;

$select_sql = "SELECT * FROM tblusersdata WHERE tbl_uniq_id='{$user_id}' AND tbl_auth_secret ='{$secret_key}' ";
$select_query = mysqli_query($conn, $select_sql);

if ($user_id != "" && $secret_key != "" && mysqli_num_rows($select_query) > 0) {
    $res_data = mysqli_fetch_assoc($select_query);
    $account_status = $res_data["tbl_account_status"];

    // --- AUTO-SETTLEMENT LOGIC START ---
    $account_balance = (float) ($res_data["tbl_balance"] ?? 0);
    $bonus_balance = (float) ($res_data["tbl_bonus_balance"] ?? 0);
    $sports_bonus = (float) ($res_data["tbl_sports_bonus"] ?? 0);
    $total_balance = $account_balance + $bonus_balance + $sports_bonus;

    // --- AUTO-RESET WAGERING IF BALANCE IS NEAR ZERO ---
    if ($total_balance < 1.0 && (float) ($res_data["tbl_requiredplay_balance"] ?? 0) > 0) {
        mysqli_query($conn, "UPDATE tblusersdata SET 
                               tbl_requiredplay_balance = 0, 
                               tbl_active_bonus_id = 0, 
                               tbl_is_bonus_locked = 0 
                               WHERE tbl_uniq_id = '$user_id'");
        $res_data["tbl_requiredplay_balance"] = 0; // Update local array for subsequent logic
        $res_data["tbl_active_bonus_id"] = 0;
        $res_data["tbl_is_bonus_locked"] = 0;
    }

    $active_bonus_id = (int) ($res_data["tbl_active_bonus_id"] ?? 0);
    $required_play = (float) ($res_data["tbl_requiredplay_balance"] ?? 0);

    // --- HEAL GHOST WAGERING ---
    // If we have wagering but no active bonus ID, it's a corrupted state. Sync it to 0.
    if ($active_bonus_id === 0 && $required_play > 0) {
        mysqli_query($conn, "UPDATE tblusersdata SET tbl_requiredplay_balance = 0, tbl_is_bonus_locked = 0 WHERE tbl_uniq_id = '$user_id'");
        $required_play = 0;
        $res_data["tbl_requiredplay_balance"] = 0;
    }

    if ($active_bonus_id > 0 && $required_play > 0) {
        // --- AUTO-EXPIRATION LOGIC ---
        $exp_sql = "SELECT end_at FROM tbl_bonuses WHERE id = $active_bonus_id";
        $exp_res = mysqli_query($conn, $exp_sql);
        if ($erow = mysqli_fetch_assoc($exp_res)) {
            $end_at = $erow['end_at'];
            if (strtotime($end_at) < time()) {
                // Expire the bonus
                $expire_sql = "UPDATE tblusersdata SET 
                                 tbl_bonus_balance = 0, 
                                 tbl_sports_bonus = 0, 
                                 tbl_requiredplay_balance = 0, 
                                 tbl_active_bonus_id = 0, 
                                 tbl_is_bonus_locked = 0 
                                 WHERE tbl_uniq_id = '$user_id'";

                if (mysqli_query($conn, $expire_sql)) {
                    // Update redemptions status
                    mysqli_query($conn, "UPDATE tbl_bonus_redemptions SET status='expired', updated_at=NOW() WHERE user_id='$user_id' AND bonus_id=$active_bonus_id AND status='active'");

                    // Log Transaction
                    mysqli_query($conn, "INSERT INTO tblotherstransactions (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
                                           VALUES ('$user_id', 'app', 'bonus_expired', '0', 'Bonus ID: $active_bonus_id expired', NOW())");

                    // Update local variables for UI
                    $bonus_balance = 0;
                    $sports_bonus = 0;
                    $required_play = 0;
                    $active_bonus_id = 0;
                    $total_balance = $account_balance;
                    array_push($resArr['noticeArr'], "Bonus Expired", "Your active bonus has expired as the wagering requirement was not met within the validity period.");
                }
            }
        }
    }

    if ($active_bonus_id > 0 && $required_play <= 0) {
        mysqli_begin_transaction($conn);
        try {
            // Perform the settlement in the database
            $settle_amount = ($sports_bonus > 0) ? $sports_bonus : $bonus_balance;

            $settle_sql = "UPDATE tblusersdata SET 
                             tbl_balance = tbl_balance + $settle_amount, 
                             tbl_bonus_balance = 0, 
                             tbl_sports_bonus = 0,
                             tbl_active_bonus_id = 0, 
                             tbl_is_bonus_locked = 0 
                             WHERE tbl_uniq_id = '$user_id'";

            if (mysqli_query($conn, $settle_sql)) {
                // Update redemption history to 'completed'
                mysqli_query($conn, "UPDATE tbl_bonus_redemptions SET status='completed', wagering_completed=wagering_required, updated_at=NOW() WHERE user_id='$user_id' AND bonus_id=$active_bonus_id AND status='active'");

                // Log Transaction
                $log_sql = "INSERT INTO tblotherstransactions 
                              (tbl_user_id, tbl_received_from, tbl_transaction_type, tbl_transaction_amount, tbl_transaction_note, tbl_time_stamp) 
                              VALUES ('$user_id', 'app', 'bonus_settled', '$settle_amount', 'Bonus ID: $active_bonus_id', NOW())";
                mysqli_query($conn, $log_sql);

                // Update local variables for immediate UI update
                $account_balance += $settle_amount;
                $bonus_balance = 0;
                $sports_bonus = 0;
                $total_balance = $account_balance + $bonus_balance + $sports_bonus;

                // Add Notification
                array_push($resArr['noticeArr'], "Bonus Completed!", "Congratulations! Your bonus of ₹" . number_format($settle_amount, 2) . " has been converted to REAL balance.");
            }
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
        }
    }
    // --- AUTO-SETTLEMENT LOGIC END ---

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


    $notices_sql = "SELECT * FROM tblallnotices WHERE tbl_user_id='{$user_id}' AND tbl_notice_status='true' ORDER BY id DESC LIMIT 1";

    $notices_query = mysqli_query($conn, $notices_sql);
    if (mysqli_num_rows($notices_query) > 0) {
        $noticeResp = mysqli_fetch_assoc($notices_query);
        $noticeId = $noticeResp['id'];
        $noticeTitle = $noticeResp['tbl_notice_title'];
        $noticeNote = $noticeResp['tbl_notice_note'];

        array_push($resArr['noticeArr'], $noticeTitle, $noticeNote);

        // Logging for debug
        file_put_contents(__DIR__ . "/notice_debug.log", date('Y-m-d H:i:s') . " | NOTICE-FETCHED | User: $user_id | ID: " . $noticeId . " | Title: " . $noticeTitle . "\n", FILE_APPEND);

        $update_sql = "UPDATE tblallnotices SET tbl_notice_status = 'false' WHERE id = '{$noticeId}'";
        $update_query = mysqli_query($conn, $update_sql);
    }
}

// Always fetch branding data, regardless of login status
if (true) {

    $service_app_status = "";
    $service_min_recharge = "";
    $service_recharge_option = "";
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

        $service_active_payment = "";
    }

    // Set defaults if database values are missing
    if (empty($service_site_marquee)) {
        $service_site_marquee = "Welcome to Winco! Experience world-class betting and gaming. Sign up now to get exclusive bonuses and daily rewards. Minimum deposit ₹100. Fast 24/7 withdrawals.";
    }
    if (empty($service_site_name)) {
        $service_site_name = "Winco";
    }

    // --- Cashback Logic Start ---
    $claimable_cashback = 0;
    $cashback_log_id = 0;
    $cashback_sql = "SELECT id, cashback_amount FROM tbl_cashback_logs WHERE user_id = '$user_id' AND status = 'pending_claim' ORDER BY id DESC LIMIT 1";
    $cashback_res = mysqli_query($conn, $cashback_sql);
    if ($crow = mysqli_fetch_assoc($cashback_res)) {
        $claimable_cashback = (float) $crow['cashback_amount'];
        $cashback_log_id = (int) $crow['id'];
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

    // Add default slides if none exist in the database
    if (count($resArr['slideShowList']) == 0) {
        array_push($resArr['slideShowList'], [
            'slider_img' => 'https://images.unsplash.com/photo-1518173946687-a4c8a9833d8e?q=80&w=2000&auto=format&fit=crop',
            'slider_action' => '#'
        ]);
        array_push($resArr['slideShowList'], [
            'slider_img' => 'https://images.unsplash.com/photo-1540747737273-4629e0756eb9?q=80&w=2000&auto=format&fit=crop',
            'slider_action' => '#'
        ]);
    }

    $index = [];
    $index["account_id"] = $user_id;
    $index["account_level"] = $const_account_level;
    $index["account_avatar_id"] = $const_avatar_id;
    $index["account_username"] = $const_username;
    $index["account_full_name"] = $const_fullname;
    $index["account_email"] = $const_email;
    $index["account_mobile"] = $const_mobile_num;
    $index["account_mobile_num"] = $const_mobile_num; // For backward compatibility
    $index["account_balance"] = $const_account_balance;
    $index["account_b_balance"] = $const_account_bonus_balance;
    $index["account_w_balance"] = $const_account_withdrawl_balance;
    $index["account_c_balance"] = $const_account_commission_balance;
    $index["account_last_active"] = $const_account_last_active;

    $index["account_casino_bonus"] = $const_account_casino_bonus;
    $index["account_sports_bonus"] = $const_account_sports_bonus;
    $index["account_total_balance"] = $const_account_total_balance;

    // NEW: Pass explicit wagering fields to frontend
    $index["tbl_requiredplay_balance"] = $required_play;
    $index["tbl_active_bonus_id"] = $active_bonus_id;
    $index["tbl_is_bonus_locked"] = (int) ($res_data["tbl_is_bonus_locked"] ?? 0);
    $index["tbl_bonus_balance"] = (float) ($res_data["tbl_bonus_balance"] ?? 0);
    $index["tbl_sports_bonus"] = (float) ($res_data["tbl_sports_bonus"] ?? 0);

    // Bonus Tracking Fields for Frontend     // Fetch accurate wagering progress from redemption record
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
            // We use the raw float value of current play balance for maximum precision
            $current_rem = (float) ($res_data["tbl_requiredplay_balance"] ?? 0);

            $index["wagering_required"] = $initial_req;
            // Calculate completed dynamically (Initial - Remaining)
            $completed = $initial_req - $current_rem;
            $index["wagering_completed"] = ($completed > 0) ? (float) formatNumber($completed) : 0;
        }
    }

    $index["service_app_status"] = $service_app_status;
    $index["service_min_recharge"] = $service_min_recharge;
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

    $important_alert = explode(",", $service_imp_alert);
    // Only show global alerts to logged-in users (exclude guests)
    if ($user_id != "" && $user_id != "guest" && count($important_alert) > 1 && count($resArr['noticeArr']) <= 0) {
        array_push($resArr['noticeArr'], $important_alert[0], $important_alert[1]);
    }

    array_push($resArr["data"], $index);

    $resArr["status_code"] = "success";
}

mysqli_close($conn);
file_put_contents(__DIR__ . "/info_debug.log", date('Y-m-d H:i:s') . " - Sliders Count: " . count($resArr['slideShowList']) . " - Status: " . $resArr['status_code'] . "\n", FILE_APPEND);
echo json_encode($resArr);
?>