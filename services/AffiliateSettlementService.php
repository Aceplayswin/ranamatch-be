<?php
/**
 * AffiliateSettlementService
 * Handles settlement calculations and watermarking for affiliates.
 * When a settlement is completed, `last_settled_at` is stamped as the watermark,
 * ensuring subsequent calculations start fresh from that exact moment.
 */

class AffiliateSettlementService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get the active/current unsettled cycle stats for an affiliate (or all affiliates if null)
     * Calculation starts from `last_settled_at` up to NOW()
     */
    public function getUnsettledCycleMetrics(int $affiliateId): array {
        // 1. Fetch affiliate details & watermark
        $stmt = mysqli_prepare($this->conn, "SELECT id, affiliate_code, full_name, last_settled_at, available_balance, pending_balance, lifetime_earnings, deal_type, revshare_pct, sub_override_pct FROM affiliates WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $affiliateId);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$aff) {
            return ["success" => false, "message" => "Affiliate not found"];
        }

        $watermark = $aff['last_settled_at'] ? $aff['last_settled_at'] : '2000-01-01 00:00:00';
        $now = date('Y-m-d H:i:s');

        // 2. Fetch referred player user IDs (Direct + Sub-affiliate referred)
        $playerSql = "SELECT ar.id AS referral_id, ar.user_id, ar.affiliate_id, u.tbl_uniq_id, u.tbl_user_name, u.tbl_balance,
                             af.id AS direct_aff_id, af.affiliate_code AS direct_aff_code, af.full_name AS direct_aff_name,
                             af.parent_id
                      FROM affiliate_referrals ar
                      JOIN tblusersdata u ON (u.id = ar.user_id OR u.tbl_uniq_id = ar.user_id)
                      JOIN affiliates af ON af.id = ar.affiliate_id
                      WHERE ar.affiliate_id = ? OR af.parent_id = ?";
        
        $pStmt = mysqli_prepare($this->conn, $playerSql);
        mysqli_stmt_bind_param($pStmt, "ii", $affiliateId, $affiliateId);
        mysqli_stmt_execute($pStmt);
        $playerRes = mysqli_stmt_get_result($pStmt);

        $playerIds = [];
        $playerUniqIds = [];
        $referralIds = [];
        $playerDetails = [];

        while ($p = mysqli_fetch_assoc($playerRes)) {
            $playerIds[] = (int)$p['user_id'];
            $playerUniqIds[] = "'" . mysqli_real_escape_string($this->conn, $p['tbl_uniq_id']) . "'";
            $referralIds[] = (int)$p['referral_id'];
            $playerDetails[$p['tbl_uniq_id']] = $p;
        }

        $summary = [
            "affiliate_id" => $affiliateId,
            "affiliate_code" => $aff['affiliate_code'],
            "full_name" => $aff['full_name'],
            "watermark_start" => $aff['last_settled_at'],
            "watermark_display" => $aff['last_settled_at'] ? date('d M Y, h:i A', strtotime($aff['last_settled_at'])) : 'Account Inception',
            "period_end" => $now,
            "total_players" => count($playerDetails),
            "total_bets" => 0.00,
            "total_wins" => 0.00,
            "total_losses" => 0.00,
            "total_deposits" => 0.00,
            "total_withdrawals" => 0.00,
            "total_ggr" => 0.00,
            "total_ngr" => 0.00,
            "direct_commission" => 0.00,
            "sub_override_commission" => 0.00,
            "total_unsettled_commission" => 0.00
        ];

        if (empty($playerUniqIds)) {
            return ["success" => true, "data" => $summary];
        }

        $uniqListStr = implode(",", $playerUniqIds);

        // 3. Bets, Wins, Losses from watermark onwards
        // Note: tblmatchplayed uses `created_at` (datetime) for reliable date comparison
        $betSql = "SELECT 
                    COALESCE(SUM(tbl_match_cost), 0) AS total_bets,
                    COALESCE(SUM(tbl_match_profit), 0) AS total_wins,
                    COALESCE(SUM(CASE WHEN tbl_match_profit = 0 THEN tbl_match_cost 
                                      WHEN tbl_match_profit < tbl_match_cost THEN (tbl_match_cost - tbl_match_profit) 
                                      ELSE 0 END), 0) AS total_losses
                   FROM tblmatchplayed 
                   WHERE tbl_user_id IN ($uniqListStr) 
                     AND created_at >= '$watermark'";
        $bRes = mysqli_query($this->conn, $betSql);
        if ($bRow = mysqli_fetch_assoc($bRes)) {
            $summary['total_bets'] = (float)$bRow['total_bets'];
            $summary['total_wins'] = (float)$bRow['total_wins'];
            $summary['total_losses'] = (float)$bRow['total_losses'];
        }

        // 4. Deposits & Withdrawals from watermark onwards
        // Note: tblusersrecharge.tbl_time_stamp is varchar in 'DD-MM-YYYY HH:MM AM/PM' format
        //       We use STR_TO_DATE() to convert it for reliable comparison
        $depSql = "SELECT COALESCE(SUM(tbl_recharge_amount), 0) AS total_deposits 
                   FROM tblusersrecharge 
                   WHERE tbl_user_id IN ($uniqListStr) 
                     AND tbl_request_status = 'success' 
                     AND (STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= '$watermark' OR tbl_time_stamp IS NULL)";
        $dRes = mysqli_query($this->conn, $depSql);
        if ($dRow = mysqli_fetch_assoc($dRes)) {
            $summary['total_deposits'] = (float)$dRow['total_deposits'];
        }

        // Note: tbluserswithdraw.tbl_time_stamp is also varchar in same format
        $wdSql = "SELECT COALESCE(SUM(tbl_withdraw_amount), 0) AS total_withdrawals 
                  FROM tbluserswithdraw 
                  WHERE tbl_user_id IN ($uniqListStr) 
                    AND tbl_request_status = 'success' 
                    AND (STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= '$watermark' OR tbl_time_stamp IS NULL)";
        $wRes = mysqli_query($this->conn, $wdSql);
        if ($wRow = mysqli_fetch_assoc($wRes)) {
            $summary['total_withdrawals'] = (float)$wRow['total_withdrawals'];
        }

        // GGR = Bets - Wins (Platform Gross Win)
        $summary['total_ggr'] = max(0.00, $summary['total_bets'] - $summary['total_wins']);
        // NGR = GGR - Deductions (bonus/fees approx 15% platform deduction standard or pure GGR)
        $summary['total_ngr'] = $summary['total_ggr'];

        // 5. Commission earned in commission ledger from watermark onwards
        $commSql = "SELECT 
                        COALESCE(SUM(CASE WHEN entry_type != 'override' THEN amount ELSE 0 END), 0) AS direct_comm,
                        COALESCE(SUM(CASE WHEN entry_type = 'override' THEN amount ELSE 0 END), 0) AS override_comm,
                        COALESCE(SUM(amount), 0) AS total_comm
                    FROM affiliate_commission_ledger
                    WHERE affiliate_id = $affiliateId
                      AND created_at >= '$watermark'";
        $cRes = mysqli_query($this->conn, $commSql);
        if ($cRow = mysqli_fetch_assoc($cRes)) {
            $summary['direct_commission'] = (float)$cRow['direct_comm'];
            $summary['sub_override_commission'] = (float)$cRow['override_comm'];
            $summary['total_unsettled_commission'] = (float)$cRow['total_comm'];
        }

        return ["success" => true, "data" => $summary];
    }

    /**
     * Execute settlement for an affiliate:
     * 1. Calculates the current unsettled metrics
     * 2. Snapshots into affiliate_settlements
     * 3. Stamps last_settled_at = NOW() (Watermark update)
     * 4. Credits unsettled commissions to available_balance (if not already credited)
     */
    public function executeSettlement(int $affiliateId, string $cycleLabel = '', string $settledBy = 'admin'): array {
        mysqli_begin_transaction($this->conn);

        try {
            $metricsResult = $this->getUnsettledCycleMetrics($affiliateId);
            if (!$metricsResult['success']) {
                mysqli_rollback($this->conn);
                return $metricsResult;
            }

            $m = $metricsResult['data'];
            $now = date('Y-m-d H:i:s');
            $startTime = $m['watermark_start'] ? $m['watermark_start'] : $m['period_end'];
            $label = $cycleLabel ?: 'Settlement Cycle ending ' . date('d M Y');

            $totalPayout = $m['total_unsettled_commission'];

            // Insert settlement snapshot
            $ins = mysqli_prepare($this->conn, 
                "INSERT INTO affiliate_settlements 
                 (affiliate_id, cycle_label, start_time, end_time, total_bets, total_wins, total_losses, 
                  total_deposits, total_withdrawals, total_ggr, total_ngr, commission_amount, 
                  sub_override_amount, total_payout, status, settled_by, settled_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'settled', ?, NOW())");
            
            mysqli_stmt_bind_param($ins, "isssdddddddddds", 
                $affiliateId,
                $label,
                $startTime,
                $now,
                $m['total_bets'],
                $m['total_wins'],
                $m['total_losses'],
                $m['total_deposits'],
                $m['total_withdrawals'],
                $m['total_ggr'],
                $m['total_ngr'],
                $m['direct_commission'],
                $m['sub_override_commission'],
                $totalPayout,
                $settledBy
            );
            mysqli_stmt_execute($ins);
            $settlementId = mysqli_insert_id($this->conn);

            // Stamp watermark on affiliate record so next calculation starts fresh from NOW()
            $upd = mysqli_prepare($this->conn, "UPDATE affiliates SET last_settled_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, "i", $affiliateId);
            mysqli_stmt_execute($upd);

            mysqli_commit($this->conn);

            return [
                "success" => true,
                "settlement_id" => $settlementId,
                "watermark_reset_to" => $now,
                "message" => "Settlement completed successfully. Fresh calculation started from " . date('d M Y, h:i A', strtotime($now))
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
