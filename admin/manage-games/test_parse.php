<?php
$content = <<<'EOD'
export const slotgames = [
    {
        "Game Name": "Mahjong Ways",
        "Game UID": "1189baca156e1bbbecc3b26651a63565",
        "Game Type": "Slot Game",
        "icon": "https://huidu-bucket.s3.ap-southeast-1.amazonaws.com/api/pg/Mahjong-Ways_rounded_1024.png"
    },
    {
        "Game Name": "Mahjong Ways 2",
        "Game UID": "ba2adf72179e1ead9e3dae8f0a7d4c07",
        "Game Type": "Slot Game",
        "icon": "https://huidu-bucket.s3.ap-southeast-1.amazonaws.com/api/pg/Mahjong-Ways2_rounded_1024.png"
    }
]
EOD;

preg_match_all('/\{(?:[^{}]*)\}/s', $content, $matches);
foreach ($matches[0] as $game_raw) {
    echo "RAW: $game_raw\n";
    $game = [];
    preg_match_all('/"([^"]+)":\s*"([^"]*)"/', $game_raw, $pairs, PREG_SET_ORDER);
    foreach ($pairs as $pair) {
        $game[$pair[1]] = $pair[2];
    }
    print_r($game);
}
?>
