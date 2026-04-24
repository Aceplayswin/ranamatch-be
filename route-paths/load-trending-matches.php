<?php
$resArr = array();
$resArr['status_code'] = "failed";
error_reporting(0);

// --- Strategy 1: Last 24h of play activity (any project) ---
$query_24h = "SELECT tbl_match_details, COUNT(*) as viewers
              FROM tblmatchplayed
              WHERE tbl_match_details != ''
                AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              GROUP BY tbl_match_details
              ORDER BY viewers DESC
              LIMIT 5";
$result = mysqli_query($conn, $query_24h);

$matches = array();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $detail = trim($row['tbl_match_details']);
        if (empty($detail)) continue;

        // Handle JSON-encoded match detail payloads
        $decoded = json_decode($detail, true);
        if (is_array($decoded)) {
            // Try common keys where match name may be stored
            $name = $decoded['matchName']
                 ?? $decoded['match_name']
                 ?? $decoded['MatchName']
                 ?? $decoded['title']
                 ?? $decoded['name']
                 ?? null;
            if ($name) $detail = trim($name);
        }

        if (!empty($detail)) {
            $matches[] = [
                'name'    => $detail,
                'viewers' => (int)$row['viewers'],
            ];
        }
    }
}

// --- Strategy 2: Fall back to last 7 days if 24h window is empty ---
if (empty($matches)) {
    $query_7d = "SELECT tbl_match_details, COUNT(*) as viewers
                 FROM tblmatchplayed
                 WHERE tbl_match_details != ''
                   AND STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p') >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY tbl_match_details
                 ORDER BY viewers DESC
                 LIMIT 5";
    $result7 = mysqli_query($conn, $query_7d);
    if ($result7 && mysqli_num_rows($result7) > 0) {
        while ($row = mysqli_fetch_assoc($result7)) {
            $detail = trim($row['tbl_match_details']);
            if (empty($detail)) continue;

            $decoded = json_decode($detail, true);
            if (is_array($decoded)) {
                $name = $decoded['matchName']
                     ?? $decoded['match_name']
                     ?? $decoded['MatchName']
                     ?? $decoded['title']
                     ?? $decoded['name']
                     ?? null;
                if ($name) $detail = trim($name);
            }

            if (!empty($detail)) {
                $matches[] = [
                    'name'    => $detail,
                    'viewers' => (int)$row['viewers'],
                ];
            }
        }
    }
}

// --- Strategy 3: All-time top if still empty ---
if (empty($matches)) {
    $query_all = "SELECT tbl_match_details, COUNT(*) as viewers
                  FROM tblmatchplayed
                  WHERE tbl_match_details != ''
                  GROUP BY tbl_match_details
                  ORDER BY viewers DESC
                  LIMIT 5";
    $result_all = mysqli_query($conn, $query_all);
    if ($result_all && mysqli_num_rows($result_all) > 0) {
        while ($row = mysqli_fetch_assoc($result_all)) {
            $detail = trim($row['tbl_match_details']);
            if (empty($detail)) continue;

            $decoded = json_decode($detail, true);
            if (is_array($decoded)) {
                $name = $decoded['matchName']
                     ?? $decoded['match_name']
                     ?? $decoded['MatchName']
                     ?? $decoded['title']
                     ?? $decoded['name']
                     ?? null;
                if ($name) $detail = trim($name);
            }

            if (!empty($detail)) {
                $matches[] = [
                    'name'    => $detail,
                    'viewers' => (int)$row['viewers'],
                ];
            }
        }
    }
}

// Build simple name array (backward compat) + rich array
$top_names = array_map(fn($m) => $m['name'], $matches);

// Ensure at least 2 entries so the ticker always shows 2 slots
$fallbacks = ["IPL: MI vs CSK · In Progress", "E-Sports: CSGO Major · Live"];
while (count($top_names) < 2) {
    $top_names[] = $fallbacks[count($top_names)];
}
while (count($matches) < 2) {
    $matches[] = ['name' => $fallbacks[count($matches)], 'viewers' => 0];
}

$resArr['status_code'] = "success";
$resArr['data']        = array_slice($top_names, 0, 5);   // simple list for old clients
$resArr['matches']     = array_slice($matches,   0, 5);   // rich list with viewer counts

echo json_encode($resArr);
exit();
?>
