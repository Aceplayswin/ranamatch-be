<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$trending_names = [
    'Crazy Time', 'Monopoly', 'Andar Bahar', 'Crazy Pachinko', 'Crazy Punchuk', 
    'Football Studio', 'Craps', 'Fan Tan', 'Dead or Alive Saloon'
];

echo "SEARCHING FOR TRENDING GAMES:\n";

foreach($trending_names as $name) {
    $res = mysqli_query($conn, "SELECT game_name, game_uid FROM tbl_games WHERE game_name LIKE '%$name%'");
    while($row = mysqli_fetch_assoc($res)) {
        echo "Name: " . $row['game_name'] . " | UID: " . $row['game_uid'] . "\n";
    }
}
?>
