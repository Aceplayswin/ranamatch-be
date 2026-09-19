<?php
/**
 * BetSettlementService
 * Handles real-time Sports and Casino bet triggers for Agents:
 * - Exposure locking on bet placement
 * - Exposure release, Turnover Commission, and Net P&L settlement on bet result
 * - Sync to player_bets table for live reports and dashboard analytics
 * Uses pure mysqli.
 */

class BetSettlementService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * 1. TRIGGER ON BET PLACED (PENDING STATE)
     * Increases `exposed_credit` across all upline agents in the hierarchy tree,
     * and records the pending bet in player_bets.
     * 
     * @param string|int $userId Player unique ID (e.g. 'USER_100011') or numeric ID in tblusersdata
     * @param float $betAmount Bet stake amount
     * @param string|int|null $betRef Bet reference identifier
     * @param array $extra Optional metadata (event_name, market_name, selection_name, odds, game_type, etc.)
     */
    public function onBetPlaced($userId, float $betAmount, $betRef = null, array $extra = []): bool {
        $playerInfo = $this->getPlayerAndAgentInfo($userId);
        if (!$playerInfo || !$playerInfo['agent_id']) {
            return false; // Player has no assigned agent
        }

        $agentId = (int)$playerInfo['agent_id'];
        $numericUserId = (int)$playerInfo['user_id'];
        $refStr = (string)($betRef ?? ('BET-P-' . uniqid()));

        // Increment exposed credit for all ancestors in agent_tree
        $sql = "UPDATE agents 
                SET exposed_credit = exposed_credit + ? 
                WHERE id IN (SELECT ancestor_id FROM agent_tree WHERE descendant_id = ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "di", $betAmount, $agentId);
        $ok = mysqli_stmt_execute($stmt);

        // Record in player_bets
        $gameType = $extra['game_type'] ?? 'sports';
        $eventName = $extra['event_name'] ?? 'Cricket Fixture';
        $marketName = $extra['market_name'] ?? 'Match Odds';
        $selectionName = $extra['selection_name'] ?? 'Team 1';
        $side = $extra['side'] ?? 'back';
        $odds = (float)($extra['odds'] ?? 1.95);
        $liability = (float)($extra['liability'] ?? $betAmount);

        $pbStmt = mysqli_prepare($this->conn, 
            "INSERT INTO player_bets (
                bet_ref, agent_id, user_id, game_type, event_name, 
                market_name, selection_name, side, odds, stake, 
                liability, win_amount, pnl, status, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 'pending', NOW())");
        if ($pbStmt) {
            mysqli_stmt_bind_param($pbStmt, "siisssssddd", 
                $refStr, $agentId, $numericUserId, $gameType, $eventName, 
                $marketName, $selectionName, $side, $odds, $betAmount, $liability);
            mysqli_stmt_execute($pbStmt);
        }

        return $ok;
    }

    /**
     * 2. TRIGGER ON BET SETTLED (WON OR LOST STATE)
     * Unlocks exposed credit, calculates Turnover Commission, and settles Net P&L Profit/Loss split.
     * Updates/Inserts records in player_bets.
     * 
     * @param string|int $userId Player ID
     * @param string|int $betId Bet reference ID
     * @param float $betAmount Bet stake
     * @param float $winAmount Amount won by player (0 if lost)
     * @param string $status 'won' or 'lost'
     * @param array $extra Optional metadata
     */
    public function onBetSettled($userId, $betId, float $betAmount, float $winAmount, string $status, array $extra = []): bool {
        if (!in_array($status, ['won', 'lost'], true)) {
            return false;
        }

        mysqli_begin_transaction($this->conn);

        try {
            $playerInfo = $this->getPlayerAndAgentInfo($userId);
            if (!$playerInfo || !$playerInfo['agent_id']) {
                mysqli_commit($this->conn);
                return false;
            }

            $agentId = (int)$playerInfo['agent_id'];
            $numericUserId = (int)$playerInfo['user_id'];
            $betRefStr = (string)$betId;

            // Step A: Release Exposed Credit across tree
            $sql = "UPDATE agents 
                    SET exposed_credit = GREATEST(0, exposed_credit - ?) 
                    WHERE id IN (SELECT ancestor_id FROM agent_tree WHERE descendant_id = ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $betAmount, $agentId);
            mysqli_stmt_execute($stmt);

            // Step B & C: Calculate & Pay Turnover Commission & P&L Split across agent hierarchy tree
            // Direct agent (depth 0) gets their rate; uplines (depth > 0) earn the spread differential.
            $ancestorStmt = mysqli_prepare($this->conn, 
                "SELECT a.id, a.current_credit, a.turnover_commission_pct, a.partnership_pct, t.depth 
                 FROM agent_tree t
                 JOIN agents a ON t.ancestor_id = a.id
                 WHERE t.descendant_id = ?
                 ORDER BY t.depth ASC");
            mysqli_stmt_bind_param($ancestorStmt, "i", $agentId);
            mysqli_stmt_execute($ancestorStmt);
            $ancestorsRes = mysqli_stmt_get_result($ancestorStmt);

            $netResult = $betAmount - $winAmount; // Positive = player loss (agent profit), Negative = player win (agent loss)

            $prevTurnoverPct = 0.0;
            $prevPartnershipPct = 0.0;

            while ($agent = mysqli_fetch_assoc($ancestorsRes)) {
                $uplineAgentId = (int)$agent['id'];
                $currentCredit = (float)$agent['current_credit'];
                $turnoverCommPct = (float)$agent['turnover_commission_pct'];
                $partnershipPct = (float)$agent['partnership_pct'];
                $depth = (int)$agent['depth'];

                // Direct agent gets full rate; upline levels earn the spread differential
                if ($depth === 0) {
                    $effectiveTurnoverRate = $turnoverCommPct;
                    $effectivePartnershipRate = $partnershipPct;
                    $prevTurnoverPct = $turnoverCommPct;
                    $prevPartnershipPct = $partnershipPct;
                } else {
                    $effectiveTurnoverRate = max(0.0, $turnoverCommPct - $prevTurnoverPct);
                    $effectivePartnershipRate = max(0.0, $partnershipPct - $prevPartnershipPct);
                    $prevTurnoverPct = max($prevTurnoverPct, $turnoverCommPct);
                    $prevPartnershipPct = max($prevPartnershipPct, $partnershipPct);
                }

                $downlineLabel = !empty($playerInfo['agent_username']) ? $playerInfo['agent_username'] : ("Agent #" . $agentId);
                $transferringAgent = ($depth === 0) ? null : $agentId;

                // Turnover commission for this level
                $turnoverComm = $betAmount * ($effectiveTurnoverRate / 100.0);
                if ($turnoverComm > 0) {
                    $newCredit = $currentCredit + $turnoverComm;
                    $upd = mysqli_prepare($this->conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd, "di", $newCredit, $uplineAgentId);
                    mysqli_stmt_execute($upd);

                    $remark = ($depth === 0) 
                        ? "Turnover commission on bet #{$betId} (Rate: {$effectiveTurnoverRate}%)"
                        : "Downline turnover commission spread from {$downlineLabel} on bet #{$betId} (Spread: {$effectiveTurnoverRate}%)";

                    $ledger = mysqli_prepare($this->conn, 
                        "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) 
                         VALUES (?, ?, 'TURNOVER_COMMISSION', ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($ledger, "iiddds", $uplineAgentId, $transferringAgent, $turnoverComm, $currentCredit, $newCredit, $remark);
                    mysqli_stmt_execute($ledger);

                    $currentCredit = $newCredit;
                }

                // P&L Share for this level (Player Loss = positive profit for agent; Player Win = negative profit/loss for agent)
                $agentPnlShare = $netResult * ($effectivePartnershipRate / 100.0);
                if ($agentPnlShare != 0) {
                    $finalCredit = $currentCredit + $agentPnlShare;
                    $upd2 = mysqli_prepare($this->conn, "UPDATE agents SET current_credit = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd2, "di", $finalCredit, $uplineAgentId);
                    mysqli_stmt_execute($upd2);

                    $outcomeLabel = ($agentPnlShare >= 0) ? 'Profit' : 'Loss';
                    $remark2 = ($depth === 0)
                        ? "P&L settlement ({$outcomeLabel}) for bet #{$betId} (Share: {$effectivePartnershipRate}%)"
                        : "Downline P&L spread ({$outcomeLabel}) from {$downlineLabel} on bet #{$betId} (Spread: {$effectivePartnershipRate}%)";

                    $ledger2 = mysqli_prepare($this->conn, 
                        "INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, balance_before, balance_after, remark) 
                         VALUES (?, ?, 'PNL_SETTLEMENT', ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($ledger2, "iiddds", $uplineAgentId, $transferringAgent, $agentPnlShare, $currentCredit, $finalCredit, $remark2);
                    mysqli_stmt_execute($ledger2);
                }
            }

            // Step D: Sync/Update player_bets table
            $chkStmt = mysqli_prepare($this->conn, "SELECT id FROM player_bets WHERE bet_ref = ? LIMIT 1");
            mysqli_stmt_bind_param($chkStmt, "s", $betRefStr);
            mysqli_stmt_execute($chkStmt);
            $chkRes = mysqli_stmt_get_result($chkStmt);
            $existingPb = mysqli_fetch_assoc($chkRes);

            if ($existingPb) {
                // Update existing bet record
                $updPb = mysqli_prepare($this->conn, 
                    "UPDATE player_bets 
                     SET win_amount = ?, pnl = ?, status = ?, settled_at = NOW() 
                     WHERE id = ?");
                mysqli_stmt_bind_param($updPb, "ddsi", $winAmount, $netResult, $status, $existingPb['id']);
                mysqli_stmt_execute($updPb);
            } else {
                // Insert settled record directly
                $gameType = $extra['game_type'] ?? 'sports';
                $eventName = $extra['event_name'] ?? 'Cricket Fixture';
                $marketName = $extra['market_name'] ?? 'Match Odds';
                $selectionName = $extra['selection_name'] ?? 'Team 1';
                $side = $extra['side'] ?? 'back';
                $odds = (float)($extra['odds'] ?? 1.95);
                $liability = (float)($extra['liability'] ?? $betAmount);

                $insPb = mysqli_prepare($this->conn, 
                    "INSERT INTO player_bets (
                        bet_ref, agent_id, user_id, game_type, event_name, 
                        market_name, selection_name, side, odds, stake, 
                        liability, win_amount, pnl, status, created_at, settled_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if ($insPb) {
                    mysqli_stmt_bind_param($insPb, "siisssssddddss", 
                        $betRefStr, $agentId, $numericUserId, $gameType, $eventName, 
                        $marketName, $selectionName, $side, $odds, $betAmount, 
                        $liability, $winAmount, $netResult, $status);
                    mysqli_stmt_execute($insPb);
                }
            }

            mysqli_commit($this->conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Helper to look up numeric player user_id and assigned agent_id
     */
    public function getPlayerAndAgentInfo($userId): ?array {
        $stmt = mysqli_prepare($this->conn, "SELECT id, tbl_joined_under FROM tblusersdata WHERE tbl_uniq_id = ? OR id = ? LIMIT 1");
        $userIdStr = (string)$userId;
        $userIdInt = (int)$userId;
        mysqli_stmt_bind_param($stmt, "si", $userIdStr, $userIdInt);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);

        if (!$row || empty($row['tbl_joined_under'])) {
            return null;
        }

        $agentCode = trim($row['tbl_joined_under']);
        $stmt2 = mysqli_prepare($this->conn, "SELECT id, username, name FROM agents WHERE agent_code = ? OR username = ? OR id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "ssi", $agentCode, $agentCode, $agentCode);
        mysqli_stmt_execute($stmt2);
        $agentRes = mysqli_stmt_get_result($stmt2);
        $agentRow = mysqli_fetch_assoc($agentRes);

        if (!$agentRow) {
            return null;
        }

        return [
            'user_id' => (int)$row['id'],
            'agent_id' => (int)$agentRow['id'],
            'agent_username' => $agentRow['username'] ?? '',
            'agent_name' => $agentRow['name'] ?? ''
        ];
    }
}
