<?php
/**
 * AgentSettlementService
 * Manages the automated settlement cycle (Daily, Weekly Monday, Bi-Weekly, Monthly) for Agents:
 * - Calculates net P&L and turnover commission over the configured period
 * - Handles negative carryover rules
 * - Unlocks safe net commission into `withdrawable_profit`
 * - Logs audit records in `agent_settlements` and `agent_credit_ledger`
 * Uses pure mysqli.
 */

class AgentSettlementService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Run the settlement calculation for the configured or custom period
     * 
     * @param string|null $overrideCycle 'daily', 'weekly_monday', 'bi_weekly', 'monthly'
     * @param string|null $customStartDate YYYY-MM-DD
     * @param string|null $customEndDate YYYY-MM-DD
     * @return array Summary of processed agents
     */
    public function runSettlementCycle($overrideCycle = null, $customStartDate = null, $customEndDate = null): array {
        // 1. Fetch system settlement rules
        $settRes = mysqli_query($this->conn, "SELECT agent_settlement_cycle, agent_minimum_payout, agent_negative_carryover FROM program_settings WHERE id = 1");
        $settings = mysqli_fetch_assoc($settRes);

        $cycle = $overrideCycle ?: ($settings['agent_settlement_cycle'] ?? 'weekly_monday');
        $minPayout = (float)($settings['agent_minimum_payout'] ?? 1000.00);
        $negativeCarryover = (int)($settings['agent_negative_carryover'] ?? 1);

        // 2. Determine period boundaries
        if ($customStartDate && $customEndDate) {
            $startDate = $customStartDate . " 00:00:00";
            $endDate = $customEndDate . " 23:59:59";
            $periodLabel = "Custom (" . $customStartDate . " to " . $customEndDate . ")";
        } else {
            $bounds = $this->calculateCycleBounds($cycle);
            $startDate = $bounds['start'];
            $endDate = $bounds['end'];
            $periodLabel = $bounds['label'];
        }

        mysqli_begin_transaction($this->conn);

        try {
            // 3. Fetch all active agents
            $agentRes = mysqli_query($this->conn, "SELECT id, agent_code, username, name, partnership_pct, turnover_commission_pct, current_credit, withdrawable_profit, cycle_net_pnl FROM agents WHERE status IN ('active', 'approved')");
            $agents = mysqli_fetch_all($agentRes, MYSQLI_ASSOC);

            $processedCount = 0;
            $totalUnlockedProfit = 0.0;
            $details = [];

            foreach ($agents as $agent) {
                $agentId = (int)$agent['id'];
                $partnershipPct = (float)$agent['partnership_pct'];
                $turnoverPct = (float)$agent['turnover_commission_pct'];
                $prevCarryover = (float)$agent['cycle_net_pnl'];

                // 4. Calculate Player bets for this agent in period
                $betStmt = mysqli_prepare($this->conn, 
                    "SELECT 
                        COALESCE(SUM(stake), 0) AS total_stake,
                        COALESCE(SUM(CASE WHEN status = 'lost' THEN stake ELSE 0 END), 0) AS total_losses,
                        COALESCE(SUM(CASE WHEN status = 'won' THEN win_amount ELSE 0 END), 0) AS total_wins
                     FROM player_bets
                     WHERE agent_id = ? AND settled_at >= ? AND settled_at <= ?");
                mysqli_stmt_bind_param($betStmt, "iss", $agentId, $startDate, $endDate);
                mysqli_stmt_execute($betStmt);
                $betData = mysqli_fetch_assoc(mysqli_stmt_get_result($betStmt));

                $totalStake = (float)$betData['total_stake'];
                $playerLosses = (float)$betData['total_losses'];
                $playerWins = (float)$betData['total_wins'];

                // Net Company GGR for this agent's players
                $netCompanyResult = $playerLosses - $playerWins;

                // Agent P&L share
                $agentPnl = $netCompanyResult * ($partnershipPct / 100.0);

                // Turnover commission
                $turnoverComm = $totalStake * ($turnoverPct / 100.0);

                // Gross cycle profit before previous deficit
                $grossCycleProfit = $agentPnl + $turnoverComm;

                // Net cycle profit factoring previous deficit
                $netCycleProfit = $grossCycleProfit + ($prevCarryover < 0 ? $prevCarryover : 0);

                $unlockedAmount = 0.0;
                $newCarryover = 0.0;
                $plBefore = (float)$agent['withdrawable_profit'];
                $plAfter = $plBefore;

                if ($netCycleProfit > 0) {
                    // Net positive profit earned in this cycle
                    $unlockedAmount = $netCycleProfit;
                    $plAfter = $plBefore + $unlockedAmount;
                    $newCarryover = 0.0; // Deficit cleared

                    // Update agent withdrawable profit
                    $upd = mysqli_prepare($this->conn, 
                        "UPDATE agents 
                         SET withdrawable_profit = withdrawable_profit + ?, 
                             cycle_net_pnl = 0.00, 
                             last_settled_at = NOW() 
                         WHERE id = ?");
                    mysqli_stmt_bind_param($upd, "di", $unlockedAmount, $agentId);
                    mysqli_stmt_execute($upd);

                    $totalUnlockedProfit += $unlockedAmount;

                } else {
                    // Net loss / deficit for the cycle (players won more than they lost)
                    if ($negativeCarryover === 1) {
                        $newCarryover = $netCycleProfit; // Carry forward negative balance
                    } else {
                        $newCarryover = 0.0; // Reset to zero
                    }

                    $upd = mysqli_prepare($this->conn, 
                        "UPDATE agents 
                         SET cycle_net_pnl = ?, 
                             last_settled_at = NOW() 
                         WHERE id = ?");
                    mysqli_stmt_bind_param($upd, "di", $newCarryover, $agentId);
                    mysqli_stmt_execute($upd);
                }

                // 5. Record entry in agent_settlements
                $note = "Settlement for {$periodLabel}. GGR: ₹" . number_format($netCompanyResult, 2) . ", Comm: ₹" . number_format($grossCycleProfit, 2);
                $insSet = mysqli_prepare($this->conn, 
                    "INSERT INTO agent_settlements (agent_id, amount, pl_before, pl_after, period, note, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())");
                mysqli_stmt_bind_param($insSet, "idddss", $agentId, $unlockedAmount, $plBefore, $plAfter, $periodLabel, $note);
                mysqli_stmt_execute($insSet);

                $processedCount++;
                $details[] = [
                    "agent_id" => $agentId,
                    "agent_code" => $agent['agent_code'],
                    "turnover" => $totalStake,
                    "net_ggr" => $netCompanyResult,
                    "cycle_commission" => $grossCycleProfit,
                    "previous_deficit" => $prevCarryover,
                    "unlocked_withdrawable" => $unlockedAmount,
                    "new_carryover" => $newCarryover
                ];
            }

            mysqli_commit($this->conn);

            return [
                "success" => true,
                "period" => $periodLabel,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "cycle_type" => $cycle,
                "agents_processed" => $processedCount,
                "total_unlocked_profit" => $totalUnlockedProfit,
                "details" => $details
            ];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Compute start and end timestamps based on cycle configuration
     */
    public function calculateCycleBounds(string $cycle): array {
        switch ($cycle) {
            case 'daily':
                // Yesterday 00:00 to 23:59
                $yest = date('Y-m-d', strtotime('-1 day'));
                return [
                    'start' => $yest . ' 00:00:00',
                    'end' => $yest . ' 23:59:59',
                    'label' => 'Daily (' . $yest . ')'
                ];

            case 'bi_weekly':
                $day = (int)date('j');
                if ($day <= 15) {
                    // Settle second half of previous month
                    $start = date('Y-m-16 00:00:00', strtotime('last month'));
                    $end = date('Y-m-t 23:59:59', strtotime('last month'));
                    $label = 'Bi-Weekly (16-' . date('t M Y', strtotime('last month')) . ')';
                } else {
                    // Settle first half of current month (1-15)
                    $start = date('Y-m-01 00:00:00');
                    $end = date('Y-m-15 23:59:59');
                    $label = 'Bi-Weekly (1-15 ' . date('M Y') . ')';
                }
                return ['start' => $start, 'end' => $end, 'label' => $label];

            case 'monthly':
                $start = date('Y-m-01 00:00:00', strtotime('last month'));
                $end = date('Y-m-t 23:59:59', strtotime('last month'));
                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => 'Monthly (' . date('F Y', strtotime('last month')) . ')'
                ];

            case 'weekly_monday':
            default:
                // Monday of last week to Sunday of last week
                $start = date('Y-m-d 00:00:00', strtotime('monday last week'));
                $end = date('Y-m-d 23:59:59', strtotime('sunday last week'));
                $weekNum = date('W', strtotime('monday last week'));
                $year = date('Y', strtotime('monday last week'));
                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => "Week {$weekNum} ({$year})"
                ];
        }
    }
}
