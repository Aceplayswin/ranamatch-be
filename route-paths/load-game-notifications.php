<?php
/**
 * load-game-notifications.php
 *
 * Backend-authoritative notification system for game results.
 * game/index.php is NEVER touched — all logic lives here.
 *
 * Two-stage approach (no dependency on game/index.php):
 *
 *  STAGE 1 — "Discovery":
 *    When a settled+unnotified record is seen for the first time,
 *    set tbl_notify_at = NOW() + 8 seconds.
 *    This gives the game iframe 8 seconds to show its own animation.
 *
 *  STAGE 2 — "Fire":
 *    On the next poll (5s later), return records whose tbl_notify_at has passed.
 *    Mark them tbl_notified = 1 so they never repeat.
 */

$user_id = "";

if (isset($_GET['USER_ID'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['USER_ID']);
}

if ($user_id == "") {
    $resArr['status_code'] = "invalid_params";
    echo json_encode($resArr);
    return;
}

// Robust Auth extraction
$secret_key = $headerObj->getAuthorization();
if ($secret_key === "null" || $secret_key === "") {
    $secret_key = $_GET['AuthToken'] ?? $_REQUEST['AuthToken'] ?? "";
}

// Log Auth Attempt
$auth_log = "   -> Auth Check | User: $user_id | Token: " . substr($secret_key, 0, 10) . "...\n";
file_put_contents(dirname(__DIR__) . "/router/play_debug.txt", $auth_log, FILE_APPEND);

if ($secret_key == "") {
    $resArr['status_code'] = "authorization_error";
    echo json_encode($resArr);
    return;
}

// Validate user session
$select_user_sql = "SELECT tbl_balance FROM tblusersdata
                    WHERE tbl_uniq_id = '{$user_id}'
                    AND tbl_auth_secret = '{$secret_key}'
                    AND tbl_account_status = 'true'";
$select_user_query = mysqli_query($conn, $select_user_sql);

if (mysqli_num_rows($select_user_query) == 0) {
    file_put_contents(dirname(__DIR__) . "/router/play_debug.txt", "   -> DB AUTH FAILED for User: $user_id\n", FILE_APPEND);
    $resArr['status_code'] = "authorization_error";
    echo json_encode($resArr);
    return;
}

// ---------------------------------------------------------------
// STAGE 1: Discovery / Recovery
// Ensure all settled records have a notify timestamp.
// Even if game/index.php misses it, this will pick it up.
// ---------------------------------------------------------------
mysqli_query($conn, "UPDATE tblmatchplayed
    SET tbl_notify_at = NOW()
    WHERE tbl_user_id = '{$user_id}'
    AND tbl_match_status NOT IN ('wait')
    AND tbl_notified = 0
    AND tbl_notify_at IS NULL");

// ---------------------------------------------------------------
// STAGE 2: Fire
// ---------------------------------------------------------------

// ---------------------------------------------------------------
// STAGE 2: Fire
$notify_sql = "SELECT *
               FROM tblmatchplayed
               WHERE tbl_user_id = '{$user_id}'
               AND tbl_match_status NOT IN ('wait')
               AND tbl_notified = 0
               AND tbl_notify_at IS NOT NULL
               ORDER BY id ASC LIMIT 1";

$notify_result = mysqli_query($conn, $notify_sql);

$notifications = [];
$ids_to_mark = [];

if ($notify_result) {
    while ($row = mysqli_fetch_assoc($notify_result)) {
        $status = $row['tbl_match_status'];
        $is_win = ($status === 'profit' || $status === 'cashout');
        $is_rejected = ($status === 'rejected');
        $details = isset($row['tbl_match_details']) ? $row['tbl_match_details'] : "";

        $notifications[] = [
            'id' => (int) $row['id'],
            'r_match_name' => $row['tbl_project_name'] ?? "Game",
            'r_match_status' => $status,
            'r_match_amount' => (float) ($row['tbl_match_cost'] ?? 0),
            'r_match_profit' => (float) ($row['tbl_match_profit'] ?? 0),
            'is_win' => $is_win,
            'is_rejection' => $is_rejected,
            'message' => $is_rejected ? ($details ?: "Maximum odds limit for Sports is 4.0x. Your bet was rejected.") : ""
        ];
        $ids_to_mark[] = (int) $row['id'];
    }
}

// Mark as notified — never shows again
if (!empty($ids_to_mark)) {
    $ids_str = implode(',', $ids_to_mark);
    mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_notified = 1 WHERE id IN ({$ids_str})");
}

if (!empty($notifications)) {
    $resArr['status_code'] = "success";
    $resArr['data'] = $notifications;
} else {
    $resArr['status_code'] = "no_notifications";
    $resArr['data'] = [];
}

// DIAGNOSTIC LOGGING
$debug_msg = date('Y-m-d H:i:s') . " | POLL | User: $user_id | Found: " . count($notifications) . " | SQL: " . (mysqli_error($conn) ?: "OK") . "\n";
file_put_contents(__DIR__ . "/notification_debug.log", $debug_msg, FILE_APPEND);

echo json_encode($resArr);
?>