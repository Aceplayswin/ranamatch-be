<?php
define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/AgentTreeService.php';
require_once __DIR__ . '/BetSettlementService.php';
require_once __DIR__ . '/PayoutService.php';

header('Content-Type: text/plain');

echo "=== SPRINT 4: BET SETTLEMENT & PAYOUT SERVICE TEST ===\n\n";

$treeService = new AgentTreeService($conn);
$betService = new BetSettlementService($conn);
$payoutService = new PayoutService($conn);

try {
    // 1. Create Test Agent & Player Assignment
    echo "1. Setting up Agent & Player test records...\n";
    $agentCode = 'AGT-SETTLE-' . rand(100, 999);
    $agentId = $treeService->createAgentWithTree(null, [
        'agent_code' => $agentCode,
        'name' => 'Bet Settlement Test Agent',
        'email' => 'settle_' . uniqid() . '@agent.com',
        'password' => 'pass123',
        'partnership_pct' => 50.00,
        'turnover_commission_pct' => 2.50,
        'opening_credit' => 100000.00
    ]);

    // Create a mock user in tblusersdata assigned to this agent
    $userUniqId = 'USR-TEST-' . rand(1000, 9999);
    $userSql = "INSERT INTO tblusersdata (tbl_uniq_id, tbl_auth_secret, tbl_avatar_id, tbl_mobile_num, tbl_email_id, tbl_full_name, tbl_password, tbl_balance, tbl_requiredplay_balance, tbl_withdrawl_balance, tbl_commission_balance, tbl_freezed_balance, tbl_joined_under, tbl_last_active_date, tbl_last_active_time, tbl_account_level, tbl_account_status, tbl_user_joined) 
                VALUES (?, 'secret', '1', '9999999999', 'user@test.com', 'Test Player', 'hash', 5000.00, 0.00, 5000.00, 0.00, 0.00, ?, '03-09-2026', '12:00 pm', '1', 'active', '03-09-2026')";
    $uStmt = mysqli_prepare($conn, $userSql);
    mysqli_stmt_bind_param($uStmt, "ss", $userUniqId, $agentCode);
    mysqli_stmt_execute($uStmt);
    $userId = mysqli_insert_id($conn);

    echo "   Agent Code: $agentCode (ID: $agentId)\n";
    echo "   Player UniqId: $userUniqId (ID: $userId)\n\n";

    // 2. Test Bet Placement (Pending Exposure)
    echo "2. Simulating Player placing a ₹1,000 Bet...\n";
    $placedOk = $betService->onBetPlaced($userUniqId, 1000.00);
    
    // Check Agent exposed_credit
    $res = mysqli_query($conn, "SELECT current_credit, exposed_credit FROM agents WHERE id = $agentId");
    $agentInfo = mysqli_fetch_assoc($res);
    echo "   Current Credit: ₹" . $agentInfo['current_credit'] . "\n";
    echo "   Exposed Credit: ₹" . $agentInfo['exposed_credit'] . "\n";

    if ((float)$agentInfo['exposed_credit'] === 1000.00) {
        echo "✅ SUCCESS (Exposed credit correctly locked to ₹1,000)\n\n";
    } else {
        echo "❌ FAILED (Exposed credit mismatch)\n\n";
    }

    // 3. Test Bet Settlement (Player Lost Bet)
    echo "3. Simulating Bet Settlement (Player Lost ₹1,000 bet)...\n";
    $settledOk = $betService->onBetSettled($userUniqId, 'BET-888', 1000.00, 0.00, 'lost');

    $res2 = mysqli_query($conn, "SELECT current_credit, exposed_credit FROM agents WHERE id = $agentId");
    $agentAfter = mysqli_fetch_assoc($res2);
    echo "   Exposed Credit After Settlement: ₹" . $agentAfter['exposed_credit'] . "\n";
    echo "   Current Credit After Settlement: ₹" . $agentAfter['current_credit'] . "\n";

    // Expected: Turnover = ₹1000 * 2.5% = ₹25. P&L = ₹1000 * 50% = ₹500. Total added = ₹525. Initial = ₹100,000. Expected = ₹100,525.
    if ((float)$agentAfter['exposed_credit'] === 0.00 && (float)$agentAfter['current_credit'] === 100525.00) {
        echo "✅ SUCCESS (Exposure released to 0, Turnover Commission + P&L profit correctly credited: ₹100,525.00)\n\n";
    } else {
        echo "❌ FAILED (Credit calculation mismatch)\n\n";
    }

    // 4. Test Payout Lifecycle for Affiliate
    echo "4. Testing Affiliate Payout Lifecycle...\n";
    // Step A: Request Payout ₹2,000
    $affCode = 'AFF-TEST-' . rand(100, 999);
    $affEmail = 'aff_' . uniqid() . '@test.com';
    mysqli_query($conn, "INSERT INTO affiliates (affiliate_code, email, password_hash, full_name, available_balance) VALUES ('$affCode', '$affEmail', 'hash', 'Test Aff', 5000.00)");
    $affId = mysqli_insert_id($conn);

    // Create test payout method
    mysqli_query($conn, "INSERT INTO affiliate_payout_methods (affiliate_id, method_type, account_details) VALUES ($affId, 'bank_transfer', '{\"bank\":\"HDFC\"}')");
    $pmId = mysqli_insert_id($conn);
    // Step A: Request Payout ₹2,000
    echo "   Requesting ₹2,000 Payout for Affiliate #$affId...\n";
    $pReq = $payoutService->requestPayout($affId, 2000.00, $pmId);
    echo "   Status: " . ($pReq['success'] ? 'SUCCESS' : 'FAILED') . " | Remaining Balance: ₹" . $pReq['remaining_balance'] . "\n";
    $payoutId = $pReq['payout_id'] ?? 0;

    // Step B: Admin Approves Payout
    echo "   Admin Approving Payout #$payoutId...\n";
    $appOk = $payoutService->approvePayout($payoutId, 1);
    echo "   Approved: " . ($appOk ? 'YES' : 'NO') . "\n";

    // Step C: Admin Marks Paid with UTR
    echo "   Admin Marking Paid with UTR #UTR998811...\n";
    $paidOk = $payoutService->markPaid($payoutId, 'UTR998811', 1);
    echo "   Marked Paid: " . ($paidOk ? 'YES' : 'NO') . "\n";

    if ($paidOk) {
        echo "✅ SUCCESS (Payout request -> approval -> paid workflow complete)\n\n";
    } else {
        echo "❌ FAILED (Payout workflow error)\n\n";
    }

    echo "=== SPRINT 4 TEST COMPLETE ===";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
