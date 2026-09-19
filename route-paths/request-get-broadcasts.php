<?php
header("Content-Type: application/json");
if (!defined("ACCESS_SECURITY")) define("ACCESS_SECURITY", "true");
include_once dirname(__DIR__) . '/security/config.php';
include_once dirname(__DIR__) . '/security/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$resArr = ["status_code" => "error", "data" => []];

$user_id = "";
if (isset($_SESSION["tbl_user_id"])) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION["tbl_user_id"]);
} elseif (isset($_GET['USER_ID']) || isset($_POST['USER_ID'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['USER_ID'] ?? $_POST['USER_ID'] ?? '');
}

if (!empty($user_id)) {
    $now = date('Y-m-d H:i:s');

    // 1. Fetch user profile data
    $u_res = mysqli_query($conn, "SELECT tbl_uniq_id, tbl_user_name, tbl_full_name, tbl_email_id, tbl_mobile_num, tbl_balance, tbl_account_level FROM tblusersdata WHERE tbl_uniq_id = '$user_id'");
    $u_row = ($u_res && mysqli_num_rows($u_res) > 0) ? mysqli_fetch_assoc($u_res) : [];

    $real_username = !empty($u_row['tbl_user_name']) ? $u_row['tbl_user_name'] : (!empty($u_row['tbl_full_name']) ? $u_row['tbl_full_name'] : 'User');
    $user_fullname = !empty($u_row['tbl_full_name']) ? $u_row['tbl_full_name'] : $real_username;
    $user_balance = (float)($u_row['tbl_balance'] ?? 0);
    $user_email = !empty($u_row['tbl_email_id']) ? $u_row['tbl_email_id'] : 'N/A';
    $user_phone = !empty($u_row['tbl_mobile_num']) ? $u_row['tbl_mobile_num'] : 'N/A';
    $user_level = !empty($u_row['tbl_account_level']) ? $u_row['tbl_account_level'] : '1';

    // 2. Fetch User Stats (Total Wins, Last Played Game, Total Deposits)
    $wins_q = mysqli_query($conn, "SELECT COUNT(*) as win_count FROM tblmatchplayed WHERE tbl_user_id = '$user_id' AND tbl_match_status IN ('profit', 'cashout', 'win')");
    $total_wins = ($wins_r = mysqli_fetch_assoc($wins_q)) ? (int)($wins_r['win_count'] ?? 0) : 0;

    $last_game_q = mysqli_query($conn, "SELECT tbl_project_name FROM tblmatchplayed WHERE tbl_user_id = '$user_id' ORDER BY id DESC LIMIT 1");
    $last_played_game = ($last_game_r = mysqli_fetch_assoc($last_game_q)) ? ($last_game_r['tbl_project_name'] ?: 'None') : 'None';

    $dep_q = mysqli_query($conn, "SELECT SUM(tbl_recharge_amount) as total_dep FROM tblusersrecharge WHERE tbl_user_id = '$user_id' AND tbl_request_status = 'success'");
    $total_deposit = ($dep_r = mysqli_fetch_assoc($dep_q)) ? (float)($dep_r['total_dep'] ?? 0) : 0;

    // Fetch active broadcasts
    $sql = "
        SELECT b.* 
        FROM tbl_broadcasts b
        WHERE (b.start_time IS NULL OR b.start_time <= '$now' OR b.start_time <= NOW()) 
        AND (b.end_time IS NULL OR b.end_time >= '$now' OR b.end_time >= NOW())
        AND (b.target_type = 'all' OR b.target_type = 'filter' OR b.target_type = '' OR b.target_type IS NULL OR b.target_uid = '$user_id')
        ORDER BY b.id DESC
    ";

    $result = mysqli_query($conn, $sql);
    if ($result) {
        $broadcasts = [];
        
        $placeholders = [
            '@username' => $real_username,
            '@name' => $user_fullname,
            '@fullname' => $user_fullname,
            '@userid' => $user_id,
            '@id' => $user_id,
            '@uid' => $user_id,
            '@balance' => '₹' . number_format($user_balance, 2),
            '@rawbalance' => number_format($user_balance, 2, '.', ''),
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

        while ($row = mysqli_fetch_assoc($result)) {
            // Apply filtering criteria if configured
            if (!empty($row['filter_min_wins']) && $total_wins < (int)$row['filter_min_wins']) {
                continue; // Skip: user doesn't meet the min wins requirement
            }
            if (!empty($row['filter_min_balance']) && $user_balance < (float)$row['filter_min_balance']) {
                continue; // Skip: user doesn't meet the min balance requirement
            }
            if (!empty($row['filter_min_deposit']) && $total_deposit < (float)$row['filter_min_deposit']) {
                continue; // Skip: user doesn't meet the min deposit requirement
            }

            $title = $row['title'];
            $msg = $row['message'];

            foreach ($placeholders as $tag => $replacement) {
                $title = str_ireplace($tag, $replacement, $title);
                $msg = str_ireplace($tag, $replacement, $msg);
            }
            
            $broadcasts[] = [
                "id" => $row['id'],
                "title" => $title,
                "message" => $msg,
                "show_once" => (int)$row['show_once']
            ];
        }
        $resArr["status_code"] = "success";
        $resArr["data"] = $broadcasts;
    } else {
        $resArr["message"] = mysqli_error($conn);
    }
} else {
    $resArr["status_code"] = "unauthorized";
}

mysqli_close($conn);
echo json_encode($resArr);
?>
