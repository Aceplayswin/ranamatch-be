<?php
if (!defined("ACCESS_SECURITY")) {
    echo "permission denied!";
    return;
}

$resArr = array();
$resArr['status_code'] = "success";
$resArr['data'] = array();

// Optional: Filter by category, provider, or navbar_category if passed
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$provider = isset($_GET['provider']) ? mysqli_real_escape_string($conn, $_GET['provider']) : '';
$navbar_category = isset($_GET['navbar_category']) ? mysqli_real_escape_string($conn, $_GET['navbar_category']) : '';

$where = "WHERE game_status = 1";
if ($category) {
    $where .= " AND game_category = '$category'";
}
if ($provider) {
    $where .= " AND game_provider = '$provider'";
}
if ($navbar_category) {
    $where .= " AND navbar_category = '$navbar_category'";
}

$sql = "SELECT game_uid, game_name, game_category, navbar_category, game_provider, game_image as icon, is_featured, sort_order 
        FROM tbl_games 
        $where 
        ORDER BY sort_order ASC, id ASC";

$result = mysqli_query($conn, $sql);
$games = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cat = $row['game_category'];
        if (!isset($games[$cat])) {
            $games[$cat] = array();
        }
        
        $games[$cat][] = [
            "Game Name" => $row['game_name'],
            "Game UID" => $row['game_uid'],
            "Game Type" => $row['game_category'],
            "navbar_category" => $row['navbar_category'] ?: "",
            "Navbar Category" => $row['navbar_category'] ?: "",
            "Game Provider" => $row['game_provider'],
            "icon" => $row['icon'],
            "is_featured" => (int)$row['is_featured'],
            "is_roulette" => 0
        ];
    }
    $resArr['data'] = $games;
} else {
    $resArr['status_code'] = "error";
    $resArr['message'] = "Database query failed";
}

echo json_encode($resArr);
?>