<?php
/**
 * AgentTreeService
 * Manages the multi-level agent hierarchy closure table (`agent_tree`).
 * Uses pure mysqli.
 */

class AgentTreeService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new Agent and automatically populate the closure tree (`agent_tree`).
     *
     * @param int|null $parentAgentId Parent agent ID (null if top-level root agent)
     * @param array $agentData Fields for the `agents` table
     * @return int New Agent ID
     * @throws Exception On DB error
     */
    public function createAgentWithTree(?int $parentAgentId, array $agentData): int {
        mysqli_begin_transaction($this->conn);

        try {
            $agentCode = $agentData['agent_code'] ?? ('AGT-' . strtoupper(substr(md5(uniqid()), 0, 6)));
            $username = $agentData['username'] ?? strtolower($agentCode);
            $name = $agentData['name'] ?? '';
            $email = $agentData['email'] ?? '';
            $phone = $agentData['phone'] ?? '';
            $passwordHash = isset($agentData['password']) ? password_hash($agentData['password'], PASSWORD_BCRYPT) : ($agentData['password_hash'] ?? '');
            $rankLevel = $agentData['rank_level'] ?? 'agent';
            $status = $agentData['status'] ?? 'active';
            $partnershipPct = (float)($agentData['partnership_pct'] ?? 50.00);
            $turnoverCommissionPct = (float)($agentData['turnover_commission_pct'] ?? 2.50);
            $openingCredit = (float)($agentData['opening_credit'] ?? 0.00);

            // Normalize rank_level to valid modern enum
            $rawRank = strtolower(str_replace(' ', '_', trim($rankLevel)));
            $validRanks = ['senior_super_agent', 'super_agent', 'master_agent', 'agent'];
            $rankLevel = in_array($rawRank, $validRanks) ? $rawRank : 'agent';

            // Map to legacy level enum ('super_master','master','agent','sub_agent')
            $rawLevel = strtolower(str_replace([' ', '_'], '', trim($agentData['level'] ?? $rankLevel)));
            if (strpos($rawLevel, 'seniorsuper') !== false || strpos($rawLevel, 'supermaster') !== false) {
                $level = 'super_master';
            } elseif (strpos($rawLevel, 'super') !== false || $rawLevel === 'master') {
                $level = 'master';
            } elseif (strpos($rawLevel, 'master') !== false || $rawLevel === 'agent') {
                $level = 'agent';
            } else {
                $level = 'sub_agent';
            }

            // Calculate tree_path
            $treePath = '/';
            if ($parentAgentId !== null && $parentAgentId > 0) {
                $pStmt = mysqli_prepare($this->conn, "SELECT tree_path FROM agents WHERE id = ? LIMIT 1");
                if ($pStmt) {
                    mysqli_stmt_bind_param($pStmt, "i", $parentAgentId);
                    mysqli_stmt_execute($pStmt);
                    $pRes = mysqli_stmt_get_result($pStmt);
                    if ($pRow = mysqli_fetch_assoc($pRes)) {
                        $treePath = rtrim($pRow['tree_path'] ?? '/', '/') . '/' . $parentAgentId . '/';
                    }
                }
            }

            // 1. Insert into `agents` table with phone, level, tree_path
            $sql = "INSERT INTO agents (
                        agent_code, username, name, email, phone, password_hash, 
                        level, parent_id, tree_path, rank_level, status, 
                        partnership_pct, turnover_commission_pct, partnership, commission_rate, 
                        current_credit, credit_reference, balance
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssisssddddddd", 
                $agentCode, $username, $name, $email, $phone, $passwordHash, 
                $level, $parentAgentId, $treePath, $rankLevel, $status, 
                $partnershipPct, $turnoverCommissionPct, $partnershipPct, $turnoverCommissionPct, 
                $openingCredit, $openingCredit, $openingCredit
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert agent: " . mysqli_stmt_error($stmt));
            }

            $newAgentId = mysqli_insert_id($this->conn);

            // 2. Populate `agent_tree` closure table
            if ($parentAgentId !== null && $parentAgentId > 0) {
                // Copy all ancestors of parent and increment depth by 1, plus self-reference at depth 0
                $treeSql = "INSERT INTO agent_tree (ancestor_id, descendant_id, depth)
                            SELECT ancestor_id, ?, depth + 1 
                            FROM agent_tree 
                            WHERE descendant_id = ?
                            UNION ALL 
                            SELECT ?, ?, 0";
                $treeStmt = mysqli_prepare($this->conn, $treeSql);
                mysqli_stmt_bind_param($treeStmt, "iiii", $newAgentId, $parentAgentId, $newAgentId, $newAgentId);
                if (!mysqli_stmt_execute($treeStmt)) {
                    throw new Exception("Failed to populate agent_tree: " . mysqli_stmt_error($treeStmt));
                }
            } else {
                // Top-level root agent (only self reference at depth 0)
                $treeSql = "INSERT INTO agent_tree (ancestor_id, descendant_id, depth) VALUES (?, ?, 0)";
                $treeStmt = mysqli_prepare($this->conn, $treeSql);
                mysqli_stmt_bind_param($treeStmt, "ii", $newAgentId, $newAgentId);
                if (!mysqli_stmt_execute($treeStmt)) {
                    throw new Exception("Failed to insert root agent_tree: " . mysqli_stmt_error($treeStmt));
                }
            }

            mysqli_commit($this->conn);
            return $newAgentId;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Get all ancestors (uplines) of an agent ordered from closest parent to root.
     *
     * @param int $agentId
     * @return array List of ancestor agent records
     */
    public function getAncestors(int $agentId): array {
        $sql = "SELECT a.*, t.depth 
                FROM agents a 
                JOIN agent_tree t ON a.id = t.ancestor_id 
                WHERE t.descendant_id = ? AND t.depth > 0 
                ORDER BY t.depth ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $agentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($res, MYSQLI_ASSOC);
    }

    /**
     * Get all direct and indirect descendants (downlines) of an agent.
     *
     * @param int $agentId
     * @return array List of descendant agent records
     */
    public function getDescendants(int $agentId): array {
        $sql = "SELECT a.*, t.depth 
                FROM agents a 
                JOIN agent_tree t ON a.id = t.descendant_id 
                WHERE t.ancestor_id = ? AND t.depth > 0 
                ORDER BY t.depth ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $agentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($res, MYSQLI_ASSOC);
    }

    /**
     * Get full visual hierarchy tree under an agent.
     *
     * @param int $agentId
     * @return array Tree structure
     */
    public function getHierarchyTree(int $agentId): array {
        $descendants = $this->getDescendants($agentId);
        return $descendants;
    }
}
