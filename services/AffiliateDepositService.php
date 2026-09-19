<?php
/**
 * AffiliateDepositService.php
 * Handles First Time Deposit (FTD) attribution and CPA commission payout logic
 * whenever a user account completes a successful deposit/recharge.
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}

class AffiliateDepositService {

    /**
     * Call this function whenever a user's deposit is completed/approved.
     * 
     * @param mysqli $conn
     * @param string|int $userIdentifier User's tbl_uniq_id or integer ID
     * @param float $amount Amount deposited
     * @return bool True if FTD was newly recorded, false otherwise
     */
    public static function checkAndUpdateFTD($conn, $userIdentifier, $amount) {
        if (!$conn || empty($userIdentifier) || (float)$amount <= 0) {
            return false;
        }

        $userStr = (string)$userIdentifier;
        $depositAmt = (float)$amount;

        // 1. Resolve user integer ID and tbl_uniq_id from tblusersdata
        $uStmt = mysqli_prepare($conn, "SELECT id, tbl_uniq_id FROM tblusersdata WHERE id = ? OR tbl_uniq_id = ? LIMIT 1");
        mysqli_stmt_bind_param($uStmt, "ss", $userStr, $userStr);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);
        $uRow = mysqli_fetch_assoc($uRes);

        if (!$uRow) {
            return false;
        }

        $userId = (int)$uRow['id'];
        $userUniqId = (string)$uRow['tbl_uniq_id'];

        // 2. Find referral record in affiliate_referrals matching either ID
        $rStmt = mysqli_prepare($conn, "SELECT id, affiliate_id, link_id, first_deposit_at, status FROM affiliate_referrals WHERE user_id = ? OR user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($rStmt, "is", $userId, $userUniqId);
        mysqli_stmt_execute($rStmt);
        $rRes = mysqli_stmt_get_result($rStmt);
        $rRow = mysqli_fetch_assoc($rRes);

        if (!$rRow) {
            return false;
        }

        $refId = (int)$rRow['id'];
        $affId = (int)$rRow['affiliate_id'];

        // 3. Check if first_deposit_at is NULL (meaning this is their First Time Deposit!)
        if (empty($rRow['first_deposit_at'])) {
            // Update referral status to 'ftd' and record deposit timestamp + amount
            $updRef = mysqli_prepare($conn, "UPDATE affiliate_referrals SET first_deposit_at = NOW(), first_deposit_amount = ?, status = 'ftd' WHERE id = ?");
            mysqli_stmt_bind_param($updRef, "di", $depositAmt, $refId);
            mysqli_stmt_execute($updRef);

            // 4. Handle CPA Commission eligibility
            $affStmt = mysqli_prepare($conn, "SELECT id, deal_type, cpa_amount, status FROM affiliates WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($affStmt, "i", $affId);
            mysqli_stmt_execute($affStmt);
            $affRes = mysqli_stmt_get_result($affStmt);
            $affRow = mysqli_fetch_assoc($affRes);

            if ($affRow && in_array($affRow['status'], ['approved', 'active'])) {
                $dealType = $affRow['deal_type'];
                $cpaAmount = (float)$affRow['cpa_amount'];

                // Check min deposit threshold from program_settings
                $minCpaDeposit = 100.00;
                $settRes = mysqli_query($conn, "SELECT affiliate_cpa_min_deposit_threshold FROM program_settings WHERE id = 1");
                if ($settRes && $settRow = mysqli_fetch_assoc($settRes)) {
                    $minCpaDeposit = (float)($settRow['affiliate_cpa_min_deposit_threshold'] ?? 100.00);
                }

                if (in_array($dealType, ['cpa', 'hybrid']) && $cpaAmount > 0 && $depositAmt >= $minCpaDeposit) {
                    // Mark referral as CPA qualified
                    mysqli_query($conn, "UPDATE affiliate_referrals SET is_cpa_qualified = 1 WHERE id = {$refId}");

                    // Check if ledger entry already exists to prevent duplicate payout
                    $chkL = mysqli_query($conn, "SELECT id FROM affiliate_commission_ledger WHERE affiliate_id = {$affId} AND entry_type = 'cpa' AND base_amount = {$depositAmt} LIMIT 1");
                    if ($chkL && mysqli_num_rows($chkL) === 0) {
                        $linkId = (int)($rRow['link_id'] ?? 0);
                        // Insert CPA commission into ledger
                        $insL = mysqli_prepare($conn, 
                            "INSERT INTO affiliate_commission_ledger (affiliate_id, entry_type, base_kind, base_amount, rate, amount, status, link_id, referral_id, created_at) 
                             VALUES (?, 'cpa', 'deposits', ?, ?, ?, 'approved', ?, ?, NOW())"
                        );
                        mysqli_stmt_bind_param($insL, "idddii", $affId, $depositAmt, $cpaAmount, $cpaAmount, $linkId, $refId);
                        mysqli_stmt_execute($insL);

                        // Update Affiliate balances
                        $updAff = mysqli_prepare($conn, "UPDATE affiliates SET available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ? WHERE id = ?");
                        mysqli_stmt_bind_param($updAff, "ddi", $cpaAmount, $cpaAmount, $affId);
                        mysqli_stmt_execute($updAff);

                        // Credit Sub-Affiliate Override Commission to Parent Affiliate if applicable
                        $pStmt = mysqli_prepare($conn, "SELECT parent_id FROM affiliates WHERE id = ? LIMIT 1");
                        mysqli_stmt_bind_param($pStmt, "i", $affId);
                        mysqli_stmt_execute($pStmt);
                        $pRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));

                        if ($pRow && !empty($pRow['parent_id'])) {
                            $parentId = (int)$pRow['parent_id'];
                            $parentStmt = mysqli_prepare($conn, "SELECT sub_override_pct FROM affiliates WHERE id = ? AND status IN ('approved','active') LIMIT 1");
                            mysqli_stmt_bind_param($parentStmt, "i", $parentId);
                            mysqli_stmt_execute($parentStmt);
                            $parentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

                            if ($parentRow && (float)$parentRow['sub_override_pct'] > 0) {
                                $overridePct = (float)$parentRow['sub_override_pct'];
                                $overrideComm = round($cpaAmount * ($overridePct / 100.0), 2);

                                if ($overrideComm > 0) {
                                    $oIns = mysqli_prepare($conn, 
                                        "INSERT INTO affiliate_commission_ledger (affiliate_id, entry_type, base_kind, base_amount, rate, amount, status, source_affiliate_id, link_id, referral_id, created_at) 
                                         VALUES (?, 'override', 'deposits', ?, ?, ?, 'approved', ?, ?, ?, NOW())"
                                    );
                                    mysqli_stmt_bind_param($oIns, "idddiii", $parentId, $cpaAmount, $overridePct, $overrideComm, $affId, $linkId, $refId);
                                    mysqli_stmt_execute($oIns);

                                    $oUpd = mysqli_prepare($conn, "UPDATE affiliates SET available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ? WHERE id = ?");
                                    mysqli_stmt_bind_param($oUpd, "ddi", $overrideComm, $overrideComm, $parentId);
                                    mysqli_stmt_execute($oUpd);
                                }
                            }
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Backfill / Sync function: Checks all successful recharges in DB
     * and updates any pending affiliate_referrals that haven't been marked as FTD.
     */
    public static function syncExistingFTDs($conn) {
        $sql = "SELECT r.tbl_user_id, r.tbl_recharge_amount
                FROM tblusersrecharge r
                WHERE r.tbl_request_status = 'success'
                ORDER BY r.id ASC";
        $res = mysqli_query($conn, $sql);
        if (!$res) return 0;

        $count = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            if (self::checkAndUpdateFTD($conn, $row['tbl_user_id'], (float)$row['tbl_recharge_amount'])) {
                $count++;
            }
        }
        return $count;
    }
}
