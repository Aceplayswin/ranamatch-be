<?php
define("ACCESS_SECURITY", "true");
include "../security/config.php";

echo "Starting sports database migration...\n";

// Fetch all sports bets where selection is empty or NULL
$res = mysqli_query($conn, "SELECT id, tbl_bet_type, tbl_uniq_id, tbl_match_details FROM tblmatchplayed WHERE tbl_selection IS NULL OR tbl_selection = '' OR tbl_selection = '-'");

if (!$res) {
    die("Database query failed: " . mysqli_error($conn) . "\n");
}

$updated_count = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $id = $row['id'];
    $old_bet_type = $row['tbl_bet_type'];
    $uniq_id = $row['tbl_uniq_id'];
    $details = $row['tbl_match_details'];

    // We only migrate if the old tbl_bet_type has sports characteristics (like ' - ', or is a long string, and is not 'Back' or 'Lay')
    if (!empty($old_bet_type) && !in_array($old_bet_type, ['Back', 'Lay']) && (strlen($old_bet_type) > 5 || strpos($old_bet_type, ' - ') !== false)) {
        // Move old_bet_type to selection
        $new_selection = $old_bet_type;

        // Default bet_type to "Back"
        $new_bet_type = "Back";

        // If the details or choice contains Lay characteristics, we can check. Otherwise "Back" is safe.
        if (stripos($new_selection, 'lay') !== false) {
            $new_bet_type = "Lay";
        }

        $e_sel = mysqli_real_escape_string($conn, $new_selection);
        $e_bt = mysqli_real_escape_string($conn, $new_bet_type);

        $upd = mysqli_query($conn, "UPDATE tblmatchplayed SET tbl_selection = '$e_sel', tbl_bet_type = '$e_bt' WHERE id = $id");
        if ($upd) {
            $updated_count++;
            echo "Migrated ID $id (Ref: $uniq_id): Selection = '$new_selection', Bet Type = '$new_bet_type'\n";
        } else {
            echo "Failed to migrate ID $id: " . mysqli_error($conn) . "\n";
        }
    }
}

echo "Migration finished. Total records updated: $updated_count\n";
?>
