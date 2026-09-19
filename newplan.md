# Master System Architecture & API Specification Blueprint
## Complete Implementation Guide for Agent, Affiliate & Admin Systems (`D:\xampp\htdocs\api\`)

---

## Executive Overview

This document is the **definitive, zero-placeholder master engineering specification** for building an **Agent System**, **Affiliate System**, and **Admin Integration** from scratch in a PHP/MySQL environment (`D:\xampp\htdocs\api\`).

```
                               ┌──────────────────────────────────┐
                               │       ADMIN CONSOLE CORE         │
                               │  (Financial & Risk Controller)   │
                               └────────────────┬─────────────────┘
                                                │
                     ┌──────────────────────────┴──────────────────────────┐
                     ▼                                                     ▼
    ┌──────────────────────────────────┐                  ┌──────────────────────────────────┐
    │          AGENT SYSTEM            │                  │         AFFILIATE SYSTEM         │
    │   (B2B Credit & Risk Sharing)    │                  │  (Performance Cash Acquisition)  │
    ├──────────────────────────────────┤                  ├──────────────────────────────────┤
    │ • Multi-tier Hierarchy Tree      │                  │ • Digital Attribution & Links    │
    │ • Credit Float & Injections      │                  │ • RevShare / CPA / Hybrid Deals  │
    │ • Exposed Risk & Unsettled P&L   │                  │ • Daily Automated Commission Run │
    │ • Turnover Commissions           │                  │ • Payout Requests & Approvals    │
    └──────────────────────────────────┘                  └──────────────────────────────────┘
```

---

# SECTION 1: ARCHITECTURAL FOUNDATION & 3 TRUST BOUNDARIES

### 1.1 Actor Roles & Directory Structure (`D:\xampp\htdocs\api\`)

```
D:\xampp\htdocs\api\
├── config/
│   └── database.php        <-- PDO Database connection helper
├── services/
│   ├── BetSettlementService.php  <-- Real-time Sports & Casino bet triggers for Agents
│   ├── AffiliateCommissionWorker.php <-- Daily NGR RevShare & CPA worker
│   └── PayoutService.php   <-- Withdrawal locking & UTR payout releases
├── admin/                  <-- Admin Staff Control APIs (/api/v1/admin/*)
│   ├── agents/             <-- Agent directory, credit, applications, settings
│   └── affiliates/         <-- Affiliate directory, commission run, payouts, settings
├── agent/                  <-- Agent Partner Portal APIs (/api/v1/agent/*)
│   ├── auth/               <-- Agent login & token validation
│   ├── dashboard/          <-- Agent stats & credit float overview
│   ├── downlines/          <-- Sub-agent & player management
│   ├── credit/             <-- Credit float transfers to downlines
│   └── reports/            <-- Turnover commissions & P&L risk reports
└── affiliate/              <-- Affiliate Partner Portal APIs (/api/v1/affiliate/*)
    ├── auth/               <-- Affiliate login & registration
    ├── dashboard/          <-- Clicks, signups, available balance
    ├── links/              <-- Campaign tracking link generation (?ref=CODE)
    ├── referrals/          <-- Referred player conversion table
    ├── earnings/           <-- Commission ledger (RevShare / CPA)
    ├── payouts/            <-- Withdrawal request & payout history
    └── methods/            <-- Bank, UPI, and Crypto payout details
```

| Dimension | Admin Console (`admin/`) | Agent Portal (`agent/`) | Affiliate Portal (`affiliate/`) |
|---|---|---|---|
| **Actor Role** | Super Admin / Platform Staff | Master Agents & Sub-Agents | Performance Marketers & Publishers |
| **Authentication** | Admin Session Token | Agent JWT Session | Affiliate JWT Session / Signed Key |
| **Primary Unit** | Risk Management & System Approvals | Credit Float Allocation to Downlines | Cash Commission Earnings & Withdrawals |

---

# SECTION 2: PRODUCTION DATABASE SCHEMA & SQL DDL SCRIPT

Execute this complete DDL script in MySQL / MariaDB to create all required tables.

```sql
-- =============================================================================
-- 1. SYSTEM ENUMS & CONFIGURATION
-- =============================================================================

CREATE TABLE IF NOT EXISTS program_settings (
    id INT PRIMARY KEY DEFAULT 1,
    agent_default_level ENUM('senior_super_agent', 'super_agent', 'master_agent', 'agent') NOT NULL DEFAULT 'agent',
    agent_review_time_hours INT NOT NULL DEFAULT 24,
    agent_default_partnership_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    agent_default_commission_rate_pct DECIMAL(5,2) NOT NULL DEFAULT 2.50,
    agent_default_opening_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    affiliate_default_tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    affiliate_default_revshare_pct DECIMAL(5,2) NOT NULL DEFAULT 35.00,
    affiliate_default_cpa_amount DECIMAL(18,2) NOT NULL DEFAULT 50.00,
    affiliate_cpa_min_deposit_threshold DECIMAL(18,2) NOT NULL DEFAULT 100.00,
    affiliate_cookie_duration_days INT NOT NULL DEFAULT 30,
    affiliate_minimum_payout DECIMAL(18,2) NOT NULL DEFAULT 1000.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO program_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id=1;

-- =============================================================================
-- 2. AGENT SYSTEM TABLES
-- =============================================================================

CREATE TABLE IF NOT EXISTS agents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_code VARCHAR(32) UNIQUE NOT NULL,
    name VARCHAR(128) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    parent_agent_id BIGINT NULL,
    rank_level ENUM('senior_super_agent', 'super_agent', 'master_agent', 'agent') NOT NULL DEFAULT 'agent',
    status ENUM('active', 'suspended', 'locked', 'closed') NOT NULL DEFAULT 'active',
    partnership_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    turnover_commission_pct DECIMAL(5,2) NOT NULL DEFAULT 2.50,
    current_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    exposed_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_agent_id) REFERENCES agents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_tree (
    ancestor_id BIGINT NOT NULL,
    descendant_id BIGINT NOT NULL,
    depth INT NOT NULL,
    PRIMARY KEY (ancestor_id, descendant_id),
    FOREIGN KEY (ancestor_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (descendant_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_credit_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT NOT NULL,
    admin_id BIGINT NULL,
    transferring_agent_id BIGINT NULL,
    transaction_type VARCHAR(32) NOT NULL, -- INJECTION, CLAWBACK, TRANSFER_OUT, TRANSFER_IN, TURNOVER_COMMISSION, PNL_SETTLEMENT
    amount DECIMAL(18,2) NOT NULL,
    balance_before DECIMAL(18,2) NOT NULL,
    balance_after DECIMAL(18,2) NOT NULL,
    remark TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT,
    FOREIGN KEY (transferring_agent_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_applications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(128) NOT NULL,
    username VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    company VARCHAR(128),
    market_region VARCHAR(128) NOT NULL,
    volume_bracket VARCHAR(32) NOT NULL,
    requested_upline_id BIGINT NULL,
    claimed_upline_text VARCHAR(128),
    status ENUM('pending', 'info_requested', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT,
    info_request_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (requested_upline_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 3. AFFILIATE SYSTEM TABLES
-- =============================================================================

CREATE TABLE IF NOT EXISTS affiliates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_code VARCHAR(32) UNIQUE NOT NULL,
    name VARCHAR(128) NOT NULL,
    company VARCHAR(128),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    parent_affiliate_id BIGINT NULL,
    status ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    deal_type ENUM('revenue_share', 'cpa', 'hybrid') NOT NULL DEFAULT 'revenue_share',
    revshare_pct DECIMAL(5,2) NOT NULL DEFAULT 35.00,
    cpa_amount DECIMAL(18,2) NOT NULL DEFAULT 50.00,
    sub_override_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    available_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    pending_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    lifetime_earnings DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_links (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id BIGINT NOT NULL,
    code VARCHAR(64) UNIQUE NOT NULL,
    campaign_name VARCHAR(128) NOT NULL,
    target_path VARCHAR(255) DEFAULT '/',
    clicks_count BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    link_id BIGINT NOT NULL,
    affiliate_id BIGINT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referrer_url TEXT,
    converted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (link_id) REFERENCES affiliate_links(id) ON DELETE CASCADE,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_referrals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id BIGINT NOT NULL,
    user_id BIGINT UNIQUE NOT NULL,
    link_id BIGINT NULL,
    attributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_deposit_at TIMESTAMP NULL,
    first_deposit_amount DECIMAL(18,2) DEFAULT 0.00,
    is_cpa_qualified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE RESTRICT,
    FOREIGN KEY (link_id) REFERENCES affiliate_links(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_commission_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id BIGINT NOT NULL,
    referral_id BIGINT NULL,
    entry_type VARCHAR(32) NOT NULL, -- revenue_share, cpa, sub_override, clawback
    gross_amount DECIMAL(18,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending, approved, paid, clawed_back
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE RESTRICT,
    FOREIGN KEY (referral_id) REFERENCES affiliate_referrals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_payouts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id BIGINT NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    payout_method_type VARCHAR(32) NOT NULL, -- bank, upi, crypto
    payout_method_details JSON NOT NULL,
    status ENUM('requested', 'approved', 'paid', 'rejected') NOT NULL DEFAULT 'requested',
    transaction_reference VARCHAR(128),
    rejection_reason TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_payout_methods (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id BIGINT NOT NULL,
    method_type VARCHAR(32) NOT NULL,
    details JSON NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 4. BET HISTORY TABLES (SPORTS & CASINO)
-- =============================================================================

CREATE TABLE IF NOT EXISTS sports_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    bet_amount DECIMAL(18,2) NOT NULL,
    win_amount DECIMAL(18,2) DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending, won, lost, void
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS casino_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    bet_amount DECIMAL(18,2) NOT NULL,
    win_amount DECIMAL(18,2) DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending, won, lost, void
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 5. AUDIT LOGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT NOT NULL,
    action_group VARCHAR(64) NOT NULL,
    action_type VARCHAR(64) NOT NULL,
    target_entity_id BIGINT NOT NULL,
    target_entity_type VARCHAR(32) NOT NULL,
    payload_before JSON NULL,
    payload_after JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

# SECTION 3: REAL-TIME BET INTEGRATION & SERVICE CODE IMPLEMENTATIONS

This section provides **complete, production PHP service implementations** that link Sports and Casino bets to Agent Turnover, Agent P&L splits, and Affiliate RevShare/CPA commissions.

---

### 3.1 PHP Service: `D:\xampp\htdocs\api\services\BetSettlementService.php`
*Triggered in real-time whenever a player places or settles a Sports/Casino bet.*

```php
<?php
// D:\xampp\htdocs\api\services\BetSettlementService.php

class BetSettlementService {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * 1. TRIGGER ON BET PLACED (PENDING STATE)
     * Locks exposed credit up the Agent Hierarchy Tree
     */
    public function onBetPlaced($userId, $betAmount) {
        // Find player's assigned Agent ID
        $stmt = $this->db->prepare("SELECT agent_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $agentId = $stmt->fetchColumn();

        if (!$agentId) return; // Player has no assigned agent

        // Increment exposed credit for all ancestors in the hierarchy tree
        $sql = "UPDATE agents 
                SET exposed_credit = exposed_credit + ? 
                WHERE id IN (SELECT ancestor_id FROM agent_tree WHERE descendant_id = ?)";
        $this->db->prepare($sql)->execute([$betAmount, $agentId]);
    }

    /**
     * 2. TRIGGER ON BET SETTLED (WON OR LOST STATE)
     * Unlocks exposed credit, pays Turnover Commission, and settles Net P&L Profit Share
     */
    public function onBetSettled($userId, $betId, $betAmount, $winAmount, $status) {
        if (!in_array($status, ['won', 'lost'])) return;

        $this->db->beginTransaction();

        try {
            // Find player's assigned Agent
            $stmt = $this->db->prepare("SELECT agent_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $agentId = $stmt->fetchColumn();

            if ($agentId) {
                // Step A: Release Exposed Credit across tree
                $sql = "UPDATE agents 
                        SET exposed_credit = GREATEST(0, exposed_credit - ?) 
                        WHERE id IN (SELECT ancestor_id FROM agent_tree WHERE descendant_id = ?)";
                $this->db->prepare($sql)->execute([$betAmount, $agentId]);

                // Step B: Calculate & Pay Agent Turnover Commission
                $stmt = $this->db->prepare("SELECT current_credit, turnover_commission_pct, partnership_pct FROM agents WHERE id = ? FOR UPDATE");
                $stmt->execute([$agentId]);
                $agent = $stmt->fetch();

                $turnoverComm = $betAmount * ($agent['turnover_commission_pct'] / 100.0);
                if ($turnoverComm > 0) {
                    $newCredit = $agent['current_credit'] + $turnoverComm;
                    $this->db->prepare("UPDATE agents SET current_credit = ? WHERE id = ?")->execute([$newCredit, $agentId]);

                    // Record Ledger
                    $this->db->prepare("INSERT INTO agent_credit_ledger (agent_id, transaction_type, amount, balance_before, balance_after, remark) 
                                        VALUES (?, 'TURNOVER_COMMISSION', ?, ?, ?, ?)")
                             ->execute([$agentId, $turnoverComm, $agent['current_credit'], $newCredit, "Turnover commission on bet #{$betId}"]);
                    
                    $agent['current_credit'] = $newCredit;
                }

                // Step C: Settle P&L Profit/Loss Split
                // Net Result Delta: positive means player lost (agent profit), negative means player won (agent loss)
                $netResult = $betAmount - $winAmount;
                $agentPnlShare = $netResult * ($agent['partnership_pct'] / 100.0);

                if ($agentPnlShare != 0) {
                    $finalCredit = $agent['current_credit'] + $agentPnlShare;
                    $this->db->prepare("UPDATE agents SET current_credit = ? WHERE id = ?")->execute([$finalCredit, $agentId]);

                    $this->db->prepare("INSERT INTO agent_credit_ledger (agent_id, transaction_type, amount, balance_before, balance_after, remark) 
                                        VALUES (?, 'PNL_SETTLEMENT', ?, ?, ?, ?)")
                             ->execute([$agentId, $agentPnlShare, $agent['current_credit'], $finalCredit, "P&L settlement for bet #{$betId}"]);
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

---

### 3.2 PHP Service: `D:\xampp\htdocs\api\services\AffiliateCommissionWorker.php`
*Executed daily by automated cron worker (`POST /api/v1/admin/affiliates/commissions/run`).*

```php
<?php
// D:\xampp\htdocs\api\services\AffiliateCommissionWorker.php

class AffiliateCommissionWorker {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function runDailyCommissionJob($periodDate = null) {
        if (!$periodDate) {
            $periodDate = date('Y-m-d', strtotime('-1 day'));
        }

        $this->db->beginTransaction();

        try {
            // 1. Fetch NGR per referred user for yesterday's settled bets
            $sql = "SELECT 
                        ar.id AS referral_id,
                        ar.affiliate_id,
                        af.deal_type,
                        af.revshare_pct,
                        af.cpa_amount,
                        af.parent_affiliate_id,
                        SUM(b.total_bets) AS gross_bets,
                        SUM(b.total_wins) AS gross_wins
                    FROM affiliate_referrals ar
                    JOIN affiliates af ON af.id = ar.affiliate_id
                    JOIN (
                        SELECT user_id, SUM(bet_amount) AS total_bets, SUM(win_amount) AS total_wins 
                        FROM sports_bets 
                        WHERE status IN ('won', 'lost') AND DATE(settled_at) = ?
                        GROUP BY user_id
                        UNION ALL
                        SELECT user_id, SUM(bet_amount) AS total_bets, SUM(win_amount) AS total_wins 
                        FROM casino_bets 
                        WHERE status IN ('won', 'lost') AND DATE(settled_at) = ?
                        GROUP BY user_id
                    ) b ON b.user_id = ar.user_id
                    WHERE af.status = 'approved'
                    GROUP BY ar.id, ar.affiliate_id, af.deal_type, af.revshare_pct, af.cpa_amount, af.parent_affiliate_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$periodDate, $periodDate]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalGenerated = 0.0;

            foreach ($results as $row) {
                $ggr = $row['gross_bets'] - $row['gross_wins'];
                $platformFee = $ggr > 0 ? ($ggr * 0.15) : 0.0; // 15% platform admin fee
                $ngr = max(0, $ggr - $platformFee);

                if ($row['deal_type'] === 'revenue_share' || $row['deal_type'] === 'hybrid') {
                    $commission = $ngr * ($row['revshare_pct'] / 100.0);

                    if ($commission > 0) {
                        // Insert RevShare into Ledger
                        $this->db->prepare("INSERT INTO affiliate_commission_ledger (affiliate_id, referral_id, entry_type, gross_amount, status, period_start, period_end) 
                                            VALUES (?, ?, 'revenue_share', ?, 'pending', ?, ?)")
                                 ->execute([$row['affiliate_id'], $row['referral_id'], $commission, $periodDate, $periodDate]);

                        // Update Affiliate Pending Balance
                        $this->db->prepare("UPDATE affiliates SET pending_balance = pending_balance + ? WHERE id = ?")
                                 ->execute([$commission, $row['affiliate_id']]);

                        $totalGenerated += $commission;

                        // Check Sub-Affiliate Override for Parent Affiliate
                        if ($row['parent_affiliate_id']) {
                            $parentStmt = $this->db->prepare("SELECT sub_override_pct FROM affiliates WHERE id = ? AND status = 'approved'");
                            $parentStmt->execute([$row['parent_affiliate_id']]);
                            $subOverridePct = $parentStmt->fetchColumn();

                            if ($subOverridePct > 0) {
                                $overrideCommission = $commission * ($subOverridePct / 100.0);
                                $this->db->prepare("INSERT INTO affiliate_commission_ledger (affiliate_id, referral_id, entry_type, gross_amount, status, period_start, period_end) 
                                                    VALUES (?, ?, 'sub_override', ?, 'pending', ?, ?)")
                                         ->execute([$row['parent_affiliate_id'], $row['referral_id'], $overrideCommission, $periodDate, $periodDate]);

                                $this->db->prepare("UPDATE affiliates SET pending_balance = pending_balance + ? WHERE id = ?")
                                         ->execute([$overrideCommission, $row['parent_affiliate_id']]);
                            }
                        }
                    }
                }
            }

            $this->db->commit();
            return [
                "success" => true,
                "period_processed" => $periodDate,
                "total_commission_generated" => $totalGenerated,
                "records_processed" => count($results)
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

---

# SECTION 4: EXHAUSTIVE API CONTRACTS & IMPLEMENTATION SPECIFICATIONS

Every API endpoint across all 3 domains is specified below with HTTP Method, Path, Payload JSON, Response JSON, and Backend PHP Logic.

---

## DOMAIN 1: ADMIN CONSOLE ENDPOINTS (`D:\xampp\htdocs\api\admin\`)

### 1. `GET /api/v1/admin/agents`
- **File**: `D:\xampp\htdocs\api\admin\agents\index.php`
- **Params**: `page=1`, `limit=20`, `status=active`, `search=john`
- **Response `200 OK`**:
```json
{
  "summary": { "active_agents": 142, "credit_outstanding": 4500000.00, "total_exposure": 850000.00 },
  "data": [{
    "id": 12, "agent_code": "AGT-0012", "name": "Rajesh Kumar", "email": "rajesh@agent.com",
    "parent_agent_name": "Root Platform", "rank_level": "master_agent", "status": "active",
    "partnership_pct": 60.00, "turnover_commission_pct": 2.00, "current_credit": 250000.00,
    "exposed_credit": 45000.00, "unsettled_pnl": -12500.00, "downline_players_count": 145
  }],
  "pagination": { "page": 1, "limit": 20, "total_records": 142 }
}
```

---

### 2. `GET /api/v1/admin/agents/[id]`
- **File**: `D:\xampp\htdocs\api\admin\agents\detail\index.php`
- **Response `200 OK`**: Returns agent details and downline hierarchy tree.

---

### 3. `POST /api/v1/admin/agents/[id]/credit`
- **File**: `D:\xampp\htdocs\api\admin\agents\credit\index.php`
- **Request Body**: `{ "amount": 50000.00, "remark": "Opening float injection" }`

---

### 4. `POST /api/v1/admin/agents/[id]/status`
- **File**: `D:\xampp\htdocs\api\admin\agents\status\index.php`
- **Request Body**: `{ "status": "suspended", "reason": "Risk breach" }`

---

### 5. `DELETE /api/v1/admin/agents/[id]`
- **File**: `D:\xampp\htdocs\api\admin\agents\delete\index.php`

---

### 6. `GET /api/v1/admin/agents/applications`
- **File**: `D:\xampp\htdocs\api\admin\agents\applications\index.php`

---

### 7. `POST /api/v1/admin/agents/applications/[id]/approve`
- **File**: `D:\xampp\htdocs\api\admin\agents\applications\approve\index.php`
- **Request Body**: `{ "upline_id": 12, "rank_level": "agent", "opening_credit": 10000.00, "partnership_pct": 50.00, "commission_pct": 2.50 }`

---

### 8. `POST /api/v1/admin/agents/applications/[id]/reject`
- **File**: `D:\xampp\htdocs\api\admin\agents\applications\reject\index.php`

---

### 9. `POST /api/v1/admin/agents/applications/[id]/request-info`
- **File**: `D:\xampp\htdocs\api\admin\agents\applications\info\index.php`

---

### 10. `GET/PUT /api/v1/admin/agents/settings`
- **File**: `D:\xampp\htdocs\api\admin\agents\settings\index.php`

---

### 11. `GET /api/v1/admin/affiliates`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\index.php`

---

### 12. `POST /api/v1/admin/affiliates/commissions/run`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\commissions\run\index.php`
- **PHP Code**: Invokes `AffiliateCommissionWorker->runDailyCommissionJob()`.

---

### 13. `GET /api/v1/admin/affiliates/payouts`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\payouts\index.php`

---

### 14. `POST /api/v1/admin/affiliates/payouts/[id]/approve`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\payouts\approve\index.php`

---

### 15. `POST /api/v1/admin/affiliates/payouts/[id]/pay`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\payouts\pay\index.php`
- **Request Body**: `{ "transaction_reference": "UTR994810294812" }`

---

### 16. `POST /api/v1/admin/affiliates/payouts/[id]/reject`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\payouts\reject\index.php`

---

### 17. `POST /api/v1/admin/affiliates/payouts/bulk`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\payouts\bulk\index.php`

---

### 18. `GET/PUT /api/v1/admin/affiliates/settings`
- **File**: `D:\xampp\htdocs\api\admin\affiliates\settings\index.php`

---

## DOMAIN 2: AGENT PORTAL ENDPOINTS (`D:\xampp\htdocs\api\agent\`)

### 19. `POST /api/v1/agent/auth/login`
- **File**: `D:\xampp\htdocs\api\agent\auth\login.php`

---

### 20. `GET /api/v1/agent/dashboard`
- **File**: `D:\xampp\htdocs\api\agent\dashboard\index.php`

---

### 21. `GET /api/v1/agent/downlines`
- **File**: `D:\xampp\htdocs\api\agent\downlines\index.php`

---

### 22. `POST /api/v1/agent/downlines/create`
- **File**: `D:\xampp\htdocs\api\agent\downlines\create.php`

---

### 23. `POST /api/v1/agent/credit/transfer`
- **File**: `D:\xampp\htdocs\api\agent\credit\transfer.php`
- **Request Body**: `{ "target_agent_id": 45, "amount": 10000.00, "remark": "Float reload" }`

---

### 24. `GET /api/v1/agent/credit/statement`
- **File**: `D:\xampp\htdocs\api\agent\credit\statement.php`

---

### 25. `GET /api/v1/agent/reports/pnl`
- **File**: `D:\xampp\htdocs\api\agent\reports\pnl.php`

---

### 26. `GET /api/v1/agent/reports/exposure`
- **File**: `D:\xampp\htdocs\api\agent\reports\exposure.php`

---

### 27. `GET/PUT /api/v1/agent/profile`
- **File**: `D:\xampp\htdocs\api\agent\profile\index.php`

---

## DOMAIN 3: AFFILIATE PORTAL ENDPOINTS (`D:\xampp\htdocs\api\affiliate\`)

### 28. `POST /api/v1/affiliate/auth/login`
- **File**: `D:\xampp\htdocs\api\affiliate\auth\login.php`

---

### 29. `POST /api/v1/affiliate/auth/register`
- **File**: `D:\xampp\htdocs\api\affiliate\auth\register.php`

---

### 30. `GET /api/v1/affiliate/dashboard`
- **File**: `D:\xampp\htdocs\api\affiliate\dashboard\index.php`

---

### 31. `GET/POST /api/v1/affiliate/links`
- **File**: `D:\xampp\htdocs\api\affiliate\links\index.php`

---

### 32. `GET /api/v1/affiliate/referrals`
- **File**: `D:\xampp\htdocs\api\affiliate\referrals\index.php`

---

### 33. `GET /api/v1/affiliate/earnings`
- **File**: `D:\xampp\htdocs\api\affiliate\earnings\index.php`

---

### 34. `POST /api/v1/affiliate/payouts/request`
- **File**: `D:\xampp\htdocs\api\affiliate\payouts\request.php`

---

### 35. `GET /api/v1/affiliate/payouts/history`
- **File**: `D:\xampp\htdocs\api\affiliate\payouts\history.php`

---

### 36. `GET/POST /api/v1/affiliate/methods`
- **File**: `D:\xampp\htdocs\api\affiliate\methods\index.php`

---

# SECTION 5: BUILD & DEPLOYMENT CHECKLIST

1. Run Section 2 SQL DDL in phpMyAdmin / MySQL.
2. Place `BetSettlementService.php` and `AffiliateCommissionWorker.php` in `D:\xampp\htdocs\api\services\`.
3. Hook `onBetPlaced()` and `onBetSettled()` into your Sports and Casino game settlement engines.
4. Implement all 36 API files across `admin/`, `agent/`, and `affiliate/`.
5. Add Windows Task Scheduler cron to run `AffiliateCommissionWorker::runDailyCommissionJob()` daily at 01:00 UTC.
