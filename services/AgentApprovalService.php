<?php
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/NotificationHelper.php';

class AgentApprovalService {
    private $conn;

    public function __construct($dbConnection = null) {
        global $conn;
        $this->conn = $dbConnection ?: $conn;
        $this->ensureTableExists();
    }

    private function ensureTableExists() {
        if ($this->conn) {
            $sql = "CREATE TABLE IF NOT EXISTS tbl_agent_approvals (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                request_code VARCHAR(50) NOT NULL UNIQUE,
                requester_id INT NOT NULL,
                requester_type ENUM('agent', 'player') NOT NULL DEFAULT 'agent',
                approver_id INT NOT NULL,
                approval_type VARCHAR(50) NOT NULL,
                amount DECIMAL(12,2) DEFAULT 0.00,
                details_json LONGTEXT NULL,
                status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                rejection_reason VARCHAR(255) NULL,
                processed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_approver_status (approver_id, status),
                INDEX idx_requester (requester_id),
                INDEX idx_approval_type (approval_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            @$this->conn->query($sql);
        }
    }

    /**
     * Create a new approval request submitted by or on behalf of a requester
     */
    public function createRequest($requester_id, $requester_type, $approver_id, $approval_type, $amount = 0.00, $details = []) {
        $amount = (float)$amount;
        $request_code = 'APR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $details_json = json_encode($details);

        // Verify parent authorization (1-step hierarchy rule)
        if ($requester_type === 'agent') {
            $stmt = $this->conn->prepare("SELECT parent_id, username FROM agents WHERE id = ?");
            $stmt->bind_param("i", $requester_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $agent = $res->fetch_assoc();
            $stmt->close();

            if (!$agent) {
                return ['success' => false, 'error' => 'Requester agent not found'];
            }
            if ($approver_id > 0 && (int)$agent['parent_id'] !== (int)$approver_id) {
                return ['success' => false, 'error' => 'Unauthorized: Approver is not the direct parent of this agent'];
            }
        } elseif ($requester_type === 'player') {
            $stmt = $this->conn->prepare("SELECT tbl_referral_code FROM tblusersdata WHERE id = ?");
            $stmt->bind_param("i", $requester_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $player = $res->fetch_assoc();
            $stmt->close();

            if (!$player) {
                return ['success' => false, 'error' => 'Requester player not found'];
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO tbl_agent_approvals 
            (request_code, requester_id, requester_type, approver_id, approval_type, amount, details_json, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("sisissd", $request_code, $requester_id, $requester_type, $approver_id, $approval_type, $amount, $details_json);
        
        if ($stmt->execute()) {
            $request_id = $stmt->insert_id;
            $stmt->close();

            // Notify approver
            if ($approver_id > 0 && function_exists('sendAffiliateNotification')) {
                sendAffiliateNotification($this->conn, $approver_id, 'system', 'New Approval Request Required', "Request $request_code ($approval_type) for ₹" . number_format($amount, 2) . " requires your approval.");
            }

            return ['success' => true, 'request_id' => $request_id, 'request_code' => $request_code];
        }

        return ['success' => false, 'error' => $stmt->error];
    }

    /**
     * Auto-sync pending player recharge & withdraw requests into tbl_agent_approvals for direct agents
     */
    public function syncPendingPlayerRequests() {
        // 1. Sync pending recharges
        $sqlDep = "SELECT r.id as recharge_id, r.tbl_recharge_amount, r.tbl_recharge_mode, r.tbl_recharge_details, r.tbl_uniq_id,
                          u.id as user_id, u.tbl_user_name, ag.id as agent_id
                   FROM tblusersrecharge r
                   JOIN tblusersdata u ON (r.tbl_user_id = u.tbl_uniq_id OR r.tbl_user_id = CAST(u.id AS CHAR))
                   JOIN agents ag ON (u.tbl_joined_under = ag.agent_code OR u.tbl_joined_under = ag.username OR u.tbl_joined_under = CAST(ag.id AS CHAR))
                   WHERE r.tbl_request_status = 'pending'";
        $resDep = $this->conn->query($sqlDep);
        if ($resDep) {
            while ($r = $resDep->fetch_assoc()) {
                $recId = (int)$r['recharge_id'];
                $agId = (int)$r['agent_id'];
                $uId = (int)$r['user_id'];
                $amt = (float)$r['tbl_recharge_amount'];

                $chk = $this->conn->query("SELECT id FROM tbl_agent_approvals WHERE request_code = 'APR-DEP-{$recId}'");
                if ($chk && $chk->num_rows === 0) {
                    $requestCode = 'APR-DEP-' . $recId;
                    $details = json_encode([
                        'recharge_id' => $recId,
                        'recharge_mode' => $r['tbl_recharge_mode'],
                        'recharge_details' => $r['tbl_recharge_details'],
                        'uniq_id' => $r['tbl_uniq_id']
                    ]);
                    $stmt = $this->conn->prepare("INSERT INTO tbl_agent_approvals (request_code, requester_id, requester_type, approver_id, approval_type, amount, details_json, status, created_at) VALUES (?, ?, 'player', ?, 'deposit', ?, ?, 'pending', NOW())");
                    $stmt->bind_param("siids", $requestCode, $uId, $agId, $amt, $details);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        // 2. Sync pending withdrawals
        $sqlWd = "SELECT w.id as withdraw_id, w.tbl_withdraw_amount, w.tbl_withdraw_details, w.tbl_uniq_id,
                         u.id as user_id, u.tbl_user_name, ag.id as agent_id
                  FROM tbluserswithdraw w
                  JOIN tblusersdata u ON (w.tbl_user_id = u.tbl_uniq_id OR w.tbl_user_id = CAST(u.id AS CHAR))
                  JOIN agents ag ON (u.tbl_joined_under = ag.agent_code OR u.tbl_joined_under = ag.username OR u.tbl_joined_under = CAST(ag.id AS CHAR))
                  WHERE w.tbl_request_status = 'pending'";
        $resWd = $this->conn->query($sqlWd);
        if ($resWd) {
            while ($w = $resWd->fetch_assoc()) {
                $wdId = (int)$w['withdraw_id'];
                $agId = (int)$w['agent_id'];
                $uId = (int)$w['user_id'];
                $amt = (float)$w['tbl_withdraw_amount'];

                $chk = $this->conn->query("SELECT id FROM tbl_agent_approvals WHERE request_code = 'APR-WD-{$wdId}'");
                if ($chk && $chk->num_rows === 0) {
                    $requestCode = 'APR-WD-' . $wdId;
                    $details = json_encode([
                        'withdraw_id' => $wdId,
                        'withdraw_details' => $w['tbl_withdraw_details'],
                        'uniq_id' => $w['tbl_uniq_id']
                    ]);
                    $stmt = $this->conn->prepare("INSERT INTO tbl_agent_approvals (request_code, requester_id, requester_type, approver_id, approval_type, amount, details_json, status, created_at) VALUES (?, ?, 'player', ?, 'withdrawal', ?, ?, 'pending', NOW())");
                    $stmt->bind_param("siids", $requestCode, $uId, $agId, $amt, $details);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    /**
     * Get pending approvals for an agent OR all pending approvals for Admin ($is_admin = true or approver_id = 0)
     */
    public function getPendingApprovals($approver_id, $approval_type = null, $limit = 50, $offset = 0, $is_admin = false) {
        $this->syncPendingPlayerRequests();
        $limit = (int)$limit;
        $offset = (int)$offset;
        $approver_id = (int)$approver_id;

        $sql = "SELECT a.*, 
                CASE 
                  WHEN a.requester_type = 'agent' THEN ag.username 
                  WHEN a.requester_type = 'player' THEN u.tbl_user_name 
                END as requester_name,
                CASE 
                  WHEN a.requester_type = 'agent' THEN ag.name 
                  WHEN a.requester_type = 'player' THEN u.tbl_full_name 
                END as requester_full_name
                FROM tbl_agent_approvals a
                LEFT JOIN agents ag ON (a.requester_type = 'agent' AND a.requester_id = ag.id)
                LEFT JOIN tblusersdata u ON (a.requester_type = 'player' AND a.requester_id = u.id)
                WHERE a.status = 'pending'";

        if (!$is_admin && $approver_id > 0) {
            $sql .= " AND a.approver_id = " . $approver_id;
        }

        if ($approval_type) {
            if ($approval_type === 'payout_withdrawal' || $approval_type === 'withdrawal') {
                $sql .= " AND a.approval_type IN ('payout_withdrawal', 'withdrawal')";
            } elseif ($approval_type === 'credit_recharge' || $approval_type === 'deposit') {
                $sql .= " AND a.approval_type IN ('credit_recharge', 'deposit')";
            } else {
                $sql .= " AND a.approval_type = '" . $this->conn->real_escape_string($approval_type) . "'";
            }
        }

        $sql .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $row['details'] = json_decode($row['details_json'] ?? '{}', true);
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
    public function getPendingCount($approver_id, $is_admin = false) {
        $this->syncPendingPlayerRequests();
        $approver_id = (int)$approver_id;
        if ($is_admin || $approver_id === 0) {
            $sql = "SELECT COUNT(*) as cnt FROM tbl_agent_approvals WHERE status = 'pending'";
            $res = $this->conn->query($sql)->fetch_assoc();
            return (int)($res['cnt'] ?? 0);
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM tbl_agent_approvals WHERE approver_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $approver_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($res['cnt'] ?? 0);
    }

    /**
     * Process decision (Approve / Reject) atomically (supports Admin override)
     */
    public function processDecision($approval_id, $approver_id, $decision, $rejection_reason = '', $is_admin = false) {
        if (!in_array($decision, ['approved', 'rejected'])) {
            return ['success' => false, 'error' => 'Invalid decision action'];
        }

        $approval_id = (int)$approval_id;
        $approver_id = (int)$approver_id;

        mysqli_begin_transaction($this->conn);

        try {
            // Lock request row
            if ($is_admin || $approver_id === 0) {
                $stmt = $this->conn->prepare("SELECT * FROM tbl_agent_approvals WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $approval_id);
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM tbl_agent_approvals WHERE id = ? AND approver_id = ? FOR UPDATE");
                $stmt->bind_param("ii", $approval_id, $approver_id);
            }

            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$req) {
                mysqli_rollback($this->conn);
                return ['success' => false, 'error' => 'Approval request not found or unauthorized'];
            }

            if ($req['status'] !== 'pending') {
                mysqli_rollback($this->conn);
                return ['success' => false, 'error' => 'Request has already been ' . $req['status']];
            }

            $actual_approver_id = $approver_id > 0 ? $approver_id : (int)$req['approver_id'];


            if ($decision === 'rejected') {
                $stmt = $this->conn->prepare("UPDATE tbl_agent_approvals SET status = 'rejected', rejection_reason = ?, processed_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $rejection_reason, $approval_id);
                $stmt->execute();
                $stmt->close();

                // Sync rejection back to tblusersrecharge / tbluserswithdraw if applicable
                $details = json_decode($req['details_json'] ?? '{}', true);
                if ($req['approval_type'] === 'deposit' && !empty($details['recharge_id'])) {
                    $recId = (int)$details['recharge_id'];
                    $this->conn->query("UPDATE tblusersrecharge SET tbl_request_status = 'rejected' WHERE id = {$recId}");
                } elseif (($req['approval_type'] === 'withdrawal' || $req['approval_type'] === 'payout_withdrawal') && !empty($details['withdraw_id'])) {
                    $wdId = (int)$details['withdraw_id'];
                    $this->conn->query("UPDATE tbluserswithdraw SET tbl_request_status = 'rejected' WHERE id = {$wdId}");
                }

                if (function_exists('sendAffiliateNotification')) {
                    sendAffiliateNotification($this->conn, $req['requester_id'], 'system', 'Approval Request Rejected', "Your request " . $req['request_code'] . " was rejected. Reason: " . ($rejection_reason ?: 'None provided'));
                }

                mysqli_commit($this->conn);
                return ['success' => true, 'message' => 'Request rejected successfully'];
            }

            // Execute logic for APPROVED
            $amount = (float)$req['amount'];
            $requester_id = (int)$req['requester_id'];
            $approval_type = $req['approval_type'];

            if ($approval_type === 'deposit') {
                $details = json_decode($req['details_json'] ?? '{}', true);
                $recId = (int)($details['recharge_id'] ?? 0);
                if ($recId > 0) {
                    $uRec = $this->conn->prepare("UPDATE tblusersrecharge SET tbl_request_status = 'success' WHERE id = ?");
                    $uRec->bind_param("i", $recId);
                    $uRec->execute();
                    $uRec->close();
                }
                // Credit player balance
                $reqIdStr = (string)$requester_id;
                $uBal = $this->conn->prepare("UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE id = ? OR tbl_uniq_id = ?");
                $uBal->bind_param("dis", $amount, $requester_id, $reqIdStr);
                $uBal->execute();
                $uBal->close();

            } elseif ($approval_type === 'withdrawal' || $approval_type === 'payout_withdrawal') {
                $details = json_decode($req['details_json'] ?? '{}', true);
                $wdId = (int)($details['withdraw_id'] ?? 0);
                if ($wdId > 0) {
                    $uWd = $this->conn->prepare("UPDATE tbluserswithdraw SET tbl_request_status = 'success' WHERE id = ?");
                    $uWd->bind_param("i", $wdId);
                    $uWd->execute();
                    $uWd->close();
                }

                // Deduct balance from requester upon withdrawal approval
                if ($req['requester_type'] === 'player') {
                    $reqIdStr = (string)$requester_id;
                    $uBal = $this->conn->prepare("UPDATE tblusersdata SET tbl_balance = GREATEST(0, tbl_balance - ?) WHERE id = ? OR tbl_uniq_id = ?");
                    $uBal->bind_param("dis", $amount, $requester_id, $reqIdStr);
                    $uBal->execute();
                    $uBal->close();
                } elseif ($req['requester_type'] === 'agent') {
                    $agBal = $this->conn->prepare("UPDATE agents SET balance = GREATEST(0, balance - ?) WHERE id = ?");
                    $agBal->bind_param("di", $amount, $requester_id);
                    $agBal->execute();
                    $agBal->close();
                }

            } elseif ($approval_type === 'downline_registration') {
                if ($req['requester_type'] === 'agent') {
                    $uStmt = $this->conn->prepare("UPDATE agents SET status = 'active' WHERE id = ?");
                    $uStmt->bind_param("i", $requester_id);
                    $uStmt->execute();
                    $uStmt->close();
                }
            } elseif ($approval_type === 'credit_recharge') {
                if ($amount <= 0) {
                    mysqli_rollback($this->conn);
                    return ['success' => false, 'error' => 'Recharge amount must be greater than ₹0.00'];
                }

                // Check parent balance if not administrative float override
                if ($actual_approver_id > 0) {
                    $pStmt = $this->conn->prepare("SELECT balance FROM agents WHERE id = ? FOR UPDATE");
                    $pStmt->bind_param("i", $actual_approver_id);
                    $pStmt->execute();
                    $parent = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();

                    if (!$is_admin && (!$parent || (float)$parent['balance'] < $amount)) {
                        mysqli_rollback($this->conn);
                        return ['success' => false, 'error' => 'Insufficient parent credit balance (Available: ₹' . number_format($parent['balance'] ?? 0, 2) . ')'];
                    }

                    // Deduct from parent if parent exists and has balance
                    if ($parent && (float)$parent['balance'] >= $amount) {
                        $dStmt = $this->conn->prepare("UPDATE agents SET balance = balance - ? WHERE id = ?");
                        $dStmt->bind_param("di", $amount, $actual_approver_id);
                        $dStmt->execute();
                        $dStmt->close();
                    }
                }

                // Add to child
                $cStmt = $this->conn->prepare("UPDATE agents SET balance = balance + ? WHERE id = ?");
                $cStmt->bind_param("di", $amount, $requester_id);
                $cStmt->execute();
                $cStmt->close();

                // Log ledger for parent & child
                if ($actual_approver_id > 0) {
                    $lStmt = $this->conn->prepare("INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, remark, created_at) VALUES (?, ?, 'recharge_out', ?, ?, NOW())");
                    $remark = "Credit transfer approval " . $req['request_code'] . ($is_admin ? " (Admin Approved)" : "");
                    $lStmt->bind_param("iids", $actual_approver_id, $requester_id, $amount, $remark);
                    $lStmt->execute();
                    $lStmt->close();
                }

                $l2Stmt = $this->conn->prepare("INSERT INTO agent_credit_ledger (agent_id, transferring_agent_id, transaction_type, amount, remark, created_at) VALUES (?, ?, 'recharge_in', ?, ?, NOW())");
                $remarkIn = "Credit transfer approval " . $req['request_code'];
                $l2Stmt->bind_param("iids", $requester_id, $actual_approver_id, $amount, $remarkIn);
                $l2Stmt->execute();
                $l2Stmt->close();

            } elseif ($approval_type === 'commission_rate_change') {
                $details = json_decode($req['details_json'] ?? '{}', true);
                $new_rate = (float)($details['proposed_rate'] ?? 0);
                if ($new_rate > 0) {
                    $uStmt = $this->conn->prepare("UPDATE agents SET commission_rate = ? WHERE id = ?");
                    $uStmt->bind_param("di", $new_rate, $requester_id);
                    $uStmt->execute();
                    $uStmt->close();
                }
            }

            // Mark approval status
            $uReq = $this->conn->prepare("UPDATE tbl_agent_approvals SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $uReq->bind_param("i", $approval_id);
            $uReq->execute();
            $uReq->close();

            if (function_exists('sendAffiliateNotification')) {
                sendAffiliateNotification($this->conn, $req['requester_id'], 'system', 'Approval Request Approved', "Your request " . $req['request_code'] . " has been approved!");
            }

            mysqli_commit($this->conn);
            return ['success' => true, 'message' => 'Request approved successfully'];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
