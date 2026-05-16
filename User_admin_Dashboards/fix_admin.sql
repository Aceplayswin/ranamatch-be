USE `user&admin`;
INSERT INTO `users` (`full_name`, `username`, `email`, `mobile`, `password`, `role`, `wallet_balance`)
VALUES ('Admin', 'admin', 'admin@asura.com', '9999999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0.00)
ON DUPLICATE KEY UPDATE role='admin';
