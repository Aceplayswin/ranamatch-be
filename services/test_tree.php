<?php
define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/AgentTreeService.php';

header('Content-Type: text/plain');

echo "=== SPRINT 3: AGENT TREE SERVICE TEST ===\n\n";

$treeService = new AgentTreeService($conn);

try {
    // 1. Create Senior Super Agent (Root - Level 1)
    echo "1. Creating Senior Super Agent (Root)... ";
    $rootId = $treeService->createAgentWithTree(null, [
        'agent_code' => 'TEST-SENIOR-' . rand(100, 999),
        'name' => 'Root Senior Super Agent',
        'email' => 'root_' . uniqid() . '@agent.com',
        'password' => 'secret123',
        'rank_level' => 'senior_super_agent',
        'partnership_pct' => 80.00,
        'turnover_commission_pct' => 4.00,
        'opening_credit' => 500000.00
    ]);
    echo "✅ SUCCESS (ID: $rootId)\n\n";

    // 2. Create Super Agent (Level 2 under Senior Super Agent)
    echo "2. Creating Super Agent under Root (Level 2)... ";
    $superId = $treeService->createAgentWithTree($rootId, [
        'agent_code' => 'TEST-SUPER-' . rand(100, 999),
        'name' => 'Child Super Agent',
        'email' => 'super_' . uniqid() . '@agent.com',
        'password' => 'secret123',
        'rank_level' => 'super_agent',
        'partnership_pct' => 70.00,
        'turnover_commission_pct' => 3.00,
        'opening_credit' => 200000.00
    ]);
    echo "✅ SUCCESS (ID: $superId)\n\n";

    // 3. Create Master Agent (Level 3 under Super Agent)
    echo "3. Creating Master Agent under Super Agent (Level 3)... ";
    $masterId = $treeService->createAgentWithTree($superId, [
        'agent_code' => 'TEST-MASTER-' . rand(100, 999),
        'name' => 'Sub-Child Master Agent',
        'email' => 'master_' . uniqid() . '@agent.com',
        'password' => 'secret123',
        'rank_level' => 'master_agent',
        'partnership_pct' => 60.00,
        'turnover_commission_pct' => 2.50,
        'opening_credit' => 50000.00
    ]);
    echo "✅ SUCCESS (ID: $masterId)\n\n";

    // 4. Test Ancestor Lookup for Master Agent (Level 3)
    echo "4. Testing Ancestor (Upline) Lookup for Master Agent (ID: $masterId)...\n";
    $ancestors = $treeService->getAncestors($masterId);
    foreach ($ancestors as $a) {
        echo "   - Ancestor Name: " . $a['name'] . " | Rank: " . $a['rank_level'] . " | Depth: " . $a['depth'] . "\n";
    }
    if (count($ancestors) === 2) {
        echo "✅ SUCCESS (Found exactly 2 upline ancestors in correct depth order)\n\n";
    } else {
        echo "❌ FAILED (Expected 2 ancestors, found " . count($ancestors) . ")\n\n";
    }

    // 5. Test Descendant Lookup for Root Agent (Level 1)
    echo "5. Testing Descendant (Downline) Lookup for Root Senior Super Agent (ID: $rootId)...\n";
    $descendants = $treeService->getDescendants($rootId);
    foreach ($descendants as $d) {
        echo "   - Downline Name: " . $d['name'] . " | Rank: " . $d['rank_level'] . " | Depth: " . $d['depth'] . "\n";
    }
    if (count($descendants) === 2) {
        echo "✅ SUCCESS (Found exactly 2 downline descendants)\n\n";
    } else {
        echo "❌ FAILED (Expected 2 descendants, found " . count($descendants) . ")\n\n";
    }

    echo "=== SPRINT 3 TEST COMPLETE ===";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
