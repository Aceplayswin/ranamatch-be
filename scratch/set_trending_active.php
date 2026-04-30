<?php
define('ACCESS_SECURITY', 'true');
include 'd:/xampp/htdocs/security/config.php';

$trending_names = [
    'Crazy Time', 'Monopoly', 'Andar Bahar', 'Crazy Pachinko', 
    'Football Studio', 'Craps', 'Fan Tan', 'Dead or Alive: Saloon'
];

echo "SETTING ALL CASINO GAMES TO INACTIVE...\n";
mysqli_query($conn, "UPDATE tbl_games SET game_status = 0 WHERE game_category = 'casino'");
echo "Affected rows: " . mysqli_affected_rows($conn) . "\n";

echo "SETTING TRENDING GAMES TO ACTIVE...\n";
foreach($trending_names as $name) {
    mysqli_query($conn, "UPDATE tbl_games SET game_status = 1 WHERE game_name LIKE '%$name%' AND game_category = 'casino'");
    echo "Activated $name: " . mysqli_affected_rows($conn) . "\n";
}

echo "MIGRATION COMPLETE.\n";
?>
