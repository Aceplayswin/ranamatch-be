<?php
require_once __DIR__ . '/config/database.php';

$user = getAuthUser($conn);
respond(true, "User fetched", ["user" => $user]);
?>
