<?php
/**
 * Affiliate Notification Helper Service
 * Dispatches real-time alerts for KYC status, ticket replies, payouts, and account updates.
 */

if (!function_exists('sendAffiliateNotification')) {
    function sendAffiliateNotification($conn, $affiliateId, $type, $title, $message, $link = null) {
        try {
            $affId = (int)$affiliateId;
            if ($affId <= 0 || empty($title) || empty($message)) {
                return false;
            }

            $type = strtolower(trim($type ?: 'system'));
            $validTypes = ['kyc', 'ticket', 'payout', 'account', 'commission', 'system'];
            if (!in_array($type, $validTypes)) {
                $type = 'system';
            }

            $stmt = @mysqli_prepare(
                $conn, 
                "INSERT INTO affiliate_notifications (affiliate_id, type, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, "issss", $affId, $type, $title, $message, $link);
            $res = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $res;
        } catch (\Throwable $exNotif) {
            return false;
        }
    }
}
