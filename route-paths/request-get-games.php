<?php
if (!defined("ACCESS_SECURITY")) {
    echo "permission denied!";
    return;
}

// Headers and authentication are already handled by router/index.php
// which calls RequestHeaders->checkAllHeaders()

$resArr = array();
$resArr['status_code'] = "success";
$resArr['data'] = array();

// Pre-initialize all categories to prevent fallback loops on empty categories
$games = [
    'slots' => [],
    'casino' => [],
    'fishing' => [],
    'poker' => [],
    'turbo' => [],
    'live' => [],
    'casino_lobby' => [],
    'topslot' => []
];

// Optional: Filter by category if passed
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';

$where = "WHERE game_status = 1";
if ($category) {
    $where .= " AND game_category = '$category'";
}

$sql = "SELECT game_uid, game_name, game_category, game_provider, game_image as icon, is_featured, sort_order 
        FROM tbl_games 
        $where 
        ORDER BY sort_order ASC, id ASC";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Cast numeric fields
        $row['is_featured'] = (int)$row['is_featured'];
        $row['sort_order'] = (int)$row['sort_order'];
        
        // Group by category for the frontend
        $cat = $row['game_category'];
        if (!isset($games[$cat])) {
            $games[$cat] = array();
        }
        
        // Rename some keys to match existing frontend JSON structure
        $game_item = [
            "Game Name" => $row['game_name'],
            "Game UID" => $row['game_uid'],
            "Game Type" => $row['game_category'],
            "Game Provider" => $row['game_provider'],
            "icon" => $row['icon'],
            "is_featured" => $row['is_featured']
        ];
        
        $games[$cat][] = $game_item;

        // If featured, also add to topslot group
        if ($row['is_featured']) {
            if (!isset($games['topslot'])) {
                $games['topslot'] = array();
            }
            $games['topslot'][] = $game_item;
        }
    }
    
    $resArr['data'] = $games;
} else {
    $resArr['status_code'] = "error";
    $resArr['message'] = "Database query failed";
}

echo json_encode($resArr);
?>
