<?php
/**
 * Agent Sport Analysis / Risk Exposure Endpoint
 * Endpoint: GET /agent/analysis/sport-analysis
 */

if (!defined("ACCESS_SECURITY")) {
    define("ACCESS_SECURITY", "true");
}
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'agent') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Agent role required."]);
    exit;
}

$agentId = (int)$jwt_user_id;

// Fetch agent and downline subtree to capture all downline player bets
require_once __DIR__ . '/../../services/AgentTreeService.php';
$treeService = new AgentTreeService($conn);
$downlines = $treeService->getDescendants($agentId);
$agentIds = [$agentId];
foreach ($downlines as $d) {
    $agentIds[] = (int)$d['id'];
}
$inAgentIds = implode(',', array_unique($agentIds));

// Fetch active open/pending bets or recent bets excluding casino games
$sql = "SELECT pb.id, pb.game_type, pb.event_name, pb.market_name, pb.selection_name, 
               pb.side, pb.odds, pb.stake, pb.liability, pb.status, pb.created_at
        FROM player_bets pb
        WHERE pb.agent_id IN ($inAgentIds)
          AND LOWER(COALESCE(pb.game_type, '')) NOT LIKE '%casino%'
          AND LOWER(COALESCE(pb.event_name, '')) NOT LIKE '%casino%'
        ORDER BY pb.id DESC LIMIT 200";

$res = mysqli_query($conn, $sql);
$rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

function detectSport($gameType, $eventName, $marketName = '', $selectionName = '') {
    $combined = strtolower(($gameType ?? '') . ' ' . ($eventName ?? '') . ' ' . ($marketName ?? '') . ' ' . ($selectionName ?? ''));

    // Strictly exclude casino games, live dealer, slots, etc.
    if (strpos($combined, 'casino') !== false || strpos($combined, 'roulette') !== false || strpos($combined, 'slot') !== false || strpos($combined, 'teen patti') !== false || strpos($combined, 'andar bahar') !== false || strpos($combined, 'baccarat') !== false || strpos($combined, 'blackjack') !== false) {
        return null;
    }

    if (strpos($combined, 'cricket') !== false || strpos($combined, 'ipl') !== false || strpos($combined, 't20') !== false || strpos($combined, 'odi') !== false || strpos($combined, 'test') !== false || strpos($combined, 'wicket') !== false || strpos($combined, 'runs') !== false) {
        return 'Cricket';
    }
    if (strpos($combined, 'soccer') !== false || strpos($combined, 'football') !== false || strpos($combined, '1x2') !== false || strpos($combined, 'draw') !== false || strpos($combined, 'goal') !== false || strpos($combined, 'saba') !== false || strpos($combined, 'fc') !== false || strpos($combined, 'league') !== false) {
        return 'Soccer';
    }
    if (strpos($combined, 'tennis') !== false || strpos($combined, 'open') !== false || strpos($combined, 'wimbledon') !== false || strpos($combined, 'set ') !== false) {
        return 'Tennis';
    }
    if (strpos($combined, 'horse') !== false || strpos($combined, 'racing') !== false || strpos($combined, 'derby') !== false) {
        return 'Horse Racing';
    }
    if (strpos($combined, 'greyhound') !== false || strpos($combined, 'hound') !== false || strpos($combined, 'dogs') !== false) {
        return 'Greyhound';
    }
    return 'Sports';
}

$fixturesData = [
    'Cricket' => [],
    'Soccer' => [],
    'Tennis' => [],
    'Horse Racing' => [],
    'Greyhound' => []
];

$grouped = [];
foreach ($rows as $r) {
    $sport = detectSport($r['game_type'] ?? '', $r['event_name'] ?? '', $r['market_name'] ?? '', $r['selection_name'] ?? '');
    if (!$sport) {
        continue; // Exclude casino games completely
    }
    $eventName = !empty($r['event_name']) ? $r['event_name'] : 'Sports Fixture';
    $key = $sport . '___' . $eventName;

    if (!isset($grouped[$key])) {
        $isLive = (strtolower($r['status'] ?? '') === 'pending' || strtolower($r['status'] ?? '') === 'open' || strtolower($r['status'] ?? '') === 'live');
        $grouped[$key] = [
            'id' => (int)$r['id'],
            'sport' => $sport,
            'event' => $eventName,
            'date' => $isLive ? 'Live In-Play' : date('M d, H:i', strtotime($r['created_at'])),
            'totalBets' => 0,
            'totalAmount' => 0.0,
            'exposure' => 0.0,
            'maxProfit' => 0.0,
            'markets' => []
        ];
    }

    $stake = (float)$r['stake'];
    $liability = (float)$r['liability'];
    if ($liability <= 0) {
        $odds = (float)($r['odds'] ?? 1.95);
        $liability = $stake * max(0.5, $odds - 1);
    }

    $grouped[$key]['totalBets'] += 1;
    $grouped[$key]['totalAmount'] += $stake;
    // Net book exposure: negative for liability
    $grouped[$key]['exposure'] -= $liability;
    $grouped[$key]['maxProfit'] += $stake;

    $mktName = !empty($r['market_name']) ? $r['market_name'] : 'Match Odds';
    if (!isset($grouped[$key]['markets'][$mktName])) {
        $grouped[$key]['markets'][$mktName] = [
            'name' => $mktName,
            'bets' => 0,
            'exposure' => 0.0,
            'maxProfit' => 0.0
        ];
    }
    $grouped[$key]['markets'][$mktName]['bets'] += 1;
    $grouped[$key]['markets'][$mktName]['exposure'] -= $liability;
    $grouped[$key]['markets'][$mktName]['maxProfit'] += $stake;
}

$allFixtures = [];
foreach ($grouped as $g) {
    $sport = $g['sport'];
    $g['markets'] = array_values($g['markets']);
    if (isset($fixturesData[$sport])) {
        $fixturesData[$sport][] = $g;
    }
    $allFixtures[] = $g;
}

$sportsTabs = [
    ['name' => 'Cricket', 'count' => count($fixturesData['Cricket'])],
    ['name' => 'Soccer', 'count' => count($fixturesData['Soccer'])],
    ['name' => 'Tennis', 'count' => count($fixturesData['Tennis'])],
    ['name' => 'Horse Racing', 'count' => count($fixturesData['Horse Racing'])],
    ['name' => 'Greyhound', 'count' => count($fixturesData['Greyhound'])]
];

echo json_encode([
    'status' => 'success',
    'data' => [
        'sportsTabs' => $sportsTabs,
        'fixturesData' => $fixturesData,
        'allFixtures' => $allFixtures
    ]
]);
