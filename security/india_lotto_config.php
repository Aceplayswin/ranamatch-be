<?php
/**
 * Shared configuration for India Lotto (Running10)
 */

$INDIA_LOTTO_GAME_MAP = [
    '1'  => 'Bhagyathara (kerala-ww)',
    '2'  => 'Sthree Sakthi (kerala-ss)',
    '3'  => 'Dhanalekshmi (kerala-ff)',
    '4'  => 'Karunya Plus (kerala-kn)',
    '5'  => 'Suvarana Keralam (kerala-nr)',
    '6'  => 'Karunya (kerala-kr)',
    '7'  => 'Samrudhi (kerala-ak)',
    '8'  => 'Online Only (kerala-oa)',
    '9'  => 'Christmax New Year Bumper (kerala-br)',
    '10' => 'Summer Bumper (kerala-br)',
    '11' => 'Vishu Bumper (kerala-br)',
    '12' => 'Monsoon Bumper (kerala-br)',
    '13' => 'Thiruvonam Bumper (kerala-br)',
    '14' => 'Pooja Bumper (kerala-br)',
    '27' => '1.5Min Car Race',
    '28' => 'WinGo 1mins',
    '29' => 'WinGo 3mins',
    '30' => 'Dear Nagaland Lottery 1PM',
    '31' => 'Dear Sikkim Lottery 6PM',
    '32' => 'Dear Nagaland Lottery 8PM',
    '33' => 'Dear Online Only 1PM',
    '34' => 'Dear Online Only 6PM',
    '35' => 'Dear Online Only 8PM',
    '36' => 'BSE SENSEX Matka',
    '37' => '1Min 3D Game',
    '40' => '3Min 3D Game',
    '43' => '1Min 4D Game',
    '46' => '3Min 4D Game',
    '49' => '1Min 5D Game',
    '52' => '3Min 5D Game',
    '55' => '3Min Car Race',
    '56' => 'TW TAIEX Matka',
    '57' => 'DOW JONES Matka',
    '58' => 'NASDAQ Matka',
    '59' => '10Min Speed Matka',
    '60' => '4Min Speed Matka',
    '61' => '6Min Speed Matka',
    '62' => '1Min Fast Matka',
    '63' => '3Min Fast Matka',
    '64' => '1Min Color Game',
    '65' => '3Min Color Game',
    '66' => '1Min 7Up7Down',
    '67' => '3Min 7Up7Down',
    '68' => 'NIFTY 50 Matka',
    '69' => 'Nifty Bank Matka',
    '70' => 'BSE MIDCAP Matka',
    '71' => 'BSE Smallcap Matka',
    '72' => 'KERALA 3D Game',
    '73' => 'KERALA 4D Game',
    '74' => 'KERALA 5D Game',
    '75' => 'DEAR 1PM 3D Game',
    '76' => 'DEAR 1PM 4D Game',
    '77' => 'DEAR 1PM 5D Game',
    '78' => 'DEAR 6PM 3D Game',
    '79' => 'DEAR 6PM 4D Game',
    '80' => 'DEAR 6PM 5D Game',
    '81' => 'DEAR 8PM 3D Game',
    '82' => 'DEAR 8PM 4D Game',
    '83' => 'DEAR 8PM 5D Game',
    '84' => 'SHILLONG TEER',
    '85' => '1Min SHILLONG TEER ONLINE',
    '86' => '2Min SHILLONG TEER ONLINE',
    '87' => '1Min 2D Game',
    '88' => '3Min 2D Game',
    '89' => '1Min Dice',
    '90' => '3Min Dice',
    '91' => 'Lucky 28 1Min',
    '92' => 'Lucky 28 3Min',
];

/**
 * Returns the game name for a given India Lotto ID.
 * Falls back to "India Lotto (ID: X)" if not found in map.
 */
function getIndiaLottoGameName($gameId, $conn = null) {
    global $INDIA_LOTTO_GAME_MAP;
    
    // 1. Try our shared map first
    if (isset($INDIA_LOTTO_GAME_MAP[$gameId])) {
        return $INDIA_LOTTO_GAME_MAP[$gameId];
    }
    
    // 2. Try database lookup if connection provided
    if ($conn) {
        $gameIdSafe = mysqli_real_escape_string($conn, $gameId);
        $res = mysqli_query($conn, "SELECT game_name FROM tbl_games WHERE game_uid = '$gameIdSafe' LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row['game_name'];
        }
    }
    
    // 3. Ultimate Fallback
    return "India Lotto (Game ID: $gameId)";
}
