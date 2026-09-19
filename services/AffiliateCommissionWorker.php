<?php
/**
 * AffiliateCommissionWorker
 * Executed daily by automated cron worker or admin manual trigger.
 * Calculates NGR RevShare & Sub-Affiliate Override commissions.
 * Uses pure mysqli.
 */

class AffiliateCommissionWorker {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Run daily commission batch job for a specific date (defaults to yesterday)
     */
    public function runDailyCommissionJob($periodDate = null): array {
        if (!$periodDate) {
            $periodDate = date('Y-m-d', strtotime('-1 day'));
        }

        mysqli_begin_transaction($this->conn);

        try {
            // 1. Fetch NGR per referred user for settled bets on periodDate
            $sql = "SELECT 
                        ar.id AS referral_id,
                        ar.affiliate_id,
                        af.deal_type,
                        af.revshare_pct,
                        af.cpa_amount,
                        af.parent_id AS parent_affiliate_id,
                        SUM(b.total_bets) AS gross_bets,
                        SUM(b.total_wins) AS gross_wins
                    FROM affiliate_referrals ar
                    JOIN affiliates af ON af.id = ar.affiliate_id
                    JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
                    JOIN (
                        SELECT tbl_user_id AS match_uid, SUM(tbl_match_cost) AS total_bets, SUM(tbl_match_profit) AS total_wins 
                        FROM tblmatchplayed 
                        WHERE tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win') 
                          AND (
                            (tbl_updated_at >= CONCAT(?, ' 00:00:00') AND tbl_updated_at <= CONCAT(?, ' 23:59:59'))
                            OR (created_at >= CONCAT(?, ' 00:00:00') AND created_at <= CONCAT(?, ' 23:59:59'))
                          )
                        GROUP BY tbl_user_id
                        UNION ALL
                        SELECT CAST(user_id AS CHAR) AS match_uid, SUM(bet_amount) AS total_bets, SUM(win_amount) AS total_wins 
                        FROM sports_bets 
                        WHERE status IN ('won', 'lost') AND settled_at >= CONCAT(?, ' 00:00:00') AND settled_at <= CONCAT(?, ' 23:59:59')
                        GROUP BY user_id
                        UNION ALL
                        SELECT CAST(user_id AS CHAR) AS match_uid, SUM(bet_amount) AS total_bets, SUM(win_amount) AS total_wins 
                        FROM casino_bets 
                        WHERE status IN ('won', 'lost') AND settled_at >= CONCAT(?, ' 00:00:00') AND settled_at <= CONCAT(?, ' 23:59:59')
                        GROUP BY user_id
                    ) b ON (b.match_uid = u.tbl_uniq_id OR b.match_uid = CAST(u.id AS CHAR) OR b.match_uid = CAST(ar.user_id AS CHAR))
                    WHERE af.status IN ('approved', 'active')
                    GROUP BY ar.id, ar.affiliate_id, af.deal_type, af.revshare_pct, af.cpa_amount, af.parent_id";

            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssss", $periodDate, $periodDate, $periodDate, $periodDate, $periodDate, $periodDate, $periodDate, $periodDate);
            mysqli_stmt_execute($stmt);
            $results = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

            $totalGenerated = 0.0;
            $recordsProcessed = count($results);

            foreach ($results as $row) {
                $ggr = (float)$row['gross_bets'] - (float)$row['gross_wins'];
                $platformFee = $ggr > 0 ? ($ggr * 0.15) : 0.0; // 15% platform admin fee
                $ngr = max(0, $ggr - $platformFee);

                if (in_array($row['deal_type'], ['revenue_share', 'hybrid'])) {
                    $commission = $ngr * ((float)$row['revshare_pct'] / 100.0);

                    if ($commission > 0) {
                        // Check if ledger entry already exists for this affiliate, referral & period
                        $chk = mysqli_prepare($this->conn, "SELECT id, amount, base_amount FROM affiliate_commission_ledger WHERE affiliate_id = ? AND referral_id = ? AND entry_type = 'rev_share' AND DATE(created_at) = ?");
                        mysqli_stmt_bind_param($chk, "iis", $row['affiliate_id'], $row['referral_id'], $periodDate);
                        mysqli_stmt_execute($chk);
                        $chkRes = mysqli_stmt_get_result($chk);

                        if (mysqli_num_rows($chkRes) === 0) {
                            // First run of the day: Insert new RevShare entry into Ledger
                            $ins = mysqli_prepare($this->conn, 
                                "INSERT INTO affiliate_commission_ledger (affiliate_id, referral_id, entry_type, base_kind, base_amount, rate, amount, status, created_at) 
                                 VALUES (?, ?, 'rev_share', 'NGR', ?, ?, ?, 'approved', NOW())");
                            mysqli_stmt_bind_param($ins, "iiddd", $row['affiliate_id'], $row['referral_id'], $ngr, $row['revshare_pct'], $commission);
                            mysqli_stmt_execute($ins);

                            // Update Affiliate Balances
                            $upd = mysqli_prepare($this->conn, "UPDATE affiliates SET available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ? WHERE id = ?");
                            mysqli_stmt_bind_param($upd, "ddi", $commission, $commission, $row['affiliate_id']);
                            mysqli_stmt_execute($upd);

                            $totalGenerated += $commission;

                            // Check Sub-Affiliate Override for Parent Affiliate
                            if (!empty($row['parent_affiliate_id'])) {
                                $parentStmt = mysqli_prepare($this->conn, "SELECT sub_override_pct FROM affiliates WHERE id = ? AND status IN ('approved','active')");
                                mysqli_stmt_bind_param($parentStmt, "i", $row['parent_affiliate_id']);
                                mysqli_stmt_execute($parentStmt);
                                $parentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

                                if ($parentRow && (float)$parentRow['sub_override_pct'] > 0) {
                                    $overrideComm = $commission * ((float)$parentRow['sub_override_pct'] / 100.0);
                                    
                                    $oIns = mysqli_prepare($this->conn, 
                                        "INSERT INTO affiliate_commission_ledger (affiliate_id, entry_type, base_kind, base_amount, rate, amount, status, source_affiliate_id, created_at) 
                                         VALUES (?, 'override', 'NGR', ?, ?, ?, 'pending', ?, NOW())");
                                    mysqli_stmt_bind_param($oIns, "idddi", $row['parent_affiliate_id'], $commission, $parentRow['sub_override_pct'], $overrideComm, $row['affiliate_id']);
                                    mysqli_stmt_execute($oIns);

                                    $oUpd = mysqli_prepare($this->conn, "UPDATE affiliates SET pending_balance = pending_balance + ? WHERE id = ?");
                                    mysqli_stmt_bind_param($oUpd, "di", $overrideComm, $row['parent_affiliate_id']);
                                    mysqli_stmt_execute($oUpd);
                                }
                            }
                        } else {
                            // Entry already exists for today: check for newly placed bets (incremental difference)
                            $existingRow = mysqli_fetch_assoc($chkRes);
                            $existingAmount = (float)$existingRow['amount'];
                            $diff = $commission - $existingAmount;

                            if ($diff > 0.009) {
                                // Update existing ledger entry with cumulative NGR and commission
                                $updLedger = mysqli_prepare($this->conn, 
                                    "UPDATE affiliate_commission_ledger 
                                     SET base_amount = ?, amount = ? 
                                     WHERE id = ?");
                                mysqli_stmt_bind_param($updLedger, "ddi", $ngr, $commission, $existingRow['id']);
                                mysqli_stmt_execute($updLedger);

                                // Credit ONLY the new additional profit to affiliate available balance
                                $upd = mysqli_prepare($this->conn, 
                                    "UPDATE affiliates 
                                     SET available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ? 
                                     WHERE id = ?");
                                mysqli_stmt_bind_param($upd, "ddi", $diff, $diff, $row['affiliate_id']);
                                mysqli_stmt_execute($upd);

                                $totalGenerated += $diff;

                                // Check Sub-Affiliate Override for incremental difference
                                if (!empty($row['parent_affiliate_id'])) {
                                    $parentStmt = mysqli_prepare($this->conn, "SELECT sub_override_pct FROM affiliates WHERE id = ? AND status IN ('approved','active')");
                                    mysqli_stmt_bind_param($parentStmt, "i", $row['parent_affiliate_id']);
                                    mysqli_stmt_execute($parentStmt);
                                    $parentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));

                                    if ($parentRow && (float)$parentRow['sub_override_pct'] > 0) {
                                        $overrideDiff = $diff * ((float)$parentRow['sub_override_pct'] / 100.0);
                                        $oUpd = mysqli_prepare($this->conn, "UPDATE affiliates SET pending_balance = pending_balance + ? WHERE id = ?");
                                        mysqli_stmt_bind_param($oUpd, "di", $overrideDiff, $row['parent_affiliate_id']);
                                        mysqli_stmt_execute($oUpd);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            mysqli_commit($this->conn);

            return [
                "success" => true,
                "period_processed" => $periodDate,
                "total_commission_generated" => $totalGenerated,
                "records_processed" => $recordsProcessed
            ];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }
}
