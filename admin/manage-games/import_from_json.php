<?php
define("ACCESS_SECURITY", true);
require_once('../../security/config.php');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$frontend_path = 'd:/winco-ishad/winco-frondend/src/components/jsondata/';

$files = [
    ['file' => 'slotgames.js', 'category' => 'slots', 'var' => 'slotgames'],
    ['file' => 'cusinolive.js', 'category' => 'casino', 'var' => 'cusinolive'],
    ['file' => 'turbogames.js', 'category' => 'turbo', 'var' => 'turbogames'],
    ['file' => 'fishgame.js', 'category' => 'fishing', 'var' => 'fishgames'],
    ['file' => 'indianpokergame.js', 'category' => 'poker', 'var' => 'indianpokergames'],
    ['file' => 'topslot.js', 'category' => 'slots', 'var' => 'topslot', 'featured' => 1],
    ['file' => 'live.js', 'category' => 'live', 'var' => 'liveSport']
];

$import_count = 0;
$error_count = 0;

foreach ($files as $f) {
    $full_path = $frontend_path . $f['file'];
    if (!file_exists($full_path)) {
        continue;
    }

    $content = file_get_contents($full_path);
    
    // Find the array part [...]
    $start = strpos($content, '[');
    $end = strrpos($content, ']');
    
    if ($start === false || $end === false) {
        continue;
    }
    
    $json_content = substr($content, $start, $end - $start + 1);
    
    // Remove comments
    // $json_content = preg_replace('!/\*.*?\*/!s', '', $json_content); 
    // $json_content = preg_replace('!//.*?\n!', '', $json_content);    
    
    // Extract objects {...}
    // We use a more careful regex to avoid issues with nested stuff (though there is none)
    preg_match_all('/\{.*?\}/s', $json_content, $matches);
    
    $games_raw_list = $matches[0];

    // echo "Found " . count($games_raw_list) . " potential games in " . $f['file'] . ". Importing...\n";

    $file_index = 0;
    foreach ($games_raw_list as $game_raw) {
        $file_index++;
        // Since json_decode is failing due to invisible control characters or encoding issues,
        // we will use a manual regex-based parser for these simple objects.
        
        $game = [];
        
        // Extract Game Name
        if (preg_match('/"Game Name"\s*:\s*"(.*?)"/i', $game_raw, $m)) $game['name'] = $m[1];
        else if (preg_match('/Game Name\s*:\s*\'(.*?)\'/i', $game_raw, $m)) $game['name'] = $m[1];

        // Extract Game UID
        if (preg_match('/"Game UID"\s*:\s*"(.*?)"/i', $game_raw, $m)) $game['uid'] = $m[1];
        else if (preg_match('/Game UID\s*:\s*\'(.*?)\'/i', $game_raw, $m)) $game['uid'] = $m[1];

        // Extract Icon
        if (preg_match('/"icon"\s*:\s*"(.*?)"/i', $game_raw, $m)) $game['icon'] = $m[1];
        else if (preg_match('/icon\s*:\s*\'(.*?)\'/i', $game_raw, $m)) $game['icon'] = $m[1];

        // Extract Provider/Type
        if (preg_match('/"Game Provider"\s*:\s*"(.*?)"/i', $game_raw, $m)) $game['provider'] = $m[1];
        else if (preg_match('/"Game Type"\s*:\s*"(.*?)"/i', $game_raw, $m)) $game['provider'] = $m[1];

        $game_uid = $game['uid'] ?? null;
        $game_name = $game['name'] ?? null;
        $game_provider = $game['provider'] ?? 'Unknown';
        $game_image = $game['icon'] ?? '';
        $game_category = $f['category'];
        $is_featured = $f['featured'] ?? 0;
        $sort_order = $file_index;

        if (!$game_uid || !$game_name) {
            $error_count++;
            // echo "Could not parse required fields in: " . substr($game_raw, 0, 50) . "...\n";
            continue;
        }

        $stmt = $conn->prepare("INSERT INTO tbl_games (game_uid, game_name, game_category, game_provider, game_image, is_featured, sort_order) 
                                VALUES (?, ?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE 
                                game_name = VALUES(game_name), 
                                game_image = VALUES(game_image),
                                sort_order = VALUES(sort_order),
                                is_featured = CASE WHEN is_featured = 1 THEN 1 ELSE VALUES(is_featured) END");
        
        $stmt->bind_param("sssssii", $game_uid, $game_name, $game_category, $game_provider, $game_image, $is_featured, $sort_order);
        


        if ($stmt->execute()) {
            $import_count++;
        } else {
            $error_count++;
            // Log error
        }
        $stmt->close();
    }
}

$res = [
    "status" => "success",
    "imported" => $import_count,
    "errors" => $error_count,
    "message" => "Sync Finished! Total Imported/Updated: $import_count, Errors: $error_count"
];

header('Content-Type: application/json');
echo json_encode($res);
?>
