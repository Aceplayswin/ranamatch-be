<?php
define("ACCESS_SECURITY", true);
include __DIR__ . '/../security/config.php';

$tables_to_truncate = [
    'tbl_bonus_redemptions',
    'tbl_broadcast_views',
    'tbl_cashback_logs',
    'tbl_support_tickets',
    'tbl_ticket_attachments',
    'tbl_ticket_replies',
    'tblallbankcards',
    'tblallnotices',
    'tblautopayments',
    'tblavailablerewards',
    'tblgamesessions',
    'tblgiftcards',
    'tblmatchplayed',
    'tblmatchrecords',
    'tblmerchantrecords',
    'tblotherstransactions',
    'tblrecentotp',
    'tbltodaywinners',
    'tblusersactivity',
    'tblusersdata',
    'tblusersrecharge',
    'tbluserswithdraw',
    'tblviptransactions'
];

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

foreach($tables_to_truncate as $table) {
    if(mysqli_query($conn, "TRUNCATE TABLE $table")) {
        echo "Truncated: $table\n";
    } else {
        echo "Failed to truncate $table: " . mysqli_error($conn) . "\n";
    }
}

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

echo "Database successfully wiped for fresh launch! (Game details and Admin settings preserved)\n";
?>
