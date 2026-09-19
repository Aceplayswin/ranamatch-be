<?php
/**
 * PayoutService
 * Manages the affiliate withdrawal request lifecycle:
 * - Request payout (validates minimum threshold, locks available balance)
 * - Approve payout (admin approval)
 * - Mark paid (records UTR reference & lifetime earnings)
 * - Reject payout (refunds balance to affiliate)
 * Uses pure mysqli.
 */

require_once __DIR__ . '/NotificationHelper.php';

class PayoutService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Affiliate requests a payout
     */
    public function requestPayout(int $affiliateId, float $amount, int $payoutMethodId): array {
        mysqli_begin_transaction($this->conn);

        try {
            // 1. Fetch program minimum payout threshold and hold days
            $settRes = mysqli_query($this->conn, "SELECT affiliate_minimum_payout, affiliate_payout_hold_days FROM program_settings WHERE id = 1");
            $sett = mysqli_fetch_assoc($settRes);
            $minPayout = (float)($sett['affiliate_minimum_payout'] ?? 1000.00);
            $holdDays = (int)($sett['affiliate_payout_hold_days'] ?? 7);

            if ($amount < $minPayout) {
                mysqli_commit($this->conn);
                return ["success" => false, "message" => "Minimum payout threshold is ₹" . number_format($minPayout, 2)];
            }

            // 2. Fetch affiliate details & available balance with row lock
            $stmt = mysqli_prepare($this->conn, "SELECT available_balance, created_at, last_settled_at FROM affiliates WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $affiliateId);
            mysqli_stmt_execute($stmt);
            $aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$aff) {
                mysqli_commit($this->conn);
                return ["success" => false, "message" => "Affiliate not found"];
            }

            // Check payout lock hold period
            if ($holdDays > 0) {
                $lastAnchor = !empty($aff['last_settled_at']) ? $aff['last_settled_at'] : $aff['created_at'];
                if (!empty($lastAnchor)) {
                    $anchorTime = strtotime($lastAnchor);
                    $diffSeconds = time() - $anchorTime;
                    $diffDays = (int)floor($diffSeconds / 86400);
                    if ($diffDays < $holdDays) {
                        $remainingDays = $holdDays - $diffDays;
                        mysqli_commit($this->conn);
                        return [
                            "success" => false, 
                            "message" => "Payout lock active. You must wait {$remainingDays} more day(s) before requesting a payout (Hold period: {$holdDays} days)."
                        ];
                    }
                }
            }

            $availBalance = (float)$aff['available_balance'];
            if ($availBalance < $amount) {
                mysqli_commit($this->conn);
                return ["success" => false, "message" => "Insufficient available balance. You have ₹" . number_format($availBalance, 2)];
            }

            // 3. Deduct amount from available balance
            $newBalance = $availBalance - $amount;
            $upd = mysqli_prepare($this->conn, "UPDATE affiliates SET available_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "di", $newBalance, $affiliateId);
            mysqli_stmt_execute($upd);

            // 4. Fetch payout method details
            $pmStmt = mysqli_prepare($this->conn, "SELECT method_type, account_details FROM affiliate_payout_methods WHERE id = ? AND affiliate_id = ?");
            mysqli_stmt_bind_param($pmStmt, "ii", $payoutMethodId, $affiliateId);
            mysqli_stmt_execute($pmStmt);
            $pm = mysqli_fetch_assoc(mysqli_stmt_get_result($pmStmt));
            
            $methodType = $pm['method_type'] ?? 'bank';
            $detailsJson = $pm['account_details'] ?? '{}';

            // 5. Insert payout request
            $ins = mysqli_prepare($this->conn, 
                "INSERT INTO affiliate_payouts (affiliate_id, amount, status, payout_method_type, payout_method_details, payout_method_id, requested_at) 
                 VALUES (?, ?, 'requested', ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "idssi", $affiliateId, $amount, $methodType, $detailsJson, $payoutMethodId);
            mysqli_stmt_execute($ins);

            $payoutId = mysqli_insert_id($this->conn);

            mysqli_commit($this->conn);
            return [
                "success" => true,
                "payout_id" => $payoutId,
                "amount" => $amount,
                "remaining_balance" => $newBalance,
                "message" => "Payout request submitted successfully"
            ];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Admin approves a requested payout
     */
    public function approvePayout(int $payoutId, int $adminId): bool {
        $stmt = mysqli_prepare($this->conn, "UPDATE affiliate_payouts SET status = 'approved' WHERE id = ? AND status = 'requested'");
        mysqli_stmt_bind_param($stmt, "i", $payoutId);
        $ok = mysqli_stmt_execute($stmt);

        if ($ok && mysqli_stmt_affected_rows($stmt) > 0) {
            $this->logAdminAction($adminId, 'AFFILIATE_PAYOUT', 'APPROVE', $payoutId);
            
            $q = mysqli_query($this->conn, "SELECT affiliate_id, amount FROM affiliate_payouts WHERE id = $payoutId LIMIT 1");
            if ($pRow = mysqli_fetch_assoc($q)) {
                $amtFormatted = number_format((float)$pRow['amount'], 2);
                sendAffiliateNotification(
                    $this->conn,
                    (int)$pRow['affiliate_id'],
                    'payout',
                    'Payout Request Approved',
                    "Your withdrawal request of ₹{$amtFormatted} has been approved and scheduled for bank transfer.",
                    '/payouts'
                );
            }
            return true;
        }
        return false;
    }

    /**
     * Admin marks an approved payout as paid with bank UTR transaction reference
     */
    public function markPaid(int $payoutId, string $txnRef, int $adminId): bool {
        mysqli_begin_transaction($this->conn);

        try {
            $stmt = mysqli_prepare($this->conn, "SELECT affiliate_id, amount FROM affiliate_payouts WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $payoutId);
            mysqli_stmt_execute($stmt);
            $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$p) {
                mysqli_commit($this->conn);
                return false;
            }

            // Update payout status
            $upd = mysqli_prepare($this->conn, "UPDATE affiliate_payouts SET status = 'paid', transaction_reference = ?, paid_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $txnRef, $payoutId);
            mysqli_stmt_execute($upd);

            // Update affiliate lifetime earnings & stamp last_settled_at watermark for fresh calculation
            $affUpd = mysqli_prepare($this->conn, "UPDATE affiliates SET lifetime_earnings = lifetime_earnings + ?, last_settled_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($affUpd, "di", $p['amount'], $p['affiliate_id']);
            mysqli_stmt_execute($affUpd);

            // Record snapshot in affiliate_settlements (fault-tolerant)
            try {
                $cycleLabel = "Payout Settlement UTR: " . ($txnRef ?: ('PAY-' . $payoutId));
                $payoutAmt = (float)$p['amount'];
                $affId = (int)$p['affiliate_id'];
                $settIns = @mysqli_prepare($this->conn, 
                    "INSERT INTO affiliate_settlements (affiliate_id, cycle_label, total_payout, status, settled_by, settled_at) 
                     VALUES (?, ?, ?, 'paid', 'admin', NOW())");
                if ($settIns) {
                    mysqli_stmt_bind_param($settIns, "isd", $affId, $cycleLabel, $payoutAmt);
                    mysqli_stmt_execute($settIns);
                    mysqli_stmt_close($settIns);
                }
            } catch (\Throwable $exSett) {
                // Optional reporting table safeguard
            }

            try {
                $this->logAdminAction($adminId, 'AFFILIATE_PAYOUT', 'PAY', $payoutId);
            } catch (\Throwable $exLog) {}

            mysqli_commit($this->conn);

            $amtFormatted = number_format((float)$p['amount'], 2);
            sendAffiliateNotification(
                $this->conn,
                (int)$p['affiliate_id'],
                'payout',
                'Payout Processed (Paid)',
                "Your withdrawal of ₹{$amtFormatted} has been successfully paid (UTR / Ref: {$txnRef}).",
                '/payouts'
            );

            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Admin rejects payout and refunds balance back to affiliate
     */
    public function rejectPayout(int $payoutId, string $reason, int $adminId): bool {
        mysqli_begin_transaction($this->conn);

        try {
            $stmt = mysqli_prepare($this->conn, "SELECT affiliate_id, amount, status FROM affiliate_payouts WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $payoutId);
            mysqli_stmt_execute($stmt);
            $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$p || in_array($p['status'], ['paid', 'rejected'])) {
                mysqli_commit($this->conn);
                return false; // Already paid or rejected
            }

            // Mark rejected
            $upd = mysqli_prepare($this->conn, "UPDATE affiliate_payouts SET status = 'rejected', rejection_reason = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $reason, $payoutId);
            mysqli_stmt_execute($upd);

            // Refund balance to affiliate
            $refUpd = mysqli_prepare($this->conn, "UPDATE affiliates SET available_balance = available_balance + ? WHERE id = ?");
            mysqli_stmt_bind_param($refUpd, "di", $p['amount'], $p['affiliate_id']);
            mysqli_stmt_execute($refUpd);

            $this->logAdminAction($adminId, 'AFFILIATE_PAYOUT', 'REJECT', $payoutId);

            mysqli_commit($this->conn);

            $amtFormatted = number_format((float)$p['amount'], 2);
            sendAffiliateNotification(
                $this->conn,
                (int)$p['affiliate_id'],
                'payout',
                'Payout Request Rejected',
                "Your withdrawal request of ₹{$amtFormatted} was rejected and balance has been refunded. Reason: {$reason}",
                '/payouts'
            );

            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    private function logAdminAction($adminId, $group, $type, $targetId) {
        try {
            $log = @mysqli_prepare($this->conn, 
                "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, ip_address) 
                 VALUES (?, ?, ?, ?, 'affiliate_payout', ?)");
            if ($log) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                mysqli_stmt_bind_param($log, "issis", $adminId, $group, $type, $targetId, $ip);
                mysqli_stmt_execute($log);
                mysqli_stmt_close($log);
            }
        } catch (\Throwable $ex) {
            // Fault-tolerant logger safeguard
        }
    }
}
