<?php
$frontend_path = 'd:/winco-ishad/winco-frondend/src/components/jsondata/slotgames.js';
$content = file_get_contents($frontend_path);
$start = strpos($content, '[');
$end = strrpos($content, ']');
$json_content = substr($content, $start, $end - $start + 1);
preg_match_all('/\{(?:[^{}]*)\}/s', $json_content, $matches);

foreach (array_slice($matches[0], 0, 3) as $game_raw) {
    echo "--- RAW ---\n$game_raw\n";
    $game = [];
    preg_match_all('/"([^"]+)":\s*"([^"]*)"/', $game_raw, $pairs, PREG_SET_ORDER);
    foreach ($pairs as $pair) {
        $game[$pair[1]] = $pair[2];
    }
    echo "--- PARSED ---\n";
    print_r($game);
}
?>
