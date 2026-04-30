<?php
$resArr = array();
$resArr['status_code'] = "failed";
error_reporting(0);

// --- Strategy 1: Real-Time SABA Sports (Live matches from the last 2 hours) ---
// We look for 'wait' status matches or very recent activity in sports
$query_live_saba = "SELECT tbl_match_details, COUNT(*) as bet_count
                    FROM tblmatchplayed
                    WHERE (LOWER(tbl_project_name) LIKE '%saba%' OR LOWER(tbl_project_name) LIKE '%sports%')
                      AND tbl_match_details != ''
                      AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                    GROUP BY tbl_match_details
                    ORDER BY bet_count DESC
                    LIMIT 5";
$result = mysqli_query($conn, $query_live_saba);

$matches = array();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $detail = trim($row['tbl_match_details']);
        if (empty($detail)) continue;

        // Realistic viewer count: Base (bets * 15) + Random (50-200)
        $viewers = ((int)$row['bet_count'] * 15) + rand(50, 200);

        $matches[] = [
            'name'    => $detail,
            'viewers' => $viewers,
            'is_live' => true
        ];
    }
}

// --- Strategy 2: Recent Activity (Last 24h) if SABA is empty ---
if (count($matches) < 3) {
    $query_24h = "SELECT tbl_match_details, COUNT(*) as bet_count
                  FROM tblmatchplayed
                  WHERE tbl_match_details != ''
                    AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  GROUP BY tbl_match_details
                  ORDER BY bet_count DESC
                  LIMIT 5";
    $result24 = mysqli_query($conn, $query_24h);
    while ($row = mysqli_fetch_assoc($result24)) {
        $detail = trim($row['tbl_match_details']);
        if (empty($detail)) continue;
        
        // Check if already in list
        $exists = false;
        foreach($matches as $m) { if($m['name'] == $detail) { $exists = true; break; } }
        if($exists) continue;

        $viewers = ((int)$row['bet_count'] * 8) + rand(20, 100);
        $matches[] = [
            'name'    => $detail,
            'viewers' => $viewers,
            'is_live' => false
        ];
    }
}

// --- Strategy 3: Fallbacks ---
$fallbacks = [
    ['name' => "IPL 2024 - Chennai Super Kings vs Gujarat Titans (Cricket)", 'viewers' => rand(1200, 1500), 'is_live' => true],
    ['name' => "ITALY SERIE A - Lazio vs Udinese (Soccer)", 'viewers' => rand(800, 1100), 'is_live' => true],
    ['name' => "E-SPORTS - CS:GO PGL Major Copenhagen (Live)", 'viewers' => rand(2500, 3200), 'is_live' => true],
];

while (count($matches) < 5) {
    $matches[] = $fallbacks[count($matches) % 3];
}

$rich_matches = array_slice($matches, 0, 5);
$simple_names = array_map(fn($m) => $m['name'], $rich_matches);

$resArr['status_code'] = "success";
$resArr['data']        = $simple_names;   // simple list for backward compatibility
$resArr['matches']     = $rich_matches;   // rich list with realistic viewer counts

echo json_encode($resArr);
exit();
?>
