<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../access_validate.php';

session_start();
$access = new AccessValidate();
if ($access->validate() == "false") {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    die();
}

// Handle JSON body (for reorder drag-drop)
$json_body = json_decode(file_get_contents('php://input'), true);
if ($json_body && isset($json_body['action'])) {
    $action = $json_body['action'];
} else {
    $action = $_POST['action'] ?? '';
}
$id = (int)($_POST['id'] ?? 0);

if (!$id && !in_array($action, ['save_game', 'bulk_import', 'reorder'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    die();
}

switch ($action) {
    case 'reorder':
        $order = $json_body['order'] ?? [];
        if (empty($order)) {
            echo json_encode(['success' => false, 'error' => 'No order data']);
            break;
        }
        $stmt = $conn->prepare("UPDATE tbl_games SET sort_order = ? WHERE id = ?");
        foreach ($order as $item) {
            $pos = (int)$item['pos'];
            $gid = (int)$item['id'];
            $stmt->bind_param("ii", $pos, $gid);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['success' => true, 'updated' => count($order)]);
        break;

    case 'update_sort':
        $val = (int)($_POST['val'] ?? 0);
        $sql = "UPDATE tbl_games SET sort_order = $val WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        }
        break;

    case 'toggle_status':
        $sql = "UPDATE tbl_games SET game_status = 1 - game_status WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        }
        break;

    case 'toggle_featured':
        $sql = "UPDATE tbl_games SET is_featured = 1 - is_featured WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            $res = mysqli_query($conn, "SELECT is_featured FROM tbl_games WHERE id = $id");
            $row = mysqli_fetch_assoc($res);
            echo json_encode(['success' => true, 'is_featured' => $row['is_featured']]);
        }
        break;

    case 'update_sort':
        $val = (int)$_POST['val'];
        $sql = "UPDATE tbl_games SET sort_order = $val WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        }
        break;

    case 'save_game':
        $game_name = mysqli_real_escape_string($conn, $_POST['game_name']);
        $game_uid = mysqli_real_escape_string($conn, $_POST['game_uid']);
        $game_category = mysqli_real_escape_string($conn, $_POST['game_category']);
        $game_provider = mysqli_real_escape_string($conn, $_POST['game_provider']);
        $game_image = mysqli_real_escape_string($conn, $_POST['game_image']);
        
        if ($id > 0) {
            // Update
            $sql = "UPDATE tbl_games SET 
                    game_name = '$game_name', 
                    game_uid = '$game_uid', 
                    game_category = '$game_category', 
                    game_provider = '$game_provider', 
                    game_image = '$game_image' 
                    WHERE id = $id";
        } else {
            // Insert or Update on Duplicate Key
            $sql = "INSERT INTO tbl_games (game_name, game_uid, game_category, game_provider, game_image, game_status, sort_order) 
                    VALUES ('$game_name', '$game_uid', '$game_category', '$game_provider', '$game_image', 1, 0)
                    ON DUPLICATE KEY UPDATE 
                    game_name = VALUES(game_name),
                    game_category = VALUES(game_category),
                    game_provider = VALUES(game_provider),
                    game_image = VALUES(game_image)";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database Error: ' . mysqli_error($conn)]);
        }
        break;

    case 'delete_game':
        $sql = "DELETE FROM tbl_games WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        }
        break;

    case 'bulk_import':
        $data = trim($_POST['data'] ?? '');
        
        // Try to decode JSON
        $games = json_decode($data, true);
        
        // If json_decode fails, it might be due to single quotes or other JS-isms.
        if (!$games && (strpos($data, '[') !== false || strpos($data, '{') !== false)) {
            $tmp_data = str_replace("'", '"', $data);
            $games = json_decode($tmp_data, true);
        }

        if (!$games) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON format. Please ensure it is a valid JSON array or object.']);
            die();
        }

        // If it's a single object (associative array), wrap it in an array
        if (is_array($games) && count($games) > 0 && !isset($games[0])) {
            $games = [$games];
        }
        
        if (!is_array($games)) {
            echo json_encode(['status' => 'error', 'message' => 'Input must be a JSON array or object.']);
            die();
        }

        
        $count = 0;
        foreach ($games as $game) {
            // Handle both simple keys and JS file keys
            $name = mysqli_real_escape_string($conn, $game['gameName'] ?? $game['name'] ?? $game['Game Name'] ?? '');
            $uid = mysqli_real_escape_string($conn, $game['gameUid'] ?? $game['uid'] ?? $game['Game UID'] ?? '');
            
            // For category, if it's "Slot Game" or similar, we might need to map it
            $cat_raw = $game['category'] ?? $game['Game Type'] ?? 'slots';
            $category = strtolower($cat_raw);
            if (strpos($category, 'slot') !== false) $category = 'slots';
            else if (strpos($category, 'casino') !== false) $category = 'casino';
            else if (strpos($category, 'fish') !== false) $category = 'fishing';
            else if (strpos($category, 'poker') !== false) $category = 'poker';
            
            $category = mysqli_real_escape_string($conn, $category);
            $provider = mysqli_real_escape_string($conn, $game['provider'] ?? $game['Game Provider'] ?? 'Unknown');
            $image = mysqli_real_escape_string($conn, $game['iconUrl'] ?? $game['icon'] ?? $game['Game Icon'] ?? $game['gameImage'] ?? '');

            
            if (!$name || !$uid) continue;
            
            $sql = "INSERT INTO tbl_games (game_name, game_uid, game_category, game_provider, game_image, game_status, sort_order) 
                    VALUES ('$name', '$uid', '$category', '$provider', '$image', 1, 0)
                    ON DUPLICATE KEY UPDATE 
                    game_name = VALUES(game_name),
                    game_category = VALUES(game_category),
                    game_provider = VALUES(game_provider),
                    game_image = VALUES(game_image)";
            
            if (mysqli_query($conn, $sql)) {
                $count++;
            }
        }
        
        echo json_encode(['status' => 'success', 'count' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
?>
