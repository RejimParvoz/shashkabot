<?php
/**
 * Shashka Lite - O'rnatish skripti
 * Brauzerda BIR MARTA oching: https://topkons.uz/shashka/install.php
 * Jadvallarni yaratadi/yangilaydi. Tugagach, bu faylni o'chiring.
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Shashka Lite - O'rnatish</h2>";

function tryExec($sql, $label) {
    try {
        db()->exec($sql);
        echo "<p style='color:green'>OK: " . htmlspecialchars($label) . "</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'exists') !== false) {
            echo "<p style='color:#888'>Skip (bor): " . htmlspecialchars($label) . "</p>";
        } else {
            echo "<p style='color:red'>Xato (" . htmlspecialchars($label) . "): " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

try {
    // ---- users ----
    tryExec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `telegram_id` BIGINT UNSIGNED NOT NULL,
            `username` VARCHAR(50) DEFAULT NULL,
            `first_name` VARCHAR(100) NOT NULL DEFAULT '',
            `photo_url` TEXT DEFAULT NULL,
            `rating` INT NOT NULL DEFAULT 1000,
            `coins` BIGINT NOT NULL DEFAULT 0,
            `diamonds` BIGINT NOT NULL DEFAULT 0,
            `total_games` INT NOT NULL DEFAULT 0,
            `wins` INT NOT NULL DEFAULT 0,
            `losses` INT NOT NULL DEFAULT 0,
            `draws` INT NOT NULL DEFAULT 0,
            `equipped_board` VARCHAR(30) NOT NULL DEFAULT 'classic',
            `equipped_piece` VARCHAR(30) NOT NULL DEFAULT 'classic',
            `referred_by` BIGINT UNSIGNED DEFAULT NULL,
            `referral_count` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_active` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_telegram` (`telegram_id`),
            KEY `idx_rating` (`rating`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "users jadvali");

    // Eski bazaga yangi ustunlar (agar yo'q bo'lsa)
    tryExec("ALTER TABLE `users` ADD COLUMN `equipped_board` VARCHAR(30) NOT NULL DEFAULT 'classic'", "users.equipped_board");
    tryExec("ALTER TABLE `users` ADD COLUMN `equipped_piece` VARCHAR(30) NOT NULL DEFAULT 'classic'", "users.equipped_piece");
    tryExec("ALTER TABLE `users` ADD COLUMN `referred_by` BIGINT UNSIGNED DEFAULT NULL", "users.referred_by");
    tryExec("ALTER TABLE `users` ADD COLUMN `referral_count` INT NOT NULL DEFAULT 0", "users.referral_count");

    // ---- games ----
    tryExec("
        CREATE TABLE IF NOT EXISTS `games` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `mode` VARCHAR(20) NOT NULL DEFAULT 'classic',
            `bot_level` VARCHAR(20) DEFAULT NULL,
            `opponent_type` VARCHAR(10) NOT NULL DEFAULT 'bot',
            `result` ENUM('win','loss','draw') NOT NULL,
            `moves` INT NOT NULL DEFAULT 0,
            `rating_change` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "games jadvali");
    tryExec("ALTER TABLE `games` ADD COLUMN `opponent_type` VARCHAR(10) NOT NULL DEFAULT 'bot'", "games.opponent_type");

    // ---- shop_items (faqat tanga) ----
    tryExec("
        CREATE TABLE IF NOT EXISTS `shop_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(30) NOT NULL,
            `name` VARCHAR(60) NOT NULL,
            `type` ENUM('board','piece') NOT NULL,
            `price_coins` INT NOT NULL DEFAULT 0,
            `data` VARCHAR(255) NOT NULL DEFAULT '',
            `sort` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "shop_items jadvali");

    // ---- user_items (sotib olingan) ----
    tryExec("
        CREATE TABLE IF NOT EXISTS `user_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `item_code` VARCHAR(30) NOT NULL,
            `bought_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_item` (`user_id`, `item_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "user_items jadvali");

    // ---- matches (online o'yin) ----
    tryExec("
        CREATE TABLE IF NOT EXISTS `matches` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `p1_id` BIGINT UNSIGNED NOT NULL,
            `p2_id` BIGINT UNSIGNED DEFAULT NULL,
            `board` TEXT NOT NULL,
            `turn` TINYINT NOT NULL DEFAULT 2,
            `status` ENUM('waiting','active','finished') NOT NULL DEFAULT 'waiting',
            `winner_id` BIGINT UNSIGNED DEFAULT NULL,
            `move_count` INT NOT NULL DEFAULT 0,
            `last_from` VARCHAR(5) DEFAULT NULL,
            `last_to` VARCHAR(5) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_players` (`p1_id`, `p2_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "matches jadvali");

    // ---- Do'kon buyumlari (faqat tanga) ----
    $items = [
        ['board_classic', 'Klassik doska', 'board', 0, 'f0d9b5,b58863', 1],
        ['board_green', 'Yashil doska', 'board', 500, 'eeeed2,769656', 2],
        ['board_blue', 'Moviy doska', 'board', 800, 'dee3e6,8ca2ad', 3],
        ['board_dark', 'Tungi doska', 'board', 1200, 'b0b0b0,4a4a4a', 4],
        ['board_purple', 'Siyohrang doska', 'board', 2000, 'e6d8f0,7a5ba6', 5],
        ['piece_classic', 'Klassik toshlar', 'piece', 0, 'fff,cfcfcf,666,161616', 1],
        ['piece_gold', 'Oltin toshlar', 'piece', 700, 'fff6d0,e0b830,7a5c00,3a2c00', 2],
        ['piece_red', 'Qizil toshlar', 'piece', 900, 'ffd6d6,d83030,5a0000,2a0000', 3],
        ['piece_ocean', 'Okean toshlar', 'piece', 1500, 'd6f0ff,2090d8,003a5a,001a2a', 4],
        ['piece_neon', 'Neon toshlar', 'piece', 2500, 'e0ffe0,30d830,2a005a,10002a', 5],
    ];
    $stmt = db()->prepare(
        "INSERT IGNORE INTO shop_items (code, name, type, price_coins, data, sort) VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $it) { $stmt->execute($it); }
    echo "<p style='color:green'>OK: " . count($items) . " ta do'kon buyumi qo'shildi</p>";

    echo "<h3 style='color:green'>Tayyor! O'rnatish muvaffaqiyatli.</h3>";
    echo "<p>1) <b>install.php</b> faylini o'chiring (xavfsizlik).</p>";
    echo "<p>2) Webhook: <a href='set_webhook.php?do=set'>set_webhook.php?do=set</a></p>";
    echo "<p>3) O'yin: <a href='index.php'>index.php</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Xato: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<p>config.php dagi baza ma'lumotlarini tekshiring.</p>";
}
