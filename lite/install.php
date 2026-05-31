<?php
/**
 * Shashka Lite - O'rnatish skripti
 * Brauzerda BIR MARTA oching: https://topkons.uz/shashka/install.php
 * Jadvallarni yaratadi. Tugagach, bu faylni o'chiring.
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Shashka Lite - O'rnatish</h2>";

try {
    db()->exec("
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
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_active` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_telegram` (`telegram_id`),
            KEY `idx_rating` (`rating`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>OK: <b>users</b> jadvali yaratildi</p>";

    db()->exec("
        CREATE TABLE IF NOT EXISTS `games` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `mode` VARCHAR(20) NOT NULL DEFAULT 'classic',
            `bot_level` VARCHAR(20) DEFAULT NULL,
            `result` ENUM('win','loss','draw') NOT NULL,
            `moves` INT NOT NULL DEFAULT 0,
            `rating_change` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>OK: <b>games</b> jadvali yaratildi</p>";

    echo "<h3 style='color:green'>Tayyor! O'rnatish muvaffaqiyatli.</h3>";
    echo "<p>1) Endi <b>install.php</b> faylini o'chiring (xavfsizlik).</p>";
    echo "<p>2) Webhook: <a href='set_webhook.php?do=set'>set_webhook.php?do=set</a></p>";
    echo "<p>3) O'yin: <a href='index.php'>index.php</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Xato: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<p>config.php dagi baza ma'lumotlarini tekshiring.</p>";
}
