<?php

// defined all routes

$routes = [
    '/' => 'default',
    '/home' => 'admin/index.php',
    '/route-app-status' => 'route-paths/request-app-status.php',
    '/route-send-sms' => 'route-paths/services/sms/send-sms.php',
    '/route-login' => 'route-paths/request-login.php',
    '/route-create-account' => 'route-paths/request-create-account.php',
    '/route-change-password' => 'route-paths/request-change-password.php',
    '/route-reset-password' => 'route-paths/request-reset-password.php',
    '/route-claim-giftcard' => 'route-paths/request-claim-giftcard.php',
    
    '/route-account-info' => 'route-paths/request-account-info.php',
    '/route-allmember-records' => 'route-paths/load-all-members.php',
    '/route-devicemanager-records' => 'route-paths/load-devicemanager.php',
    '/route-recharge-request' => 'route-paths/request-recharge.php',
    '/route-recharge-records' => 'route-paths/load-recharge-records.php',
    '/route-transactions' => 'route-paths/load-transactions.php',
    '/route-team-reports' => 'route-paths/load-team-reports.php',
    
    '/route-withdraw-request' => 'route-paths/request-withdrawl.php',
    '/route-withdraw-records' => 'route-paths/load-withdraw-records.php',
    
    '/route-get-banklist' => 'route-paths/load-bank-list.json',
    '/route-deposit-info' => 'route-paths/load-deposit-details.php',
    '/route-get-bankcards' => 'route-paths/load-bank-cards.php',
    '/route-add-bankcard' => 'route-paths/request-add-bankcard.php',
    '/route-delete-bankcard' => 'route-paths/request-delete-bankcard.php',
    '/route-set-bankcard-primary' => 'route-paths/set-bankcard-primary.php',
    '/route-get-primary-bankcard' => 'route-paths/load-primary-bankcard.php',
    
    '/route-play-games' => 'route-paths/request-play-games.php',
    '/route-mygame-records' => 'route-paths/load-mygame-records.php',
    '/route-game-notifications' => 'route-paths/load-game-notifications.php',
    '/route-submit-ticket' => 'route-paths/request-submit-ticket.php',
    '/route-active-promotions' => 'route-paths/request-active-promotions.php',
    '/request-bonus-details' => 'route-paths/request-bonus-details.php',
    '/request-claim-bonus' => 'route-paths/request-claim-bonus.php',
    '/route-claim-cashback' => 'route-paths/request-claim-cashback.php',
    '/request-cancel-bonus' => 'route-paths/request-cancel-bonus.php',
    '/route-active-bonus-details' => 'route-paths/request-active-bonus-details.php',
    '/route-offer-promotions' => 'route-paths/request-offers.php',
    '/route-trending-matches' => 'route-paths/load-trending-matches.php',
    '/route-get-user-tickets' => 'route-paths/load-user-tickets.php',
    '/route-get-games' => 'route-paths/request-get-games.php',
    '/route-mark-broadcast-seen' => 'route-paths/request-mark-broadcast-seen.php',
];

?>