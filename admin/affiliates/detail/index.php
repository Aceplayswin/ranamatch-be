<?php
define("ACCESS_SECURITY", "true");
include '../../../security/config.php';
include '../../../security/constants.php';
include '../../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('location:../../logout-account');
    exit;
}

$aff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($aff_id <= 0) {
    // If no specific ID provided, load the most recent affiliate
    $firstQ = mysqli_query($conn, "SELECT id FROM affiliates ORDER BY id DESC LIMIT 1");
    if ($firstRow = mysqli_fetch_assoc($firstQ)) {
        $aff_id = (int)$firstRow['id'];
    }
}

// Helper to decode and format payout method details
function parsePayoutDetails($raw) {
    if (empty($raw)) return [];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['account_details' => $raw];
}

// Fetch affiliate data from DB
$aff = null;
$aff_links = [];
$referral_count = 0;
$lifetime_earnings = 0;
$pending_balance = 0;
$kyc_docs = [];
$aff_tickets = [];
$aff_referrals = [];
$aff_commissions = [];
$aff_payouts = [];
$aff_payout_methods = [];
$aff_activity = [];
$parent_aff = null;
$sub_affiliates = [];
$all_affiliates = [];

if ($aff_id > 0) {
    // 1. Affiliate record
    $stmt = mysqli_prepare($conn, "SELECT * FROM affiliates WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $aff_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $aff = $row;
        }
    }

    if ($aff && is_array($aff)) {
        // 2. Fetch Parent / Referrer Affiliate details (if referred)
        if (!empty($aff['parent_id'])) {
            $pStmt = mysqli_prepare($conn, "SELECT id, affiliate_code, full_name, company_name, email, phone, tier, status FROM affiliates WHERE id = ? LIMIT 1");
            if ($pStmt) {
                mysqli_stmt_bind_param($pStmt, "i", $aff['parent_id']);
                mysqli_stmt_execute($pStmt);
                $pRes = mysqli_stmt_get_result($pStmt);
                $parent_aff = mysqli_fetch_assoc($pRes);
            }
        }

        // 3. Fetch Sub-Affiliates (recruited downline partners)
        $subStmt = mysqli_prepare($conn, "SELECT a.id, a.affiliate_code, a.full_name, a.company_name, a.email, a.phone, a.tier, a.status, a.created_at,
                                                 (SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_id = a.id) AS total_players,
                                                 (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_ledger WHERE affiliate_id = a.id) AS sub_earnings
                                          FROM affiliates a
                                          WHERE a.parent_id = ?
                                          ORDER BY a.created_at DESC");
        if ($subStmt) {
            mysqli_stmt_bind_param($subStmt, "i", $aff_id);
            mysqli_stmt_execute($subStmt);
            $subRes = mysqli_stmt_get_result($subStmt);
            while ($subRow = mysqli_fetch_assoc($subRes)) {
                $sub_affiliates[] = $subRow;
            }
        }

        // 4. Fetch list of potential Upline Sponsors for the Terms dropdown
        $allAffQ = mysqli_query($conn, "SELECT id, affiliate_code, full_name, company_name, email FROM affiliates WHERE id != $aff_id ORDER BY full_name ASC");
        if ($allAffQ) {
            while ($aRow = mysqli_fetch_assoc($allAffQ)) {
                $all_affiliates[] = $aRow;
            }
        }

        // 5. Referral player count
        $stmt2 = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM affiliate_referrals WHERE affiliate_id = ?");
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "i", $aff_id);
            mysqli_stmt_execute($stmt2);
            $res2 = mysqli_stmt_get_result($stmt2);
            if ($row2 = mysqli_fetch_assoc($res2)) {
                $referral_count = (int)($row2['cnt'] ?? 0);
            }
        }

        // 6. Tracking links with signups, FTDs, FTD volume, and total earned commission
        $stmt3 = mysqli_prepare($conn, 
            "SELECT al.*, 
                    COUNT(DISTINCT ar.id) AS signups_count,
                    COUNT(DISTINCT CASE WHEN ar.first_deposit_at IS NOT NULL THEN ar.id END) AS ftds_count,
                    COALESCE(SUM(CASE WHEN ar.first_deposit_at IS NOT NULL THEN ar.first_deposit_amount ELSE 0 END), 0) AS total_ftd_amount,
                    COALESCE((
                        SELECT SUM(acl.amount) 
                        FROM affiliate_commission_ledger acl 
                        LEFT JOIN affiliate_referrals ar2 ON ar2.id = acl.referral_id
                        WHERE acl.link_id = al.id OR ar2.link_id = al.id
                    ), 0) AS total_link_commission
             FROM affiliate_links al
             LEFT JOIN affiliate_referrals ar ON ar.link_id = al.id
             WHERE al.affiliate_id = ?
             GROUP BY al.id
             ORDER BY al.created_at DESC"
        );
        if ($stmt3) {
            mysqli_stmt_bind_param($stmt3, "i", $aff_id);
            mysqli_stmt_execute($stmt3);
            $res3 = mysqli_stmt_get_result($stmt3);
            while ($row3 = mysqli_fetch_assoc($res3)) {
                $aff_links[] = $row3;
            }
        }

        // 7. Earnings
        $lifetime_earnings = floatval($aff['lifetime_earnings'] ?? 0);
        $pending_balance = floatval($aff['pending_balance'] ?? 0);

        // 8. KYC documents
        $stmt4 = mysqli_prepare($conn, "SELECT * FROM affiliate_kyc_documents WHERE affiliate_id = ? ORDER BY created_at DESC");
        if ($stmt4) {
            mysqli_stmt_bind_param($stmt4, "i", $aff_id);
            mysqli_stmt_execute($stmt4);
            $res4 = mysqli_stmt_get_result($stmt4);
            while ($row4 = mysqli_fetch_assoc($res4)) {
                $kyc_docs[] = $row4;
            }
        }

        // 9. Support tickets
        $stmt5 = mysqli_prepare($conn, "SELECT * FROM affiliate_support_tickets WHERE affiliate_id = ? ORDER BY created_at DESC");
        if ($stmt5) {
            mysqli_stmt_bind_param($stmt5, "i", $aff_id);
            mysqli_stmt_execute($stmt5);
            $res5 = mysqli_stmt_get_result($stmt5);
            while ($row5 = mysqli_fetch_assoc($res5)) {
                $aff_tickets[] = $row5;
            }
        }

        // 10. Referrals with user data and link code/name
        $stmt6 = mysqli_prepare($conn, 
            "SELECT r.*, 
                    u.id AS tbl_user_internal_id, u.tbl_uniq_id,
                    u.tbl_user_name, u.tbl_full_name, u.tbl_email_id, u.tbl_mobile_num, u.tbl_balance,
                    al.name AS link_name, al.code AS link_code, al.target_path AS link_target
             FROM affiliate_referrals r 
             LEFT JOIN tblusersdata u ON (u.id = r.user_id OR u.tbl_uniq_id = r.user_id) 
             LEFT JOIN affiliate_links al ON al.id = r.link_id
             WHERE r.affiliate_id = ? 
             ORDER BY r.signup_at DESC"
        );
        if ($stmt6) {
            mysqli_stmt_bind_param($stmt6, "i", $aff_id);
            mysqli_stmt_execute($stmt6);
            $res6 = mysqli_stmt_get_result($stmt6);
            while ($row6 = mysqli_fetch_assoc($res6)) {
                $aff_referrals[] = $row6;
            }
        }

        // 11. Commission ledger
        $stmt7 = mysqli_prepare($conn, "SELECT * FROM affiliate_commission_ledger WHERE affiliate_id = ? ORDER BY created_at DESC");
        if ($stmt7) {
            mysqli_stmt_bind_param($stmt7, "i", $aff_id);
            mysqli_stmt_execute($stmt7);
            $res7 = mysqli_stmt_get_result($stmt7);
            while ($row7 = mysqli_fetch_assoc($res7)) {
                $aff_commissions[] = $row7;
            }
        }

        // 12. Payouts
        $stmt8 = mysqli_prepare($conn, "SELECT * FROM affiliate_payouts WHERE affiliate_id = ? ORDER BY requested_at DESC");
        if ($stmt8) {
            mysqli_stmt_bind_param($stmt8, "i", $aff_id);
            mysqli_stmt_execute($stmt8);
            $res8 = mysqli_stmt_get_result($stmt8);
            while ($row8 = mysqli_fetch_assoc($res8)) {
                $aff_payouts[] = $row8;
            }
        }

        // 13. Payout methods (Bank, UPI, Crypto)
        $stmt9 = mysqli_prepare($conn, "SELECT * FROM affiliate_payout_methods WHERE affiliate_id = ? ORDER BY is_primary DESC, created_at DESC");
        if ($stmt9) {
            mysqli_stmt_bind_param($stmt9, "i", $aff_id);
            mysqli_stmt_execute($stmt9);
            $res9 = mysqli_stmt_get_result($stmt9);
            while ($row9 = mysqli_fetch_assoc($res9)) {
                $aff_payout_methods[] = $row9;
            }
        }

        // 14. Audit activity
        $stmt10 = mysqli_prepare($conn, "SELECT * FROM admin_audit_logs WHERE target_entity_id = ? AND target_entity_type = 'affiliate' ORDER BY created_at DESC LIMIT 100");
        if ($stmt10) {
            mysqli_stmt_bind_param($stmt10, "i", $aff_id);
            mysqli_stmt_execute($stmt10);
            $res10 = mysqli_stmt_get_result($stmt10);
            while ($row10 = mysqli_fetch_assoc($res10)) {
                $aff_activity[] = $row10;
            }
        }

        // 15. Real-Time Intraday Player Telemetry from tblmatchplayed
        $live_uids = [];
        foreach ($aff_referrals as $ref) {
            if (!empty($ref['user_id'])) $live_uids[] = (string)$ref['user_id'];
            if (!empty($ref['tbl_user_internal_id'])) $live_uids[] = (string)$ref['tbl_user_internal_id'];
            if (!empty($ref['tbl_uniq_id'])) $live_uids[] = (string)$ref['tbl_uniq_id'];
        }
        $live_uids = array_unique(array_filter($live_uids));

        $live_stats = [
            'today_turnover' => 0.0,
            'today_player_wins' => 0.0,
            'today_player_losses' => 0.0,
            'today_ggr' => 0.0,
            'today_estimated_revshare' => 0.0,
            'already_settled_today' => 0.0,
            'unsettled_revshare' => 0.0,
            'active_players_today' => 0,
            'total_rounds_today' => 0
        ];
        $live_recent_bets = [];

        if (!empty($live_uids)) {
            $quoted_uids = "'" . implode("','", array_map(function($u) use ($conn) {
                return mysqli_real_escape_string($conn, $u);
            }, $live_uids)) . "'";

            $live_agg_q = mysqli_query($conn, "
                SELECT 
                    COUNT(*) AS total_rounds,
                    COUNT(DISTINCT tbl_user_id) AS active_players,
                    COALESCE(SUM(tbl_match_cost), 0) AS total_bets,
                    COALESCE(SUM(tbl_match_profit), 0) AS total_wins,
                    COALESCE(SUM(CASE WHEN tbl_match_status IN ('loss', 'lost') OR tbl_match_profit = 0 THEN tbl_match_cost ELSE 0 END), 0) AS total_losses
                FROM tblmatchplayed 
                WHERE tbl_user_id IN ({$quoted_uids})
                  AND tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')
                  AND (
                    DATE(tbl_updated_at) = CURDATE() 
                    OR DATE(created_at) = CURDATE() 
                    OR tbl_time_stamp LIKE CONCAT(DATE_FORMAT(CURDATE(), '%d-%m-%Y'), '%')
                  )
            ");
            if ($live_agg_q && $agg_row = mysqli_fetch_assoc($live_agg_q)) {
                $bets = (float)$agg_row['total_bets'];
                $wins = (float)$agg_row['total_wins'];
                $ggr = $bets - $wins;
                $platform_fee = $ggr > 0 ? ($ggr * 0.15) : 0.0;
                $ngr = max(0, $ggr - $platform_fee);
                $aff_revshare_rate = (float)($aff['revshare_pct'] ?? 30.0);
                $aff_deal_type_raw = strtolower($aff['deal_type'] ?? 'revshare');
                $estimated_revshare = in_array($aff_deal_type_raw, ['revenue_share', 'revshare', 'hybrid']) ? ($ngr * ($aff_revshare_rate / 100.0)) : 0.0;

                // Settled in ledger today
                $settled_q = mysqli_query($conn, "
                    SELECT COALESCE(SUM(amount), 0) AS settled_today 
                    FROM affiliate_commission_ledger 
                    WHERE affiliate_id = {$aff_id} AND entry_type = 'rev_share' AND DATE(created_at) = CURDATE()
                ");
                $already_settled = (float)(mysqli_fetch_assoc($settled_q)['settled_today'] ?? 0.0);
                $unsettled = max(0, $estimated_revshare - $already_settled);

                $live_stats = [
                    'today_turnover' => round($bets, 2),
                    'today_player_wins' => round($wins, 2),
                    'today_player_losses' => round((float)$agg_row['total_losses'], 2),
                    'today_ggr' => round($ggr, 2),
                    'today_estimated_revshare' => round($estimated_revshare, 2),
                    'already_settled_today' => round($already_settled, 2),
                    'unsettled_revshare' => round($unsettled, 2),
                    'active_players_today' => (int)$agg_row['active_players'],
                    'total_rounds_today' => (int)$agg_row['total_rounds']
                ];
            }

            // Recent 10 live bets from referred players
            $recent_bets_q = mysqli_query($conn, "
                SELECT m.id, m.tbl_user_id, m.tbl_project_name, m.tbl_provider, m.tbl_match_details, m.tbl_period_id, 
                       m.tbl_match_cost, m.tbl_match_profit, m.tbl_match_status, m.tbl_match_result, 
                       m.tbl_time_stamp, m.tbl_updated_at, m.created_at,
                       u.tbl_user_name, u.tbl_full_name
                FROM tblmatchplayed m
                LEFT JOIN tblusersdata u ON (u.id = m.tbl_user_id OR u.tbl_uniq_id = m.tbl_user_id)
                WHERE m.tbl_user_id IN ({$quoted_uids})
                  AND m.tbl_match_status IN ('completed', 'settled', 'profit', 'loss', 'lost', 'win')
                ORDER BY m.id DESC 
                LIMIT 10
            ");
            if ($recent_bets_q) {
                while ($b_row = mysqli_fetch_assoc($recent_bets_q)) {
                    $live_recent_bets[] = $b_row;
                }
            }
        }
    }
}

if (!is_array($aff)) {
    $aff = [];
}

$is_aff_found = !empty($aff['id']);
$aff_name = !empty($aff['company_name']) ? $aff['company_name'] : (!empty($aff['full_name']) ? $aff['full_name'] : (!empty($aff['email']) ? $aff['email'] : 'Unknown Affiliate'));
$aff_full_name = $aff['full_name'] ?? '';
$aff_company_name = $aff['company_name'] ?? '';
$aff_email = $aff['email'] ?? '';
$aff_phone = $aff['phone'] ?? '';
$aff_website = $aff['website'] ?? '';
$aff_deal_type = strtolower($aff['deal_type'] ?? 'revshare');
if ($aff_deal_type === 'revenue_share') $aff_deal_type = 'revshare';
$aff_cpa = floatval($aff['cpa_amount'] ?? 0);
$aff_sub_override = floatval($aff['sub_override_pct'] ?? 5.0);
$aff_onboarding = !empty($aff['onboarding_completed']) ? 'Completed' : 'Pending Wizard';

$aff_code = !empty($aff['affiliate_code']) ? $aff['affiliate_code'] : (!empty($aff['referral_code']) ? $aff['referral_code'] : 'N/A');
$aff_tier = !empty($aff['tier']) ? ucfirst($aff['tier']) : 'Bronze';
$aff_status = !empty($aff['status']) ? ucfirst($aff['status']) : 'Pending';
$aff_revshare = intval($aff['revshare_pct'] ?? 35);
$aff_joined = !empty($aff['created_at']) ? date('M d, Y', strtotime($aff['created_at'])) : 'N/A';

$ftd_count = 0;
foreach ($aff_referrals as $ref) {
    if (floatval($ref['first_deposit_amount'] ?? 0) > 0) {
        $ftd_count++;
    }
}

$aff_kyc_status = strtolower($aff['kyc_status'] ?? 'not_submitted');
$kyc_badge_tag = 'tag-blue';
$kyc_badge_text = 'KYC Not Submitted';
if ($aff_kyc_status === 'verified' || $aff_kyc_status === 'approved') {
    $kyc_badge_tag = 'tag-success';
    $kyc_badge_text = 'KYC Verified';
} elseif ($aff_kyc_status === 'pending' || $aff_kyc_status === 'under_review') {
    $kyc_badge_tag = 'tag-warning';
    $kyc_badge_text = 'KYC Under Review';
} elseif ($aff_kyc_status === 'rejected') {
    $kyc_badge_tag = 'tag-danger';
    $kyc_badge_text = 'KYC Rejected';
}

$status_tag = (strtolower($aff_status) === 'approved' || strtolower($aff_status) === 'active') ? 'tag-success' : (strtolower($aff_status) === 'pending' ? 'tag-warning' : 'tag-danger');

// Primary payout method
$primary_payout = !empty($aff_payout_methods) ? $aff_payout_methods[0] : null;
$primary_payout_details = $primary_payout ? parsePayoutDetails($primary_payout['account_details']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Affiliate Detail & Commercial Console</title>
    <link href='../../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        <?php include "../../components/theme-variables.php"; ?>

        body {
            font-family: var(--font-body) !important;
            background-color: #030712 !important;
            min-height: 100vh;
            color: #f8fafc !important;
            margin: 0; padding: 0; overflow-x: hidden;
        }

        .agent-header-bar {
            padding: 16px 24px;
            background: #090d16;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 14px;
        }

        .nav-tabs-custom {
            display: flex; align-items: center; gap: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 24px; margin-top: 16px; margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent; border: none;
            color: #94a3b8; font-weight: 700; font-size: 13px;
            padding: 12px 18px; border-bottom: 2px solid transparent;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease; white-space: nowrap;
        }
        .tab-btn:hover { color: #f8fafc; }
        .tab-btn.active {
            color: #10b981 !important;
            border-bottom-color: #10b981 !important;
        }

        /* Glass Card Container */
        .detail-card-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px; padding: 20px;
            backdrop-filter: blur(12px);
        }

        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 13px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #94a3b8; font-weight: 600; }
        .info-val { color: #ffffff; font-weight: 700; font-family: inherit; }

        /* Metric Box Grid */
        .metric-card {
            background: #090e1a;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px; padding: 16px 20px;
        }
        .metric-card.highlight {
            border-color: rgba(245, 158, 11, 0.4);
        }
        .metric-title { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-val { font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 4px; }

        /* Form Inputs */
        .dark-field-label {
            font-size: 10px !important; font-weight: 800 !important;
            text-transform: uppercase !important; letter-spacing: 1px !important;
            color: #94a3b8 !important; margin-bottom: 6px !important; display: block;
        }
        .dark-input, .dark-select {
            width: 100% !important; background-color: #020617 !important;
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            border-radius: 10px !important; padding: 10px 14px !important;
            color: #ffffff !important; font-size: 13px !important; font-weight: 600 !important;
            outline: none !important; transition: border-color 0.2s ease;
        }
        .dark-select option { background-color: #0f172a !important; color: #ffffff !important; }

        .btn-save-purple {
            background: #6366f1 !important; color: #ffffff !important;
            font-weight: 800 !important; font-size: 13px !important;
            padding: 10px 24px !important; border-radius: 10px !important;
            border: none !important; cursor: pointer;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3) !important;
        }
        .btn-save-purple:hover { background: #4f46e5 !important; }

        .tag {
            padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center;
        }
        .tag-success { background: rgba(16, 185, 129, 0.18); color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.4); }
        .tag-purple { background: rgba(168, 85, 247, 0.18); color: #c084fc !important; border: 1px solid rgba(168, 85, 247, 0.4); }
        .tag-blue { background: rgba(56, 189, 248, 0.18); color: #38bdf8 !important; border: 1px solid rgba(56, 189, 248, 0.4); }
        .tag-warning { background: rgba(245, 158, 11, 0.18); color: #fbbf24 !important; border: 1px solid rgba(245, 158, 11, 0.4); }
        .tag-danger { background: rgba(239, 68, 68, 0.18); color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.4); }

        /* Empty State Card */
        .empty-state-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px dashed rgba(255, 255, 255, 0.12);
            border-radius: 20px; padding: 50px 20px; text-align: center;
        }
        .empty-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 26px; color: #64748b; margin-bottom: 14px;
        }

        /* Activity Table */
        .r-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .r-table th {
            background: rgba(15, 23, 42, 0.85); padding: 14px 18px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #94a3b8 !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .r-table td {
            padding: 12px 18px; font-size: 13px; font-weight: 600;
            color: #ffffff !important; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .r-table tr:hover td { background: rgba(255, 255, 255, 0.03); }

        .text-cyan-code {
            color: #38bdf8 !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
        }

        /* Modern Modal Styling */
        .credit-modal-content {
            background: #090e1a !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 20px !important;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7), 0 0 35px rgba(16, 185, 129, 0.15) !important;
            overflow: hidden;
        }
        .credit-modal-header {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.5)) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 20px 24px !important;
        }
        .credit-icon-badge {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.2), rgba(2, 132, 199, 0.1));
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #38bdf8; display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-right: 14px;
        }
    </style>
</head>
<body style="background-color: #030712 !important;">
<div class="admin-layout-wrapper">
    <?php include "../../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar p-0">
        
        <!-- Header Bar -->
        <div class="agent-header-bar">
            <div class="d-flex align-items-center gap-3">
                <a href="../" onclick="if(document.referrer && document.referrer !== location.href){ history.back(); return false; }" class="text-slate-400 hover:text-white text-xl" style="text-decoration: none;" title="Go Back">
                    <i class='bx bx-left-arrow-alt fs-3'></i>
                </a>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h2 class="mb-0 fw-bold text-white fs-4" id="affiliateHeaderTitle"><?php echo htmlspecialchars($aff_name); ?></h2>
                        <span class="tag <?php echo $status_tag; ?>" id="affiliateHeaderStatus"><?php echo htmlspecialchars($aff_status); ?></span>
                        <span class="tag tag-purple" id="affiliateHeaderTier"><?php echo htmlspecialchars($aff_tier); ?> Tier</span>
                        <span class="tag <?php echo $kyc_badge_tag; ?>" id="affiliateHeaderKyc"><?php echo htmlspecialchars($kyc_badge_text); ?></span>

                        <!-- REFERRAL ORIGIN BADGE -->
                        <?php if ($parent_aff): ?>
                            <a href="?id=<?php echo (int)$parent_aff['id']; ?>" class="tag tag-blue text-decoration-none" title="Referred by Sponsor (Click to view profile)">
                                <i class='bx bx-user-voice me-1'></i> Ref: <?php echo htmlspecialchars($parent_aff['full_name'] ?: $parent_aff['company_name'] ?: $parent_aff['affiliate_code']); ?> (<?php echo htmlspecialchars($parent_aff['affiliate_code']); ?>)
                            </a>
                        <?php else: ?>
                            <span class="tag tag-purple" title="Direct registration without referral code">
                                <i class='bx bx-globe me-1'></i> Direct Partner (No Referrer)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-slate-400 text-xs mt-1 font-semibold" style="color: #94a3b8; font-size: 12px;">
                        <span class="text-cyan-code"><?php echo htmlspecialchars($aff_code); ?></span> &bull; <?php echo htmlspecialchars($aff_email ?: 'No email'); ?> &bull; <?php echo $aff_revshare; ?>% RevShare &bull; <?php echo $aff_sub_override; ?>% Sub-Override
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if (strtolower($aff_status) === 'pending' && !empty($aff['id'])): ?>
                    <button onclick="approveDetailAffiliate(<?php echo (int)$aff['id']; ?>, '<?php echo addslashes($aff_name); ?>')" class="btn btn-sm font-bold py-2 px-3 me-1" style="border-radius: 10px; font-size: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        <i class='bx bx-check-circle me-1'></i> Approve Partner
                    </button>
                <?php endif; ?>
                <button onclick="openBankModal()" class="btn btn-sm btn-outline-light font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.3); color: #38bdf8;">
                    <i class='bx bx-credit-card me-1'></i> Bank / Settlement
                </button>
                <button onclick="openChangePasswordModal(<?php echo (int)($aff['id'] ?? $aff_id); ?>, '<?php echo addslashes($aff_name); ?>')" class="btn btn-sm btn-outline-light font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.15);">
                    <i class='bx bx-key me-1'></i> Reset password
                </button>
                <button onclick="toggleDetailAffiliateStatus(<?php echo (int)($aff['id'] ?? $aff_id); ?>, '<?php echo strtolower($aff_status); ?>')" class="btn btn-sm btn-outline-light font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.15);" id="suspendBtn">
                    <?php echo (strtolower($aff_status) === 'suspended') ? 'Activate' : 'Suspend'; ?>
                </button>
                <button onclick="deleteDetailAffiliate(<?php echo (int)($aff['id'] ?? $aff_id); ?>)" class="btn btn-sm btn-danger font-bold py-2 px-3" style="border-radius: 10px; font-size: 12px; background: #ef4444; border: none;">
                    Delete
                </button>
            </div>
        </div>

        <!-- Tab Navigation Bar -->
        <div class="nav-tabs-custom">
            <button class="tab-btn active" onclick="switchTab('profile', this)">
                <i class='bx bx-user'></i> Profile & Hierarchy
            </button>
            <button class="tab-btn" onclick="switchTab('terms', this)">
                <i class='bx bx-slider-alt'></i> Terms & Commission
            </button>
            <button class="tab-btn" onclick="switchTab('links', this)">
                <i class='bx bx-link-external'></i> Tracking Links (<?php echo count($aff_links); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('subaffiliates', this)">
                <i class='bx bx-git-repo-forked'></i> Sub-Affiliates (<?php echo count($sub_affiliates); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('referrals', this)">
                <i class='bx bx-group'></i> Referred Users (<?php echo count($aff_referrals); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('ledger', this)">
                <i class='bx bx-wallet'></i> Ledger (<?php echo count($aff_commissions); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('payouts', this)">
                <i class='bx bx-money-withdraw'></i> Payouts (<?php echo count($aff_payouts); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('kyc', this)">
                <i class='bx bx-id-card'></i> KYC (<?php echo count($kyc_docs); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('tickets', this)">
                <i class='bx bx-support'></i> Tickets (<?php echo count($aff_tickets); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('activity', this)">
                <i class='bx bx-pulse'></i> Activity Log
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="px-4 pb-5">

            <?php if (!$is_aff_found): ?>
                <div class="empty-state-box mb-4">
                    <div class="empty-icon"><i class='bx bx-error-circle'></i></div>
                    <h6 class="fw-bold text-white mb-1">Affiliate record not found</h6>
                    <p class="text-slate-400 text-xs mb-0">No affiliate matching ID <code><?php echo htmlspecialchars($aff_id); ?></code> was found. Please return to the <a href="../" class="text-cyan-code">Affiliate Directory</a>.</p>
                </div>
            <?php endif; ?>

            <!-- TAB 1: Profile & Hierarchy -->
            <div id="tab-profile" class="tab-content-item">
                <div class="row g-4">
                    <!-- Left Col -->
                    <div class="col-lg-5">
                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Account Details</h6>
                            <div class="info-row"><span class="info-label">Full Name:</span><span class="info-val"><?php echo htmlspecialchars($aff_full_name ?: 'Not Provided'); ?></span></div>
                            <div class="info-row"><span class="info-label">Company / Brand:</span><span class="info-val"><?php echo htmlspecialchars($aff_company_name ?: 'Individual Partner'); ?></span></div>
                            <div class="info-row"><span class="info-label">Affiliate Code:</span><span class="info-val text-cyan-code"><?php echo htmlspecialchars($aff_code); ?></span></div>
                            <div class="info-row"><span class="info-label">Email:</span><span class="info-val text-cyan-code"><?php echo htmlspecialchars($aff_email ?: 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Phone:</span><span class="info-val"><?php echo htmlspecialchars($aff_phone ?: 'Not Provided'); ?></span></div>
                            <div class="info-row"><span class="info-label">Website / Channel:</span><span class="info-val"><?php echo htmlspecialchars($aff_website ?: 'Direct / Social'); ?></span></div>
                            <div class="info-row"><span class="info-label">Partner Tier:</span><span class="info-val"><?php echo htmlspecialchars($aff_tier); ?></span></div>
                            <div class="info-row"><span class="info-label">Joined:</span><span class="info-val"><?php echo htmlspecialchars($aff_joined); ?></span></div>
                        </div>

                        <!-- REFERRAL ORIGIN & NETWORK CARD -->
                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">
                                <i class='bx bx-git-repo-forked text-cyan-code me-1.5'></i>Referral & Network Hierarchy
                            </h6>

                            <div class="info-row">
                                <span class="info-label">Acquisition Channel:</span>
                                <span class="info-val">
                                    <?php if ($parent_aff): ?>
                                        <span class="tag tag-blue">Affiliate Referral (2-Tier)</span>
                                    <?php else: ?>
                                        <span class="tag tag-purple">Direct / Organic</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">Referred By (Sponsor):</span>
                                <span class="info-val">
                                    <?php if ($parent_aff): ?>
                                        <a href="?id=<?php echo (int)$parent_aff['id']; ?>" class="text-cyan-code text-decoration-none fw-bold" title="Click to view Sponsor details">
                                            <i class='bx bx-user-check me-1'></i> <?php echo htmlspecialchars($parent_aff['full_name'] ?: $parent_aff['company_name'] ?: $parent_aff['affiliate_code']); ?> (@<?php echo htmlspecialchars($parent_aff['affiliate_code']); ?>)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400">Direct Root Partner (No Referrer)</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($parent_aff): ?>
                                <div class="info-row"><span class="info-label">Sponsor Email:</span><span class="info-val font-mono text-xs"><?php echo htmlspecialchars($parent_aff['email'] ?: 'N/A'); ?></span></div>
                                <div class="info-row"><span class="info-label">Sponsor Phone:</span><span class="info-val"><?php echo htmlspecialchars($parent_aff['phone'] ?: 'N/A'); ?></span></div>
                            <?php endif; ?>

                            <div class="info-row">
                                <span class="info-label">Recruited Sub-Affiliates:</span>
                                <span class="info-val text-emerald-400 fw-bold">
                                    <?php echo count($sub_affiliates); ?> Downline Partners
                                </span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">Tier-2 Override Rate:</span>
                                <span class="info-val text-amber-400 fw-bold"><?php echo $aff_sub_override; ?>% on Sub-Earnings</span>
                            </div>
                        </div>

                        <!-- BANKING & PAYOUT ACCOUNTS CARD -->
                        <div class="detail-card-box mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-white mb-0" style="font-size: 15px;">
                                    <i class='bx bx-building-house text-emerald-400 me-1.5'></i>Banking & Settlement Details
                                </h6>
                                <button type="button" onclick="openBankModal()" class="btn btn-xs btn-outline-light font-bold py-1 px-2.5" style="border-radius: 6px; font-size: 11px; background: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.3); color: #38bdf8;">
                                    <i class='bx bx-edit-alt'></i> Edit Bank
                                </button>
                            </div>

                            <?php if (!empty($aff_payout_methods)): ?>
                                <?php foreach ($aff_payout_methods as $pm): 
                                    $pDetails = parsePayoutDetails($pm['account_details']);
                                    $mType = strtolower($pm['method_type'] ?? 'bank_transfer');
                                    $bankName = $pDetails['bank_name'] ?? $pDetails['bank'] ?? '';
                                    $accNo = $pDetails['account_number'] ?? $pDetails['account_no'] ?? $pDetails['upi_id'] ?? $pDetails['crypto_address'] ?? $pDetails['wallet_address'] ?? '';
                                    $accHolder = $pDetails['account_name'] ?? $pDetails['account_holder'] ?? $pDetails['holder_name'] ?? $aff_full_name;
                                    $ifsc = $pDetails['ifsc_code'] ?? $pDetails['ifsc'] ?? $pDetails['swift_code'] ?? $pDetails['branch_name'] ?? '';
                                ?>
                                    <div class="p-3 rounded border mb-2" style="background: #090e1a; border-color: rgba(255,255,255,0.08) !important;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class='bx <?php echo ($mType === 'upi' ? 'bx-mobile' : ($mType === 'crypto' ? 'bx-bitcoin' : 'bx-building')); ?> text-emerald-400 fs-5'></i>
                                                <strong class="text-white text-xs"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $pm['method_type'] ?? 'Bank Transfer'))); ?></strong>
                                            </div>
                                            <?php if (!empty($pm['is_primary'])): ?>
                                                <span class="tag tag-success">Primary Settlement</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($bankName)): ?>
                                            <div class="info-row py-1"><span class="info-label">Bank Name:</span><span class="info-val"><?php echo htmlspecialchars($bankName); ?></span></div>
                                        <?php endif; ?>
                                        <?php if (!empty($accHolder)): ?>
                                            <div class="info-row py-1"><span class="info-label">Beneficiary:</span><span class="info-val"><?php echo htmlspecialchars($accHolder); ?></span></div>
                                        <?php endif; ?>
                                        <?php if (!empty($accNo)): ?>
                                            <div class="info-row py-1">
                                                <span class="info-label"><?php echo ($mType === 'upi' ? 'UPI ID' : ($mType === 'crypto' ? 'Wallet' : 'Account No')); ?>:</span>
                                                <span class="info-val text-cyan-code d-flex align-items-center gap-1.5">
                                                    <?php echo htmlspecialchars($accNo); ?>
                                                    <i class='bx bx-copy text-slate-400' style="cursor: pointer;" onclick="navigator.clipboard.writeText('<?php echo addslashes($accNo); ?>'); Swal.fire({ icon: 'success', title: 'Copied!', text: 'Account number copied', timer: 1200, showConfirmButton: false });"></i>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($ifsc)): ?>
                                            <div class="info-row py-1"><span class="info-label">IFSC / Branch:</span><span class="info-val text-cyan-code"><?php echo htmlspecialchars($ifsc); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-3 rounded border text-center" style="background: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.08) !important;">
                                    <i class='bx bx-credit-card-front text-slate-500 fs-2 mb-1'></i>
                                    <div class="text-white text-xs fw-bold">No Bank Account Registered</div>
                                    <div class="text-slate-400 text-xs mt-1 mb-2">No settlement bank method saved yet.</div>
                                    <button type="button" onclick="openBankModal()" class="btn btn-sm btn-outline-light font-bold py-1 px-3" style="border-radius: 8px; font-size: 11px;">
                                        <i class='bx bx-plus me-1'></i> Add Bank Account
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="detail-card-box">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Restrictions & Compliance</h6>
                            <div class="info-row"><span class="info-label">Account Status</span><span class="info-val <?php echo (strtolower($aff_status) === 'active' || strtolower($aff_status) === 'approved') ? 'text-emerald-400' : 'text-amber-400'; ?>"><?php echo htmlspecialchars($aff_status); ?></span></div>
                            <div class="info-row"><span class="info-label">Identity Verification</span><span class="info-val <?php echo ($aff_kyc_status === 'verified') ? 'text-emerald-400' : 'text-slate-400'; ?>"><?php echo htmlspecialchars($kyc_badge_text); ?></span></div>
                            <div class="info-row"><span class="info-label">Onboarding Status</span><span class="info-val"><?php echo htmlspecialchars($aff_onboarding); ?></span></div>
                        </div>
                    </div>

                    <!-- Right Col -->
                    <div class="col-lg-7">
                        <!-- Today's Live Player Activity & Estimated RevShare Banner -->
                        <div class="detail-card-box mb-4" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 27, 75, 0.85)); border: 1px solid rgba(99, 102, 241, 0.35); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);">
                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width: 32px; height: 32px; border-radius: 10px; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.3); display: flex; align-items: center; justify-content: center; color: #eab308;">
                                        <i class='bx bxs-zap fs-5'></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="fw-bold text-white mb-0" style="font-size: 15px;">Today's Live Player Activity & Estimated RevShare</h6>
                                            <span style="font-size: 10px; font-weight: 800; background: rgba(234, 179, 8, 0.15); color: #fbbf24; padding: 2px 8px; border-radius: 20px; border: 1px solid rgba(234, 179, 8, 0.3);">Real-Time Feed</span>
                                        </div>
                                        <div class="text-slate-400 text-xs" style="font-size: 11px; color: #94a3b8;">Tracks live player spins, losses & net platform profit. Commission finalizes into partner wallet nightly at 01:00 UTC (Rule 1).</div>
                                    </div>
                                </div>
                                <div style="font-size: 11px; font-weight: 700; color: #cbd5e1; background: rgba(255, 255, 255, 0.06); padding: 4px 12px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                    Active Players Today: <strong class="text-white"><?php echo $live_stats['active_players_today']; ?></strong>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Live Turnover -->
                                <div class="col-md-3 col-6">
                                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 14px 16px;">
                                        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px;">Today's Live Turnover</div>
                                        <div style="font-size: 18px; font-weight: 900; color: #ffffff; margin-top: 4px;">₹<?php echo number_format($live_stats['today_turnover'], 2); ?></div>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php echo $live_stats['total_rounds_today']; ?> rounds played</div>
                                    </div>
                                </div>

                                <!-- Player Winnings -->
                                <div class="col-md-3 col-6">
                                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 14px 16px;">
                                        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #10b981; letter-spacing: 0.5px;">Player Winnings (Today)</div>
                                        <div style="font-size: 18px; font-weight: 900; color: #34d399; margin-top: 4px;">₹<?php echo number_format($live_stats['today_player_wins'], 2); ?></div>
                                        <div style="font-size: 11px; color: #10b981; margin-top: 2px;">Total paid out to players</div>
                                    </div>
                                </div>

                                <!-- Net Player Losses (GGR) -->
                                <div class="col-md-3 col-6">
                                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(244, 63, 94, 0.2); border-radius: 12px; padding: 14px 16px;">
                                        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #f43f5e; letter-spacing: 0.5px;">Net Player Losses (GGR)</div>
                                        <div style="font-size: 18px; font-weight: 900; color: #fb7185; margin-top: 4px;">₹<?php echo number_format($live_stats['today_player_losses'], 2); ?></div>
                                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Net platform profit today</div>
                                    </div>
                                </div>

                                <!-- Live Est. RevShare -->
                                <div class="col-md-3 col-6">
                                    <div style="background: rgba(88, 28, 135, 0.25); border: 1px solid rgba(168, 85, 247, 0.35); border-radius: 12px; padding: 14px 16px; position: relative;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #c084fc; letter-spacing: 0.5px;">Live Est. RevShare (<?php echo $aff_revshare; ?>%)</div>
                                            <span style="font-size: 9px; font-weight: 800; background: rgba(234, 179, 8, 0.2); color: #fbbf24; padding: 1px 6px; border-radius: 10px;">Pending Settlement</span>
                                        </div>
                                        <div style="font-size: 18px; font-weight: 900; color: #facc15; margin-top: 4px;">+₹<?php echo number_format($live_stats['today_estimated_revshare'], 2); ?></div>
                                        <div style="font-size: 10px; color: #cbd5e1; margin-top: 2px;">
                                            Settled: ₹<?php echo number_format($live_stats['already_settled_today'], 2); ?> &bull; Pending: ₹<?php echo number_format($live_stats['unsettled_revshare'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($live_recent_bets)): ?>
                                <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span style="font-size: 12px; font-weight: 800; color: #e2e8f0;"><i class='bx bx-dice-5 text-cyan-code me-1'></i> Recent Live Bets by Referred Players</span>
                                        <span class="text-slate-400" style="font-size: 11px;">Last <?php echo count($live_recent_bets); ?> rounds</span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm text-white mb-0" style="font-size: 12px;">
                                            <thead>
                                                <tr style="color: #94a3b8; border-color: rgba(255, 255, 255, 0.08);">
                                                    <th>Round ID</th>
                                                    <th>Player</th>
                                                    <th>Game</th>
                                                    <th>Bet Cost</th>
                                                    <th>Profit</th>
                                                    <th>House Margin</th>
                                                    <th>Status</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($live_recent_bets as $bet): 
                                                    $cost = (float)$bet['tbl_match_cost'];
                                                    $profit = (float)$bet['tbl_match_profit'];
                                                    $margin = $cost - $profit;
                                                    $uName = ($bet['tbl_user_name'] ?? '') ?: (($bet['tbl_full_name'] ?? '') ?: ('Player #' . ($bet['tbl_user_id'] ?? '')));
                                                    $game = ($bet['tbl_game_name'] ?? '') ?: (($bet['tbl_project_name'] ?? '') ?: 'Casino');
                                                ?>
                                                    <tr style="border-color: rgba(255, 255, 255, 0.05);">
                                                        <td class="font-mono text-cyan-code">#<?php echo $bet['id']; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($uName); ?></strong></td>
                                                        <td><?php echo htmlspecialchars(ucfirst($game)); ?></td>
                                                        <td>₹<?php echo number_format($cost, 2); ?></td>
                                                        <td class="<?php echo $profit > 0 ? 'text-emerald-400' : 'text-slate-400'; ?>">₹<?php echo number_format($profit, 2); ?></td>
                                                        <td class="<?php echo $margin >= 0 ? 'text-rose-400' : 'text-emerald-400'; ?>">
                                                            <?php echo ($margin >= 0 ? '+' : '') . '₹' . number_format($margin, 2); ?>
                                                        </td>
                                                        <td>
                                                            <span class="tag <?php echo in_array($bet['tbl_match_status'], ['loss', 'lost']) || $profit == 0 ? 'tag-red' : 'tag-success'; ?>">
                                                                <?php echo strtoupper($bet['tbl_match_status'] ?: ($profit > 0 ? 'WIN' : 'LOSS')); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-slate-400" style="font-size: 11px;"><?php echo !empty($bet['created_at']) ? date('H:i:s', strtotime($bet['created_at'])) : ($bet['tbl_time_stamp'] ?: 'Today'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Commercial Earnings & Metrics</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Lifetime Earnings</div>
                                        <div class="metric-val" style="color: #34d399;">₹ <?php echo number_format($lifetime_earnings, 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Pending Unpaid</div>
                                        <div class="metric-val" style="color: #fbbf24;">₹ <?php echo number_format($pending_balance, 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Referred Signups</div>
                                        <div class="metric-val"><?php echo number_format($referral_count); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card highlight">
                                        <div class="metric-title" style="color: #fbbf24;">First Time Depositors</div>
                                        <div class="metric-val" style="color: #fbbf24;"><?php echo number_format($ftd_count); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">Deal Model</div>
                                        <div class="metric-val" style="font-size: 18px; text-transform: uppercase;"><?php echo htmlspecialchars($aff_deal_type); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-card">
                                        <div class="metric-title">RevShare / CPA</div>
                                        <div class="metric-val" style="font-size: 18px; color: #38bdf8;"><?php echo $aff_revshare; ?>% / ₹<?php echo number_format($aff_cpa); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-card-box mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Active Tracking Links</h6>
                            <?php if (count($aff_links) > 0): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($aff_links as $link): ?>
                                    <div class="p-3 rounded border d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: #090e1a; border-color: rgba(255,255,255,0.08) !important;">
                                        <div>
                                            <div class="text-cyan-code fw-bold text-xs"><?php echo htmlspecialchars($link['tracking_url'] ?? 'https://' . strtolower($APP_NAME) . '.site/?ref=' . ($link['code'] ?? $aff_code)); ?></div>
                                            <div class="text-slate-400 text-xs mt-1">Campaign: <strong class="text-white"><?php echo htmlspecialchars($link['name'] ?? 'Default'); ?></strong> &bull; Clicks: <strong class="text-white"><?php echo number_format($link['clicks_count'] ?? 0); ?></strong></div>
                                        </div>
                                        <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($link['tracking_url'] ?? ''); ?>'); Swal.fire({ icon: 'success', title: 'Copied!', text: 'Referral link copied to clipboard', timer: 1500, showConfirmButton: false });" class="btn btn-sm btn-outline-light font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                            <i class='bx bx-copy me-1'></i> Copy
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 rounded border d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: #090e1a; border-color: rgba(255,255,255,0.08) !important;">
                                    <div>
                                        <div class="text-cyan-code fw-bold text-xs">https://<?php echo strtolower($APP_NAME); ?>.site/?ref=<?php echo htmlspecialchars($aff_code); ?></div>
                                        <div class="text-slate-400 text-xs mt-1">Campaign: Default Organic &bull; Clicks: 0 &bull; Signups: <?php echo number_format($referral_count); ?></div>
                                    </div>
                                    <button onclick="navigator.clipboard.writeText('https://<?php echo strtolower($APP_NAME); ?>.site/?ref=<?php echo htmlspecialchars($aff_code); ?>'); Swal.fire({ icon: 'success', title: 'Copied!', text: 'Referral link copied to clipboard', timer: 1500, showConfirmButton: false });" class="btn btn-sm btn-outline-light font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                        <i class='bx bx-copy me-1'></i> Copy
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Terms & Commission -->
            <div id="tab-terms" class="tab-content-item d-none">
                <div class="detail-card-box max-w-4xl mx-auto" style="max-width: 750px;">
                    <form onsubmit="saveAffiliateTerms(event)">
                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Commercial Terms & Deal Structure</h6>
                            <p class="text-slate-400 text-xs mb-3" style="color: #94a3b8;">Custom commercial overrides applied to this partner. Changes apply to future commissions.</p>
                            <div class="mb-3">
                                <label class="dark-field-label">DEAL MODEL TYPE</label>
                                <select id="termsDealType" class="dark-select">
                                    <option value="revenue_share" <?php echo in_array($aff_deal_type, ['revshare', 'revenue_share']) ? 'selected' : ''; ?>>Revenue Share (NGR %)</option>
                                    <option value="cpa" <?php echo ($aff_deal_type === 'cpa') ? 'selected' : ''; ?>>Flat CPA (Fee per FTD)</option>
                                    <option value="hybrid" <?php echo ($aff_deal_type === 'hybrid') ? 'selected' : ''; ?>>Hybrid (RevShare + CPA)</option>
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">REVENUE SHARE (%)</label>
                                    <input type="number" id="termsRevshare" class="dark-input" value="<?php echo $aff_revshare; ?>" min="0" max="100">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">CPA REWARD (₹)</label>
                                    <input type="number" id="termsCpa" class="dark-input" value="<?php echo $aff_cpa; ?>" min="0" step="10">
                                </div>
                            </div>
                        </div>

                        <!-- REFERRAL UPLINE SPONSOR & 2-TIER OVERRIDE -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Referral Hierarchy & 2-Tier Network</h6>
                            <p class="text-slate-400 text-xs mb-3" style="color: #94a3b8;">Specify who referred this affiliate into the platform and set their Tier-2 sub-affiliate override rate.</p>
                            <div class="row g-3 mb-3">
                                <div class="col-7">
                                    <label class="dark-field-label">REFERRED BY / UPLINE SPONSOR</label>
                                    <select id="termsParentId" class="dark-select">
                                        <option value="0" <?php echo empty($aff['parent_id']) ? 'selected' : ''; ?>>Direct / Organic — Root Partner (No Referrer)</option>
                                        <?php foreach ($all_affiliates as $cand): ?>
                                            <option value="<?php echo (int)$cand['id']; ?>" <?php echo (!empty($aff['parent_id']) && (int)$aff['parent_id'] === (int)$cand['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cand['full_name'] ?: $cand['company_name'] ?: $cand['affiliate_code']); ?> (<?php echo htmlspecialchars($cand['affiliate_code']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="dark-field-label">2-TIER OVERRIDE RATE (%)</label>
                                    <input type="number" step="0.5" id="termsSubOverride" class="dark-input" value="<?php echo $aff_sub_override; ?>" min="0" max="50">
                                </div>
                            </div>
                        </div>

                        <!-- BANKING DETAILS IN TERMS -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Primary Settlement Bank Account</h6>
                            <p class="text-slate-400 text-xs mb-3" style="color: #94a3b8;">Bank details used for paying out affiliate earnings and commission withdrawals.</p>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="dark-field-label">PAYOUT METHOD TYPE</label>
                                    <select id="termsBankMethodType" class="dark-select">
                                        <option value="bank_transfer" <?php echo ($primary_payout && $primary_payout['method_type'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Account (NEFT / IMPS / RTGS)</option>
                                        <option value="UPI" <?php echo ($primary_payout && $primary_payout['method_type'] === 'UPI') ? 'selected' : ''; ?>>UPI (VPA)</option>
                                        <option value="crypto" <?php echo ($primary_payout && $primary_payout['method_type'] === 'crypto') ? 'selected' : ''; ?>>Crypto (USDT TRC-20)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">BANK / INSTITUTION NAME</label>
                                    <input type="text" id="termsBankName" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['bank_name'] ?? ''); ?>" placeholder="e.g. HDFC Bank">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="dark-field-label">ACCOUNT / BENEFICIARY NAME</label>
                                    <input type="text" id="termsAccountName" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['account_name'] ?? $aff_full_name); ?>" placeholder="Beneficiary Legal Name">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">ACCOUNT NO / UPI ID / WALLET</label>
                                    <input type="text" id="termsAccountNumber" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['account_number'] ?? $primary_payout_details['upi_id'] ?? $primary_payout_details['crypto_address'] ?? ''); ?>" placeholder="Account Number or UPI ID">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">IFSC CODE / SWIFT</label>
                                    <input type="text" id="termsIfsc" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['ifsc_code'] ?? ''); ?>" placeholder="e.g. HDFC0001234">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">BRANCH / NETWORK</label>
                                    <input type="text" id="termsBranch" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['branch_name'] ?? $primary_payout_details['network'] ?? ''); ?>" placeholder="e.g. Mumbai Main / TRC-20">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-1" style="font-size: 15px;">Partner Tier & Status</h6>
                            <p class="text-slate-400 text-xs mb-3" style="color: #94a3b8;">Manage affiliate partner clearance and priority standing.</p>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">PARTNER TIER</label>
                                    <select id="termsTier" class="dark-select">
                                        <option value="bronze" <?php echo strtolower($aff_tier) === 'bronze' ? 'selected' : ''; ?>>Bronze</option>
                                        <option value="silver" <?php echo strtolower($aff_tier) === 'silver' ? 'selected' : ''; ?>>Silver</option>
                                        <option value="gold" <?php echo strtolower($aff_tier) === 'gold' ? 'selected' : ''; ?>>Gold</option>
                                        <option value="platinum" <?php echo strtolower($aff_tier) === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">ACCOUNT STATUS</label>
                                    <select id="termsStatus" class="dark-select">
                                        <option value="active" <?php echo (strtolower($aff_status) === 'active' || strtolower($aff_status) === 'approved') ? 'selected' : ''; ?>>Active</option>
                                        <option value="pending" <?php echo strtolower($aff_status) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="suspended" <?php echo strtolower($aff_status) === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="closed" <?php echo strtolower($aff_status) === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">Contact & Entity Information</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="dark-field-label">FULL LEGAL NAME</label>
                                    <input type="text" id="termsFullName" class="dark-input" value="<?php echo htmlspecialchars($aff_full_name); ?>" placeholder="Legal Name">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">COMPANY / BUSINESS</label>
                                    <input type="text" id="termsCompanyName" class="dark-input" value="<?php echo htmlspecialchars($aff_company_name); ?>" placeholder="Company Name">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="dark-field-label">PHONE NUMBER</label>
                                    <input type="text" id="termsPhone" class="dark-input" value="<?php echo htmlspecialchars($aff_phone); ?>" placeholder="Phone Number">
                                </div>
                                <div class="col-6">
                                    <label class="dark-field-label">WEBSITE / CHANNEL URL</label>
                                    <input type="text" id="termsWebsite" class="dark-input" value="<?php echo htmlspecialchars($aff_website); ?>" placeholder="https://...">
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" id="saveTermsBtn" class="btn-save-purple">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: Sub-Affiliates (Downline Network) -->
            <div id="tab-subaffiliates" class="tab-content-item d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-white mb-0" style="font-size: 15px;">
                        <i class='bx bx-git-repo-forked text-cyan-code me-1.5'></i>Sub-Affiliates Recruited by <?php echo htmlspecialchars($aff_name); ?>
                    </h6>
                    <span class="tag tag-blue"><?php echo count($sub_affiliates); ?> Downline Partners</span>
                </div>

                <?php if (!empty($sub_affiliates)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>Sub-Partner</th>
                                    <th>Code</th>
                                    <th>Tier / Status</th>
                                    <th>Players Recruited</th>
                                    <th>Sub-Volume (₹)</th>
                                    <th>Override Commission</th>
                                    <th>Joined Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sub_affiliates as $sub): 
                                    $subEarnings = (float)($sub['sub_earnings'] ?? 0);
                                    $overrideEarned = round($subEarnings * ($aff_sub_override / 100), 2);
                                    $subSt = strtolower($sub['status'] ?? 'pending');
                                    $subTag = ($subSt === 'active' || $subSt === 'approved') ? 'tag-success' : 'tag-warning';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?php echo htmlspecialchars($sub['full_name'] ?: ($sub['company_name'] ?: 'Partner #' . $sub['id'])); ?></div>
                                            <div class="text-slate-400 text-xs"><?php echo htmlspecialchars($sub['email'] ?: 'No email'); ?></div>
                                        </td>
                                        <td><span class="text-cyan-code fw-bold"><?php echo htmlspecialchars($sub['affiliate_code']); ?></span></td>
                                        <td>
                                            <span class="tag tag-purple"><?php echo htmlspecialchars(ucfirst($sub['tier'] ?? 'Bronze')); ?></span>
                                            <span class="tag <?php echo $subTag; ?> ms-1"><?php echo htmlspecialchars(ucfirst($subSt)); ?></span>
                                        </td>
                                        <td class="fw-bold text-white"><?php echo number_format((int)($sub['total_players'] ?? 0)); ?></td>
                                        <td>₹ <?php echo number_format($subEarnings, 2); ?></td>
                                        <td class="text-emerald-400 fw-bold">
                                            ₹ <?php echo number_format($overrideEarned, 2); ?>
                                            <span class="text-slate-400 text-xs font-normal ms-1">(<?php echo $aff_sub_override; ?>%)</span>
                                        </td>
                                        <td class="text-slate-400"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a href="?id=<?php echo (int)$sub['id']; ?>" class="btn btn-sm btn-outline-light font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                                <i class='bx bx-show me-1'></i> View Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-git-repo-forked'></i></div>
                        <h6 class="fw-bold text-white mb-1">No recruited sub-affiliates</h6>
                        <p class="text-slate-400 text-xs mb-0">When other partners sign up using this affiliate's referral link or sponsor code, they will appear in this 2-tier downline network.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 4: Referred Users -->
            <div id="tab-referrals" class="tab-content-item d-none">
                <?php if (!empty($aff_referrals)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Referred Campaign / Link</th>
                                    <th>Signup Date</th>
                                    <th>First Deposit (FTD)</th>
                                    <th>CPA Qualification</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aff_referrals as $ref): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?php echo htmlspecialchars($ref['tbl_full_name'] ?: ($ref['tbl_user_name'] ?: ('User #' . $ref['user_id']))); ?></div>
                                            <div class="text-cyan-code text-xs">@<?php echo htmlspecialchars($ref['tbl_user_name'] ?: 'user'); ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($ref['link_id'])): ?>
                                                <button type="button" onclick="openLinkDetailModal(<?php echo (int)$ref['link_id']; ?>)" class="btn btn-xs py-1 px-2 font-mono text-xs d-inline-flex align-items-center gap-1" style="border-radius: 6px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; cursor: pointer;">
                                                    <i class='bx bx-link-alt'></i> <?php echo htmlspecialchars($ref['link_code'] ?: ($ref['link_name'] ?: ('Link #' . $ref['link_id']))); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">Direct Code</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-slate-400" style="color: #94a3b8;"><?php echo htmlspecialchars($ref['signup_at'] ?? 'N/A'); ?></td>
                                        <td class="text-emerald-400 fw-bold">₹ <?php echo number_format((float)($ref['first_deposit_amount'] ?? 0), 2); ?></td>
                                        <td>
                                            <?php if (!empty($ref['is_cpa_qualified'])): ?>
                                                <span class="tag tag-success">Qualified</span>
                                            <?php else: ?>
                                                <span class="tag tag-blue">Pending FTD</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="tag tag-success"><?php echo htmlspecialchars(strtoupper($ref['status'] ?? 'ACTIVE')); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-group'></i></div>
                        <h6 class="fw-bold text-white mb-1">No referred players yet</h6>
                        <p class="text-slate-400 text-xs mb-0">When players register using this affiliate's tracking code, they will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Tracking Links Section -->
            <div id="tab-links" class="tab-content-item d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-white mb-0" style="font-size: 15px;">
                        <i class='bx bx-link-external text-cyan-code me-1.5'></i>Active Campaign Tracking Links (<?php echo count($aff_links); ?>)
                    </h6>
                    <span class="tag tag-blue"><?php echo count($aff_links); ?> Active Links</span>
                </div>

                <?php if (!empty($aff_links)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name / Code</th>
                                    <th>Target Path</th>
                                    <th>Live Tracking URL</th>
                                    <th>Clicks</th>
                                    <th>Signups</th>
                                    <th>FTDs</th>
                                    <th>FTD Volume (₹)</th>
                                    <th>Commission Earned (₹)</th>
                                    <th>Created Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aff_links as $link): 
                                    $lId = (int)$link['id'];
                                    $lCode = htmlspecialchars($link['code']);
                                    $lName = htmlspecialchars($link['name'] ?? $link['code']);
                                    $lTarget = htmlspecialchars($link['target_path'] ?? '/register');
                                    $lClicks = (int)($link['clicks_count'] ?? 0);
                                    $lSignups = (int)($link['signups_count'] ?? 0);
                                    $lFtds = (int)($link['ftds_count'] ?? 0);
                                    $lFtdVol = (float)($link['total_ftd_amount'] ?? 0);
                                    $lComm = (float)($link['total_link_commission'] ?? 0);
                                    $lDate = !empty($link['created_at']) ? date('M d, Y', strtotime($link['created_at'])) : 'N/A';
                                    $fullUrl = "http://localhost:5173" . ($lTarget === '/' ? '/register' : $lTarget) . "?ref=" . $lCode;
                                    if (!empty($link['sub_id'])) $fullUrl .= "&sub=" . urlencode($link['sub_id']);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?php echo $lName; ?></div>
                                            <span class="text-cyan-code text-xs font-mono fw-bold"><?php echo $lCode; ?></span>
                                        </td>
                                        <td><span class="tag tag-blue"><?php echo $lTarget; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5">
                                                <span class="text-slate-400 font-mono text-xs text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($fullUrl); ?>"><?php echo htmlspecialchars($fullUrl); ?></span>
                                                <button type="button" class="btn btn-xs btn-outline-light py-0 px-1.5" onclick="navigator.clipboard.writeText('<?php echo addslashes($fullUrl); ?>'); Swal.fire({ icon: 'success', title: 'Copied!', text: 'Tracking URL copied to clipboard', timer: 1200, showConfirmButton: false });">
                                                    <i class='bx bx-copy'></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-white"><?php echo number_format($lClicks); ?></td>
                                        <td class="text-cyan-code fw-bold"><?php echo number_format($lSignups); ?></td>
                                        <td class="text-emerald-400 fw-bold"><?php echo number_format($lFtds); ?></td>
                                        <td class="text-emerald-400 fw-bold">₹ <?php echo number_format($lFtdVol, 2); ?></td>
                                        <td class="text-emerald-400 fw-bold">₹ <?php echo number_format($lComm, 2); ?></td>
                                        <td class="text-slate-400"><?php echo $lDate; ?></td>
                                        <td class="text-end">
                                            <button type="button" onclick="openLinkDetailModal(<?php echo $lId; ?>)" class="btn btn-sm font-bold py-1 px-2.5" style="border-radius: 8px; font-size: 11px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8;">
                                                <i class='bx bx-show me-1'></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-link-external'></i></div>
                        <h6 class="fw-bold text-white mb-1">No tracking links created</h6>
                        <p class="text-slate-400 text-xs mb-0">When campaign tracking links are generated, they will be listed here with performance statistics.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 5: Commission Ledger -->
            <div id="tab-ledger" class="tab-content-item d-none">
                <?php if (!empty($aff_commissions)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Base Kind / Amount</th>
                                    <th>Rate (%)</th>
                                    <th>Commission (₹)</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aff_commissions as $entry): ?>
                                    <tr>
                                        <td>
                                            <span class="tag tag-purple"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $entry['entry_type'] ?? 'Commission'))); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($entry['base_kind'] ?? 'NGR'); ?></div>
                                            <div class="text-slate-400 text-xs">₹ <?php echo number_format((float)($entry['base_amount'] ?? 0), 2); ?></div>
                                        </td>
                                        <td class="text-slate-300"><?php echo floatval($entry['rate'] ?? 0); ?>%</td>
                                        <td class="text-emerald-400 fw-bold">₹ <?php echo number_format((float)($entry['amount'] ?? 0), 2); ?></td>
                                        <td>
                                            <?php 
                                            $st = strtolower($entry['status'] ?? 'pending');
                                            $tagClass = ($st === 'approved' || $st === 'settled' || $st === 'paid') ? 'tag-success' : ($st === 'pending' ? 'tag-warning' : 'tag-danger');
                                            ?>
                                            <span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars(ucfirst($st)); ?></span>
                                        </td>
                                        <td class="text-slate-400" style="color: #94a3b8;"><?php echo htmlspecialchars($entry['created_at'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-wallet'></i></div>
                        <h6 class="fw-bold text-white mb-1">No commission entries recorded</h6>
                        <p class="text-slate-400 text-xs mb-0">Generated RevShare and CPA ledger credits will be tracked here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 6: Payouts -->
            <div id="tab-payouts" class="tab-content-item d-none">
                <div class="row g-4 mb-4">
                    <div class="col-lg-7">
                        <div class="detail-card-box p-0 overflow-auto h-100">
                            <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-white mb-0" style="font-size: 14px;">Payout Request History</h6>
                                <span class="tag tag-blue"><?php echo count($aff_payouts); ?> Requests</span>
                            </div>
                            <?php if (!empty($aff_payouts)): ?>
                                <table class="r-table">
                                    <thead>
                                        <tr>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Requested Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($aff_payouts as $payout): 
                                            $pst = strtolower($payout['status'] ?? 'requested');
                                            $pTag = ($pst === 'completed' || $pst === 'approved' || $pst === 'paid') ? 'tag-success' : ($pst === 'requested' || $pst === 'pending' ? 'tag-warning' : 'tag-danger');
                                        ?>
                                            <tr>
                                                <td class="text-emerald-400 fw-bold">₹ <?php echo number_format((float)($payout['amount'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars($payout['payout_method_type'] ?? 'Bank / UPI'); ?></td>
                                                <td><span class="tag <?php echo $pTag; ?>"><?php echo htmlspecialchars(ucfirst($pst)); ?></span></td>
                                                <td class="text-slate-400" style="color: #94a3b8;"><?php echo htmlspecialchars($payout['requested_at'] ?? $payout['created_at'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="p-4 text-center text-slate-400 text-xs">No payout requests submitted yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="detail-card-box h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-white mb-0" style="font-size: 14px;">Saved Payout Methods</h6>
                                <button type="button" onclick="openBankModal()" class="btn btn-xs btn-outline-light font-bold py-1 px-2" style="border-radius: 6px; font-size: 11px;">
                                    <i class='bx bx-plus me-1'></i> Add Method
                                </button>
                            </div>
                            <?php if (!empty($aff_payout_methods)): ?>
                                <div class="d-flex flex-column gap-2.5">
                                    <?php foreach ($aff_payout_methods as $method): 
                                        $mDetails = parsePayoutDetails($method['account_details']);
                                        $mType = strtolower($method['method_type'] ?? 'bank_transfer');
                                        $mBank = $mDetails['bank_name'] ?? $mDetails['bank'] ?? '';
                                        $mAcc = $mDetails['account_number'] ?? $mDetails['account_no'] ?? $mDetails['upi_id'] ?? $mDetails['crypto_address'] ?? $mDetails['wallet_address'] ?? '';
                                        $mName = $mDetails['account_name'] ?? $mDetails['account_holder'] ?? $mDetails['holder_name'] ?? $aff_full_name;
                                        $mIfsc = $mDetails['ifsc_code'] ?? $mDetails['ifsc'] ?? $mDetails['swift_code'] ?? $mDetails['branch_name'] ?? '';
                                    ?>
                                        <div class="p-3 rounded border" style="background: #090e1a; border-color: rgba(255,255,255,0.08) !important;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class='bx <?php echo ($mType === 'upi' ? 'bx-mobile' : ($mType === 'crypto' ? 'bx-bitcoin' : 'bx-building')); ?> text-emerald-400 fs-5'></i>
                                                    <strong class="text-white text-xs"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $method['method_type'] ?? 'Method'))); ?></strong>
                                                </div>
                                                <?php if (!empty($method['is_primary'])): ?>
                                                    <span class="tag tag-success">Primary</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($mBank)): ?>
                                                <div class="text-xs text-white fw-bold mb-1"><?php echo htmlspecialchars($mBank); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($mName)): ?>
                                                <div class="text-slate-400 text-xs mb-1">Beneficiary: <strong class="text-slate-200"><?php echo htmlspecialchars($mName); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($mAcc)): ?>
                                                <div class="text-cyan-code text-xs mb-1 font-monospace d-flex align-items-center gap-2">
                                                    <span><?php echo htmlspecialchars($mAcc); ?></span>
                                                    <button type="button" class="btn btn-link p-0 text-slate-400 hover:text-white" onclick="navigator.clipboard.writeText('<?php echo addslashes($mAcc); ?>'); Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false });">
                                                        <i class='bx bx-copy'></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($mIfsc)): ?>
                                                <div class="text-slate-400 text-xs font-mono">IFSC: <span class="text-slate-300"><?php echo htmlspecialchars($mIfsc); ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state-box p-4">
                                    <div class="empty-icon" style="width: 44px; height: 44px; font-size: 20px;"><i class='bx bx-credit-card'></i></div>
                                    <div class="text-white fw-bold text-xs">No payout methods saved</div>
                                    <div class="text-slate-400 text-xs mt-1">Bank details registered during onboarding will appear here.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 7: KYC & Identity -->
            <div id="tab-kyc" class="tab-content-item d-none">
                <?php if (!empty($kyc_docs)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>Document Title / Type</th>
                                    <th>File Attachment</th>
                                    <th>Submitted Date</th>
                                    <th>Status</th>
                                    <th>Reviewer Note</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kyc_docs as $doc): 
                                    $docSt = strtolower($doc['status'] ?? 'pending');
                                    $docTag = $docSt === 'approved' ? 'tag-success' : ($docSt === 'rejected' ? 'tag-danger' : 'tag-warning');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white d-flex align-items-center gap-2">
                                                <i class='bx bx-file text-cyan-code fs-5'></i>
                                                <?php echo htmlspecialchars($doc['document_name'] ?: ($doc['document_type'] . ' Document')); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0)" onclick="viewDocModal('<?php echo htmlspecialchars($doc['document_name'] ?: ($doc['document_type'] . ' Document')); ?>', '<?php echo '/uploads/affiliate_kyc/' . htmlspecialchars($doc['file_path']); ?>', <?php echo (int)$doc['id']; ?>, '<?php echo $docSt; ?>')" class="text-cyan-code text-xs text-decoration-none d-inline-flex align-items-center gap-1">
                                                <i class='bx bx-file-find'></i> <?php echo htmlspecialchars($doc['file_path']); ?>
                                            </a>
                                        </td>
                                        <td class="text-slate-400" style="color: #94a3b8;"><?php echo date('Y-m-d H:i', strtotime($doc['created_at'])); ?></td>
                                        <td><span class="tag <?php echo $docTag; ?>"><?php echo strtoupper($docSt === 'approved' ? 'Verified' : ($docSt === 'rejected' ? 'Rejected' : 'Under Review')); ?></span></td>
                                        <td class="text-slate-400" style="color: #94a3b8;"><?php echo htmlspecialchars($doc['reviewer_note'] ?? '-'); ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <button type="button" onclick="viewDocModal('<?php echo htmlspecialchars($doc['document_name'] ?: ($doc['document_type'] . ' Document')); ?>', '<?php echo '/uploads/affiliate_kyc/' . htmlspecialchars($doc['file_path']); ?>', <?php echo (int)$doc['id']; ?>, '<?php echo $docSt; ?>')" class="btn btn-sm btn-outline-light font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                                    <i class='bx bx-show'></i> Preview
                                                </button>
                                                <?php if ($docSt !== 'approved'): ?>
                                                    <button type="button" onclick="reviewKyc(<?php echo (int)$doc['id']; ?>, 'approve')" class="btn btn-sm font-bold py-1.5 px-3 text-white" style="border-radius: 8px; font-size: 11px; background: #10b981; border: none;">
                                                        <i class='bx bx-check-circle'></i> Approve
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($docSt !== 'rejected'): ?>
                                                    <button type="button" onclick="reviewKyc(<?php echo (int)$doc['id']; ?>, 'reject')" class="btn btn-sm btn-outline-danger font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                                        <i class='bx bx-x-circle'></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-id-card'></i></div>
                        <h6 class="fw-bold text-white mb-1">No KYC documents uploaded</h6>
                        <p class="text-slate-400 text-xs mb-0">When the partner submits proof of identity or address in their portal, it will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 8: Support Tickets -->
            <div id="tab-tickets" class="tab-content-item d-none">
                <?php if (!empty($aff_tickets)): ?>
                    <div class="detail-card-box p-0 overflow-auto">
                        <table class="r-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aff_tickets as $tk): 
                                    $tkSt = strtolower($tk['status'] ?? 'open');
                                    $tkTag = $tkSt === 'resolved' ? 'tag-success' : ($tkSt === 'in_progress' ? 'tag-blue' : ($tkSt === 'closed' ? 'tag-purple' : 'tag-warning'));
                                ?>
                                    <tr>
                                        <td><span class="text-cyan-code fw-bold">#AFF-<?php echo (int)$tk['id']; ?></span></td>
                                        <td><span class="fw-bold text-white"><?php echo htmlspecialchars($tk['subject']); ?></span></td>
                                        <td class="text-slate-300"><?php echo htmlspecialchars($tk['category']); ?></td>
                                        <td><span class="tag tag-purple"><?php echo htmlspecialchars(ucfirst($tk['priority'] ?? 'Normal')); ?></span></td>
                                        <td><span class="tag <?php echo $tkTag; ?>"><?php echo strtoupper(str_replace('_', ' ', $tkSt)); ?></span></td>
                                        <td class="text-slate-400" style="color: #94a3b8;"><?php echo date('Y-m-d H:i', strtotime($tk['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a href="../tickets/" class="btn btn-sm btn-outline-light font-bold py-1.5 px-3" style="border-radius: 8px; font-size: 11px;">
                                                <i class='bx bx-message-dots me-1'></i> Desk
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><i class='bx bx-support'></i></div>
                        <h6 class="fw-bold text-white mb-1">No support tickets</h6>
                        <p class="text-slate-400 text-xs mb-0">Inquiries submitted by this affiliate partner will be listed here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 9: Activity Log -->
            <div id="tab-activity" class="tab-content-item d-none">
                <div class="detail-card-box p-0 overflow-auto">
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sl. No.</th>
                                <th>Date & Time</th>
                                <th>Action Group</th>
                                <th>Action Type</th>
                                <th>Target Entity</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($aff_activity)): ?>
                                <?php $idx = 1; foreach ($aff_activity as $act): ?>
                                    <tr>
                                        <td class="text-slate-400"><?php echo $idx++; ?></td>
                                        <td class="text-slate-300"><?php echo htmlspecialchars($act['created_at'] ?? 'N/A'); ?></td>
                                        <td><span class="tag tag-purple"><?php echo htmlspecialchars($act['action_group'] ?? 'GENERAL'); ?></span></td>
                                        <td><span class="tag tag-blue"><?php echo htmlspecialchars($act['action_type'] ?? 'N/A'); ?></span></td>
                                        <td class="text-cyan-code"><?php echo htmlspecialchars(($act['target_entity_type'] ?? 'affiliate') . ':' . ($act['target_entity_id'] ?? $aff_id)); ?></td>
                                        <td class="text-slate-400"><?php echo htmlspecialchars($act['ip_address'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-slate-400 py-4">No audit logs recorded for this partner account.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="p-3 text-slate-400 text-xs fw-semibold border-top border-secondary border-opacity-25" style="color: #94a3b8;">
                        Showing all <?php echo count($aff_activity); ?> entries
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Add / Edit Bank Account Modal -->
<div class="modal fade" id="bankModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content credit-modal-content">
            <div class="credit-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="credit-icon-badge">
                        <i class='bx bx-building-house'></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0 fs-6">Banking & Payout Account</h5>
                        <div class="text-slate-400 text-xs mt-0.5" style="color: #94a3b8;">Partner: <?php echo htmlspecialchars($aff_name); ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form onsubmit="submitBankModal(event)">
                <input type="hidden" id="modalBankAffId" value="<?php echo (int)($aff['id'] ?? $aff_id); ?>">
                <input type="hidden" id="modalBankMethodId" value="<?php echo (int)($primary_payout['id'] ?? 0); ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="dark-field-label">PAYMENT / SETTLEMENT METHOD</label>
                        <select id="modalBankMethodType" class="dark-select" onchange="onModalBankTypeChange()">
                            <option value="bank_transfer" <?php echo ($primary_payout && $primary_payout['method_type'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Account (NEFT / IMPS / RTGS)</option>
                            <option value="UPI" <?php echo ($primary_payout && $primary_payout['method_type'] === 'UPI') ? 'selected' : ''; ?>>UPI (VPA)</option>
                            <option value="crypto" <?php echo ($primary_payout && $primary_payout['method_type'] === 'crypto') ? 'selected' : ''; ?>>Crypto (USDT TRC-20)</option>
                        </select>
                    </div>

                    <div id="modalBankFields">
                        <div class="mb-3">
                            <label class="dark-field-label">BANK NAME</label>
                            <input type="text" id="modalBankName" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['bank_name'] ?? ''); ?>" placeholder="e.g. HDFC Bank">
                        </div>
                        <div class="mb-3">
                            <label class="dark-field-label">ACCOUNT HOLDER NAME</label>
                            <input type="text" id="modalAccountName" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['account_name'] ?? $aff_full_name); ?>" placeholder="Legal Name on Bank Account">
                        </div>
                        <div class="mb-3">
                            <label class="dark-field-label">ACCOUNT NUMBER</label>
                            <input type="text" id="modalAccountNumber" class="dark-input font-monospace" value="<?php echo htmlspecialchars($primary_payout_details['account_number'] ?? ''); ?>" placeholder="Bank Account Number">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="dark-field-label">IFSC CODE</label>
                                <input type="text" id="modalIfsc" class="dark-input font-monospace" value="<?php echo htmlspecialchars($primary_payout_details['ifsc_code'] ?? ''); ?>" placeholder="e.g. HDFC0001234">
                            </div>
                            <div class="col-6">
                                <label class="dark-field-label">BRANCH NAME</label>
                                <input type="text" id="modalBranch" class="dark-input" value="<?php echo htmlspecialchars($primary_payout_details['branch_name'] ?? ''); ?>" placeholder="Branch Location">
                            </div>
                        </div>
                    </div>

                    <div id="modalUpiFields" class="d-none">
                        <div class="mb-3">
                            <label class="dark-field-label">UPI VPA ID</label>
                            <input type="text" id="modalUpiId" class="dark-input font-monospace" value="<?php echo htmlspecialchars($primary_payout_details['upi_id'] ?? ''); ?>" placeholder="username@bank">
                        </div>
                    </div>

                    <div id="modalCryptoFields" class="d-none">
                        <div class="mb-3">
                            <label class="dark-field-label">USDT WALLET ADDRESS</label>
                            <input type="text" id="modalCryptoAddress" class="dark-input font-monospace" value="<?php echo htmlspecialchars($primary_payout_details['crypto_address'] ?? ''); ?>" placeholder="e.g. Txxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="mb-3">
                            <label class="dark-field-label">NETWORK</label>
                            <input type="text" id="modalCryptoNetwork" class="dark-input font-monospace" value="<?php echo htmlspecialchars($primary_payout_details['network'] ?? 'TRC-20'); ?>" placeholder="TRC-20 / ERC-20">
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300 font-bold px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" id="saveBankModalBtn" class="btn btn-sm font-bold text-white px-4 py-2" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        Save Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Affiliate Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content credit-modal-content">
            <div class="credit-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="credit-icon-badge">
                        <i class='bx bx-key'></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0 fs-6">Change Affiliate Password</h5>
                        <div class="text-slate-400 text-xs mt-0.5" id="changePassAffiliateName" style="color: #94a3b8;">
                            Partner: <?php echo htmlspecialchars($aff_name); ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form onsubmit="submitChangePassword(event)">
                <input type="hidden" id="changePassAffId" value="<?php echo (int)($aff['id'] ?? $aff_id); ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="dark-field-label mb-0">NEW PASSWORD</label>
                            <button type="button" onclick="generateRandomPassword()" class="btn btn-link p-0 text-decoration-none text-cyan-code" style="font-size: 11px;">
                                <i class='bx bx-refresh'></i> Generate Strong Password
                            </button>
                        </div>
                        <div class="position-relative">
                            <input type="password" id="newAffPassword" class="dark-input pe-5" placeholder="Enter new password (min 6 characters)" required minlength="6">
                            <button class="btn position-absolute top-50 end-0 translate-middle-y text-slate-400 border-0" type="button" onclick="togglePassVisibility('newAffPassword', this)">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="dark-field-label">CONFIRM NEW PASSWORD</label>
                        <div class="position-relative">
                            <input type="password" id="confirmAffPassword" class="dark-input pe-5" placeholder="Re-enter new password" required minlength="6">
                            <button class="btn position-absolute top-50 end-0 translate-middle-y text-slate-400 border-0" type="button" onclick="togglePassVisibility('confirmAffPassword', this)">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                        <div id="passMismatchMsg" class="text-danger small mt-1 d-none"><i class='bx bx-error-circle'></i> Passwords do not match!</div>
                    </div>
                </div>
                <div class="modal-footer p-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300 font-bold px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" id="savePassBtn" class="btn btn-sm font-bold text-white px-4 py-2" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tracking Link Detail Modal -->
<div class="modal fade" id="linkDetailModal" tabindex="-1" aria-labelledby="linkDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: #0b1120; border: 1px solid rgba(255,255,255,0.12); color: #fff; border-radius: 16px;">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                        <i class='bx bx-link-alt fs-4'></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="linkDetailModalLabel">Tracking Campaign Details</h5>
                        <span class="text-cyan-code text-xs font-mono fw-bold" id="linkDetailCodeBadge">LNK-5298D8</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background: #0b1120;">
                
                <!-- SUMMARY CARDS -->
                <div class="row g-2.5 mb-4">
                    <div class="col-6 col-md">
                        <div class="p-3 rounded border text-center" style="background: #0f172a; border-color: rgba(255,255,255,0.08) !important;">
                            <div class="text-slate-400 text-xs mb-1">TOTAL CLICKS</div>
                            <div class="fs-4 fw-bold text-white" id="linkStatClicks">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="p-3 rounded border text-center" style="background: #0f172a; border-color: rgba(255,255,255,0.08) !important;">
                            <div class="text-slate-400 text-xs mb-1">REGISTERED SIGNUPS</div>
                            <div class="fs-4 fw-bold text-cyan-code" id="linkStatSignups">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="p-3 rounded border text-center" style="background: #0f172a; border-color: rgba(255,255,255,0.08) !important;">
                            <div class="text-slate-400 text-xs mb-1">QUALIFIED FTDS</div>
                            <div class="fs-4 fw-bold text-emerald-400" id="linkStatFtds">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="p-3 rounded border text-center" style="background: #0f172a; border-color: rgba(255,255,255,0.08) !important;">
                            <div class="text-slate-400 text-xs mb-1">FTD DEPOSIT VOLUME</div>
                            <div class="fs-4 fw-bold text-emerald-400" id="linkStatFtdVolume">₹ 0.00</div>
                        </div>
                    </div>
                    <div class="col-12 col-md">
                        <div class="p-3 rounded border text-center" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.25) !important;">
                            <div class="text-emerald-400 text-xs mb-1 fw-bold">COMMISSION EARNED</div>
                            <div class="fs-4 fw-bold text-emerald-400" id="linkStatCommission">₹ 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- CAMPAIGN INFO -->
                <div class="detail-card-box mb-4">
                    <h6 class="fw-bold text-white mb-2" style="font-size: 14px;"><i class='bx bx-globe text-cyan-code me-1.5'></i>Tracking Campaign Information</h6>
                    <div class="info-row py-1"><span class="info-label">Campaign Name:</span><span class="info-val text-white fw-bold" id="linkDetailName">-</span></div>
                    <div class="info-row py-1"><span class="info-label">Target Path:</span><span class="info-val text-cyan-code" id="linkDetailTarget">-</span></div>
                    <div class="info-row py-1">
                        <span class="info-label">Live URL:</span>
                        <span class="info-val text-cyan-code font-mono text-xs text-break d-flex align-items-center gap-2">
                            <span id="linkDetailUrl">-</span>
                            <button type="button" onclick="copyLinkUrl()" class="btn btn-xs btn-outline-light py-0 px-2" style="font-size: 10px; background: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.3); color: #38bdf8;">
                                <i class='bx bx-copy'></i> Copy
                            </button>
                        </span>
                    </div>
                </div>

                <!-- REFERRED PLAYERS UNDER THIS LINK -->
                <div class="mb-4">
                    <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">
                        <i class='bx bx-group text-cyan-code me-1.5'></i>Referred Players Registered Under This Link (<span id="linkPlayerCount">0</span>)
                    </h6>
                    <div class="detail-card-box p-0 overflow-auto" id="linkPlayersTableContainer">
                    </div>
                </div>

                <!-- COMMISSIONS GENERATED FROM THIS LINK -->
                <div>
                    <h6 class="fw-bold text-white mb-3" style="font-size: 15px;">
                        <i class='bx bx-wallet text-emerald-400 me-1.5'></i>Commissions Logged From Players Under This Link (<span id="linkCommCount">0</span>)
                    </h6>
                    <div class="detail-card-box p-0 overflow-auto" id="linkCommsTableContainer">
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 py-2.5">
                <button type="button" class="btn btn-sm btn-secondary font-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- KYC Document Viewer Modal -->
<div class="modal fade" id="docViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content credit-modal-content">
            <div class="credit-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="credit-icon-badge">
                        <i class='bx bx-file'></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0 fs-6" id="docViewerModalLabel">KYC Document Preview</h5>
                        <div class="text-slate-400 text-xs mt-0.5" style="color: #94a3b8;">Partner Identity Compliance Audit</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4" style="min-height: 350px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <div id="docViewerContent" class="w-100"></div>
            </div>
            <div class="modal-footer p-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                <a id="docViewerExternalLink" href="#" target="_blank" class="btn btn-sm btn-outline-light font-bold" style="border-radius: 8px;">
                    <i class='bx bx-link-external me-1'></i> Open Full File
                </a>
                <div class="d-flex gap-2" id="docViewerActionButtons"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content-item').forEach(c => c.classList.add('d-none'));

    if (btn) btn.classList.add('active');
    const target = document.getElementById('tab-' + tabId);
    if (target) target.classList.remove('d-none');
}

function openBankModal() {
    onModalBankTypeChange();
    const modalEl = document.getElementById('bankModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function onModalBankTypeChange() {
    const methodType = document.getElementById('modalBankMethodType').value;
    const bankFields = document.getElementById('modalBankFields');
    const upiFields = document.getElementById('modalUpiFields');
    const cryptoFields = document.getElementById('modalCryptoFields');

    bankFields.classList.add('d-none');
    upiFields.classList.add('d-none');
    cryptoFields.classList.add('d-none');

    if (methodType === 'UPI') {
        upiFields.classList.remove('d-none');
    } else if (methodType === 'crypto') {
        cryptoFields.classList.remove('d-none');
    } else {
        bankFields.classList.remove('d-none');
    }
}

function submitBankModal(e) {
    e.preventDefault();
    const affId = parseInt(document.getElementById('modalBankAffId').value) || <?php echo (int)($aff['id'] ?? $aff_id); ?>;
    const methodId = parseInt(document.getElementById('modalBankMethodId').value) || 0;
    const methodType = document.getElementById('modalBankMethodType').value;
    const btn = document.getElementById('saveBankModalBtn');

    let details = {};
    if (methodType === 'UPI') {
        details = {
            upi_id: document.getElementById('modalUpiId').value.trim()
        };
    } else if (methodType === 'crypto') {
        details = {
            crypto_address: document.getElementById('modalCryptoAddress').value.trim(),
            network: document.getElementById('modalCryptoNetwork').value.trim() || 'TRC-20'
        };
    } else {
        details = {
            bank_name: document.getElementById('modalBankName').value.trim(),
            account_name: document.getElementById('modalAccountName').value.trim(),
            account_number: document.getElementById('modalAccountNumber').value.trim(),
            ifsc_code: document.getElementById('modalIfsc').value.trim(),
            branch_name: document.getElementById('modalBranch').value.trim()
        };
    }

    if (btn) btn.disabled = true;

    fetch('/admin/affiliates/api_list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_payout_method',
            affiliate_id: affId,
            method_id: methodId,
            method_type: methodType,
            account_details: details,
            is_primary: 1
        })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (data.status === 'success') {
            const modalEl = document.getElementById('bankModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            Swal.fire({
                icon: 'success',
                title: 'Bank Details Saved!',
                text: 'Settlement account has been updated.',
                timer: 1800,
                showConfirmButton: false
            }).then(() => window.location.reload());
        } else {
            Swal.fire('Error', data.message || 'Failed to save bank details.', 'error');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        console.error(err);
        Swal.fire('Error', 'Network or server error.', 'error');
    });
}

function saveAffiliateTerms(e) {
    e.preventDefault();
    const affId = <?php echo (int)($aff['id'] ?? $aff_id); ?>;
    const dealType = document.getElementById('termsDealType').value;
    const revsharePct = parseFloat(document.getElementById('termsRevshare').value) || 0;
    const cpaAmount = parseFloat(document.getElementById('termsCpa').value) || 0;
    const subOverridePct = parseFloat(document.getElementById('termsSubOverride').value) || 5.0;
    const parentId = parseInt(document.getElementById('termsParentId').value) || 0;
    const tier = document.getElementById('termsTier').value;
    const status = document.getElementById('termsStatus').value;
    const fullName = document.getElementById('termsFullName').value.trim();
    const companyName = document.getElementById('termsCompanyName').value.trim();
    const phone = document.getElementById('termsPhone').value.trim();
    const website = document.getElementById('termsWebsite').value.trim();

    // Bank inputs from terms
    const bankMethodType = document.getElementById('termsBankMethodType').value;
    const bankName = document.getElementById('termsBankName').value.trim();
    const accountName = document.getElementById('termsAccountName').value.trim();
    const accountNumber = document.getElementById('termsAccountNumber').value.trim();
    const ifsc = document.getElementById('termsIfsc').value.trim();
    const branch = document.getElementById('termsBranch').value.trim();

    const btn = document.getElementById('saveTermsBtn');
    if (btn) btn.disabled = true;

    // 1. Update Profile, Status, and Upline Parent
    fetch('/admin/affiliates/api_list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            affiliate_id: affId,
            action: 'update_profile',
            full_name: fullName,
            company_name: companyName,
            phone: phone,
            website: website,
            tier: tier,
            status: status,
            parent_id: parentId
        })
    })
    .then(r => r.json())
    .then(data => {
        // 2. Update Deal Terms & Sub-Override Rate
        return fetch('/admin/affiliates/api_list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                affiliate_id: affId,
                action: 'update_deal',
                deal_type: dealType,
                revshare_pct: revsharePct,
                cpa_amount: cpaAmount,
                sub_override_pct: subOverridePct
            })
        });
    })
    .then(r => r.json())
    .then(data => {
        // 3. Update Bank Details if any field filled
        if (accountNumber || bankName || ifsc) {
            const bankDetailsObj = {
                bank_name: bankName,
                account_name: accountName,
                account_number: accountNumber,
                ifsc_code: ifsc,
                branch_name: branch
            };
            if (bankMethodType === 'UPI') bankDetailsObj.upi_id = accountNumber;
            if (bankMethodType === 'crypto') bankDetailsObj.crypto_address = accountNumber;

            return fetch('/admin/affiliates/api_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    affiliate_id: affId,
                    action: 'save_payout_method',
                    method_type: bankMethodType,
                    account_details: bankDetailsObj,
                    is_primary: 1
                })
            });
        }
        return Promise.resolve();
    })
    .then(() => {
        if (btn) btn.disabled = false;
        Swal.fire({
            icon: 'success',
            title: 'Settings Saved',
            text: 'Commercial terms, referral upline, and bank details have been updated.',
            timer: 1800,
            showConfirmButton: false
        }).then(() => {
            window.location.reload();
        });
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        console.error(err);
        Swal.fire('Error', 'Failed to update partner details.', 'error');
    });
}

function approveDetailAffiliate(id, name) {
    document.getElementById('approveAffiliateId').value = id;
    document.getElementById('approveAffiliateName').innerText = name || '<?php echo addslashes($aff_name); ?>';
    document.getElementById('approveDealType').value = 'revenue_share';
    document.getElementById('approveRevshare').value = '30';
    document.getElementById('approveCpa').value = '50';
    document.getElementById('approveTier').value = 'bronze';
    updateApprovalCommissionFields();
    const modalEl = document.getElementById('approveAffiliateModal');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function updateApprovalCommissionFields() {
    const dealType = document.getElementById('approveDealType').value;
    document.getElementById('approveRevshareGroup').classList.toggle('d-none', dealType === 'cpa');
    document.getElementById('approveCpaGroup').classList.toggle('d-none', dealType === 'revenue_share');
}

async function submitAffiliateApproval() {
    const modalElement = document.getElementById('approveAffiliateModal');
    const modal = bootstrap.Modal.getInstance(modalElement);
    try {
        const resp = await fetch('/admin/affiliates/api_list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                affiliate_id: document.getElementById('approveAffiliateId').value,
                action: 'approve',
                status: 'active',
                deal_type: document.getElementById('approveDealType').value,
                revshare_pct: parseFloat(document.getElementById('approveRevshare').value || 0),
                cpa_amount: parseFloat(document.getElementById('approveCpa').value || 0),
                tier: document.getElementById('approveTier').value
            })
        });
        const data = await resp.json();
        if (modal) modal.hide();
        if (data.status === 'success') {
            Swal.fire({
                title: 'Approved!',
                text: data.message || 'Affiliate has been approved and activated.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to approve affiliate.', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Network or server error occurred.', 'error');
    }
}

function toggleDetailAffiliateStatus(id, currentStatus) {
    const isSuspended = (currentStatus === 'suspended');
    const newStatus = isSuspended ? 'active' : 'suspended';
    const actionText = isSuspended ? 'Activate' : 'Suspend';

    Swal.fire({
        title: `${actionText} Affiliate?`,
        text: `Are you sure you want to ${actionText.toLowerCase()} this partner account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: isSuspended ? '#10b981' : '#ef4444',
        confirmButtonText: `Yes, ${actionText}`
    }).then(res => {
        if (res.isConfirmed) {
            fetch('/admin/affiliates/api_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ affiliate_id: id, status: newStatus })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Status Updated', text: data.message, timer: 1500, showConfirmButton: false })
                    .then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Request failed', 'error'));
        }
    });
}

function deleteDetailAffiliate(id) {
    Swal.fire({
        title: 'Delete Affiliate?',
        text: 'This will permanently remove the affiliate partner and referral links. This action cannot be undone!',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete Permanently'
    }).then(res => {
        if (res.isConfirmed) {
            fetch('/admin/affiliates/api_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ affiliate_id: id, action: 'delete' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: data.message, timer: 1500, showConfirmButton: false })
                    .then(() => window.location.href = '../');
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Request failed', 'error'));
        }
    });
}

function reviewKyc(docId, action) {
    const isApprove = action === 'approve';
    const title = isApprove ? 'Approve KYC Document?' : 'Reject KYC Document?';
    const text = isApprove 
        ? 'Approve this identity document and mark affiliate KYC as Verified?' 
        : 'Reject this KYC document? Affiliate will be requested to upload valid proof.';

    Swal.fire({
        title: title,
        text: text,
        icon: isApprove ? 'question' : 'warning',
        input: isApprove ? null : 'text',
        inputPlaceholder: isApprove ? null : 'Reason for rejection (optional)',
        showCancelButton: true,
        confirmButtonColor: isApprove ? '#10b981' : '#ef4444',
        confirmButtonText: isApprove ? 'Yes, Approve KYC' : 'Reject Document',
        cancelButtonText: 'Cancel'
    }).then((res) => {
        if (res.isConfirmed) {
            const note = res.value || '';
            fetch('/admin/affiliates/api_kyc.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: action, 
                    document_id: docId, 
                    affiliate_id: <?php echo (int)($aff['id'] ?? $aff_id); ?>,
                    note: note 
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: isApprove ? 'KYC Approved!' : 'KYC Rejected',
                        text: data.message,
                        icon: 'success',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Action failed.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network or server error.', 'error');
            });
        }
    });
}

function viewDocModal(title, fileUrl, docId, status) {
    document.getElementById('docViewerModalLabel').innerText = title || 'Document Preview';
    document.getElementById('docViewerExternalLink').href = fileUrl;

    const content = document.getElementById('docViewerContent');
    const ext = fileUrl.split('.').pop().toLowerCase();

    if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext)) {
        content.innerHTML = `
            <div style="max-height: 520px; overflow: auto; display: flex; justify-content: center; align-items: center; width: 100%;">
                <img src="${fileUrl}" alt="${title}" style="max-width: 100%; max-height: 480px; object-fit: contain; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.5);" onerror="this.onerror=null; this.src='/uploads/affiliate_kyc/icon-192.png';" />
            </div>
        `;
    } else if (ext === 'pdf') {
        content.innerHTML = `
            <iframe src="${fileUrl}" style="width: 100%; height: 500px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15);"></iframe>
        `;
    } else {
        content.innerHTML = `
            <div class="py-5 text-center">
                <i class='bx bx-file text-cyan-code fs-1 mb-2'></i>
                <p class="text-white fw-bold mb-2">${title}</p>
                <a href="${fileUrl}" target="_blank" class="btn btn-outline-light btn-sm">Download / Open File</a>
            </div>
        `;
    }

    const actionContainer = document.getElementById('docViewerActionButtons');
    let actionButtonsHtml = `<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>`;

    if (status !== 'approved') {
        actionButtonsHtml = `
            <button type="button" onclick="reviewKyc(${docId}, 'approve')" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1">
                <i class='bx bx-check-circle'></i> Approve KYC
            </button>
            <button type="button" onclick="reviewKyc(${docId}, 'reject')" class="btn btn-outline-danger btn-sm fw-bold d-inline-flex align-items-center gap-1">
                <i class='bx bx-x-circle'></i> Reject
            </button>
            ` + actionButtonsHtml;
    }
    actionContainer.innerHTML = actionButtonsHtml;

    const modalEl = document.getElementById('docViewerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function openChangePasswordModal(id, name) {
    const validId = (id && id > 0) ? id : <?php echo (int)($aff['id'] ?? $aff_id); ?>;
    document.getElementById('changePassAffId').value = validId;
    document.getElementById('changePassAffiliateName').innerText = 'Partner: ' + (name || '<?php echo addslashes($aff_name); ?>');
    document.getElementById('newAffPassword').value = '';
    document.getElementById('confirmAffPassword').value = '';
    document.getElementById('passMismatchMsg').classList.add('d-none');

    const modalEl = document.getElementById('changePasswordModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-hide';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-show';
    }
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    let pass = '';
    for (let i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const newPassInput = document.getElementById('newAffPassword');
    const confirmPassInput = document.getElementById('confirmAffPassword');
    newPassInput.value = pass;
    newPassInput.type = 'text';
    confirmPassInput.value = pass;
    confirmPassInput.type = 'text';
    document.getElementById('passMismatchMsg').classList.add('d-none');
}

function submitChangePassword(e) {
    e.preventDefault();
    const affId = parseInt(document.getElementById('changePassAffId').value) || <?php echo (int)($aff['id'] ?? $aff_id); ?>;
    const newPass = document.getElementById('newAffPassword').value.trim();
    const confirmPass = document.getElementById('confirmAffPassword').value.trim();
    const mismatchMsg = document.getElementById('passMismatchMsg');

    if (newPass !== confirmPass) {
        mismatchMsg.classList.remove('d-none');
        return;
    }
    mismatchMsg.classList.add('d-none');

    if (newPass.length < 6) {
        Swal.fire('Error', 'Password must be at least 6 characters.', 'error');
        return;
    }

    const btn = document.getElementById('savePassBtn');
    btn.disabled = true;

    fetch('/admin/affiliates/api_list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'change_password',
            affiliate_id: affId,
            new_password: newPass
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.status === 'success') {
            const modalEl = document.getElementById('changePasswordModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            Swal.fire({
                icon: 'success',
                title: 'Password Updated!',
                text: data.message || 'Affiliate password has been changed successfully.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to update password.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error(err);
        Swal.fire('Error', 'Network or server error occurred.', 'error');
    });
}

// LINK DETAIL MODAL & ANALYTICS
const allAffLinksData = <?php echo json_encode($aff_links); ?>;
const allAffReferralsData = <?php echo json_encode($aff_referrals); ?>;
const allAffCommissionsData = <?php echo json_encode($aff_commissions); ?>;

function openLinkDetailModal(linkId) {
    const link = allAffLinksData.find(l => parseInt(l.id) === parseInt(linkId));
    if (!link) {
        Swal.fire('Notice', 'Tracking link details not found.', 'info');
        return;
    }

    const code = link.code || 'N/A';
    const name = link.name || link.code;
    const target = link.target_path || '/register';
    const clicks = parseInt(link.clicks_count) || 0;
    const signups = parseInt(link.signups_count) || 0;
    const ftds = parseInt(link.ftds_count) || 0;
    const ftdVol = parseFloat(link.total_ftd_amount) || 0.00;

    let fullUrl = 'http://localhost:5173' + (target === '/' ? '/register' : (target.startsWith('/') ? target : '/' + target)) + '?ref=' + encodeURIComponent(code);
    if (link.sub_id) fullUrl += '&sub=' + encodeURIComponent(link.sub_id);

    document.getElementById('linkDetailModalLabel').innerText = name + ' (' + code + ')';
    document.getElementById('linkDetailCodeBadge').innerText = code;
    document.getElementById('linkStatClicks').innerText = clicks.toLocaleString();
    document.getElementById('linkStatSignups').innerText = signups.toLocaleString();
    document.getElementById('linkStatFtds').innerText = ftds.toLocaleString();
    document.getElementById('linkStatFtdVolume').innerText = '₹ ' + ftdVol.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('linkDetailName').innerText = name;
    document.getElementById('linkDetailTarget').innerText = target;
    document.getElementById('linkDetailUrl').innerText = fullUrl;

    // Filter referred players under this link
    const linkReferrals = allAffReferralsData.filter(r => parseInt(r.link_id) === parseInt(linkId));
    document.getElementById('linkPlayerCount').innerText = linkReferrals.length;

    let playersHtml = '';
    if (linkReferrals.length > 0) {
        playersHtml = `
            <table class="r-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Mobile / Email</th>
                        <th>Signup Date</th>
                        <th>First Deposit (FTD)</th>
                        <th>CPA Qualification</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        linkReferrals.forEach(r => {
            const userName = r.tbl_full_name || r.tbl_user_name || ('User #' + r.user_id);
            const userHandle = r.tbl_user_name ? ('@' + r.tbl_user_name) : '';
            const emailMob = (r.tbl_email_id || '') + (r.tbl_mobile_num ? ' (' + r.tbl_mobile_num + ')' : '');
            const ftdAmt = parseFloat(r.first_deposit_amount || 0).toFixed(2);
            const isCpa = parseInt(r.is_cpa_qualified) ? '<span class="tag tag-success">Qualified</span>' : '<span class="tag tag-blue">Pending FTD</span>';
            const ftdDate = r.first_deposit_at ? ('<span class="text-emerald-400 fw-bold">₹ ' + ftdAmt + '</span><div class="text-slate-400 text-xs">' + r.first_deposit_at + '</div>') : '<span class="text-slate-400">No Deposit Yet</span>';

            playersHtml += `
                <tr>
                    <td>
                        <div class="fw-bold text-white">${escapeHtmlStr(userName)}</div>
                        <div class="text-cyan-code text-xs">${escapeHtmlStr(userHandle)}</div>
                    </td>
                    <td class="text-slate-300 text-xs">${escapeHtmlStr(emailMob || 'N/A')}</td>
                    <td class="text-slate-400">${escapeHtmlStr(r.signup_at || 'N/A')}</td>
                    <td>${ftdDate}</td>
                    <td>${isCpa}</td>
                    <td><span class="tag tag-success">${escapeHtmlStr((r.status || 'active').toUpperCase())}</span></td>
                </tr>
            `;
        });
        playersHtml += `</tbody></table>`;
    } else {
        playersHtml = `
            <div class="p-4 text-center text-slate-400 text-xs">
                <i class='bx bx-group fs-3 text-slate-500 mb-1'></i>
                <div>No player accounts registered via this specific link yet.</div>
            </div>
        `;
    }
    document.getElementById('linkPlayersTableContainer').innerHTML = playersHtml;

    // Filter commissions for this link's players
    const linkComms = allAffCommissionsData.filter(c => {
        if (c.link_id && parseInt(c.link_id) === parseInt(linkId)) return true;
        const refMatch = linkReferrals.some(r => parseInt(r.id) === parseInt(c.referral_id));
        if (refMatch) return true;
        if (c.entry_type === 'cpa' && linkReferrals.some(r => parseFloat(r.first_deposit_amount || 0) === parseFloat(c.base_amount || 0))) {
            return true;
        }
        return false;
    });
    document.getElementById('linkCommCount').innerText = linkComms.length;

    const totalLinkComm = linkComms.reduce((sum, c) => sum + (parseFloat(c.amount) || 0), 0);
    document.getElementById('linkStatCommission').innerText = '₹ ' + totalLinkComm.toLocaleString('en-IN', {minimumFractionDigits: 2});

    let commsHtml = '';
    if (linkComms.length > 0) {
        commsHtml = `
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Base Kind / Amount</th>
                        <th>Rate (%)</th>
                        <th>Commission (₹)</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
        `;
        linkComms.forEach(c => {
            const entryType = (c.entry_type || 'Commission').replace('_', ' ').toUpperCase();
            const baseAmt = parseFloat(c.base_amount || 0).toFixed(2);
            const commAmt = parseFloat(c.amount || 0).toFixed(2);
            const rate = parseFloat(c.rate || 0);
            const st = (c.status || 'pending').toLowerCase();
            const tag = (st === 'approved' || st === 'paid') ? 'tag-success' : 'tag-warning';

            commsHtml += `
                <tr>
                    <td><span class="tag tag-purple">${escapeHtmlStr(entryType)}</span></td>
                    <td><div>${escapeHtmlStr(c.base_kind || 'NGR')}</div><div class="text-slate-400 text-xs">₹ ${baseAmt}</div></td>
                    <td>${rate}%</td>
                    <td class="text-emerald-400 fw-bold">₹ ${commAmt}</td>
                    <td><span class="tag ${tag}">${escapeHtmlStr(st.toUpperCase())}</span></td>
                    <td class="text-slate-400">${escapeHtmlStr(c.created_at || 'N/A')}</td>
                </tr>
            `;
        });
        commsHtml += `</tbody></table>`;
    } else {
        commsHtml = `
            <div class="p-4 text-center text-slate-400 text-xs">
                <i class='bx bx-wallet fs-3 text-slate-500 mb-1'></i>
                <div>No commission entries logged for this link yet.</div>
            </div>
        `;
    }
    document.getElementById('linkCommsTableContainer').innerHTML = commsHtml;

    const modalEl = document.getElementById('linkDetailModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function escapeHtmlStr(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function copyLinkUrl() {
    const url = document.getElementById('linkDetailUrl').innerText;
    navigator.clipboard.writeText(url);
    Swal.fire({ icon: 'success', title: 'Copied!', text: 'Tracking URL copied to clipboard', timer: 1200, showConfirmButton: false });
}
</script>

<!-- APPROVAL MODAL -->
<div class="modal fade" id="approveAffiliateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a; border:1px solid rgba(255,255,255,.12); color:#fff;">
            <div class="modal-header" style="border-bottom-color:rgba(255,255,255,.1);">
                <h5 class="modal-title fw-bold">Approve application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-slate-300">Approving <strong id="approveAffiliateName"></strong>. Choose the commission settings for this affiliate.</p>
                <input type="hidden" id="approveAffiliateId">
                
                <label class="form-label small text-uppercase fw-bold text-slate-400">Commission Type</label>
                <select id="approveDealType" onchange="updateApprovalCommissionFields()" class="form-select mb-3" style="background:#020617; color:#fff; border-color:#334155;">
                    <option value="revenue_share">Revenue share</option>
                    <option value="cpa">CPA</option>
                    <option value="hybrid">Hybrid (CPA + revenue share)</option>
                </select>

                <div id="approveRevshareGroup" class="mb-3">
                    <label class="form-label small text-uppercase fw-bold text-slate-400">Revenue Share %</label>
                    <input type="number" id="approveRevshare" class="form-control" value="30" min="0" max="100" step="0.01" style="background:#020617; color:#fff; border-color:#334155;">
                </div>

                <div id="approveCpaGroup" class="mb-3 d-none">
                    <label class="form-label small text-uppercase fw-bold text-slate-400">CPA Amount (₹)</label>
                    <input type="number" id="approveCpa" class="form-control" value="50" min="0" step="0.01" style="background:#020617; color:#fff; border-color:#334155;">
                </div>

                <label class="form-label small text-uppercase fw-bold text-slate-400">Tier</label>
                <select id="approveTier" class="form-select" style="background:#020617; color:#fff; border-color:#334155;">
                    <option value="bronze">Bronze</option>
                    <option value="silver">Silver</option>
                    <option value="gold">Gold</option>
                    <option value="platinum">Platinum</option>
                </select>
            </div>
            <div class="modal-footer" style="border-top-color:rgba(255,255,255,.1);">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold" onclick="submitAffiliateApproval()">Approve</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
