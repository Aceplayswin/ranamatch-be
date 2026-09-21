<?php
define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true") {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$query = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';

$results = [];

if (strlen($query) >= 1) {
    // Search in tbl_games first
    $sql = "SELECT DISTINCT game_name, game_provider, game_category, game_image, game_uid 
            FROM tbl_games 
            WHERE game_name LIKE '%$query%' 
               OR game_provider LIKE '%$query%' 
               OR game_category LIKE '%$query%' 
            ORDER BY is_featured DESC, game_name ASC 
            LIMIT 15";
    
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $results[] = [
                'game_name' => $row['game_name'],
                'game_provider' => $row['game_provider'] ?? 'Standard',
                'game_category' => $row['game_category'] ?? 'Game',
                'game_image' => $row['game_image'] ?? '',
                'game_uid' => $row['game_uid'] ?? ''
            ];
        }
    }

    // Also search distinct project names from tblmatchplayed that might not be in tbl_games
    $sql_match = "SELECT DISTINCT tbl_project_name, tbl_provider 
                  FROM tblmatchplayed 
                  WHERE tbl_project_name LIKE '%$query%' 
                     OR tbl_provider LIKE '%$query%' 
                  LIMIT 10";
    $res_match = mysqli_query($conn, $sql_match);
    if ($res_match) {
        $existing_names = array_column($results, 'game_name');
        while ($row = mysqli_fetch_assoc($res_match)) {
            $pname = $row['tbl_project_name'];
            if (!in_array($pname, $existing_names)) {
                $results[] = [
                    'game_name' => $pname,
                    'game_provider' => $row['tbl_provider'] ?? 'Standard',
                    'game_category' => 'Betting Log',
                    'game_image' => '',
                    'game_uid' => ''
                ];
                $existing_names[] = $pname;
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => $results]);
?>
