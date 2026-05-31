<?php
/**
 * Shashka Game - Daily Cron Job
 * Runs once per day (00:00) to handle maintenance tasks
 *
 * Crontab: 0 0 * * * /usr/bin/php /path/to/shashka/cron/daily.php
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

require_once __DIR__ . '/../core/App.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Quest.php';
require_once __DIR__ . '/../models/Arena.php';

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Daily cron boshlandi\n";

try {
    $app = App::getInstance();
    $db = $app->db();
    $cache = $app->cache();

    // 1. Expire VIP subscriptions that have ended
    echo "1. VIP obunalarni tekshirish...\n";
    $expiredVips = $db->get(
        "SELECT user_id FROM vip_subscriptions WHERE is_active = 1 AND end_date <= NOW()"
    );
    $db->update(
        "UPDATE vip_subscriptions SET is_active = 0 WHERE is_active = 1 AND end_date <= NOW()"
    );
    foreach ($expiredVips as $vip) {
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, created_at)
             VALUES (?, 'vip_expired', 'VIP tugadi', 'Sizning VIP obunangiz tugadi. Yangilang!', NOW())",
            [$vip['user_id']]
        );
        $cache->delete("user_vip:{$vip['user_id']}");
    }
    echo "   " . count($expiredVips) . " ta VIP obuna tugadi\n";

    // 2. Reset daily quests (delete old, users get fresh ones on next request)
    echo "2. Kunlik vazifalarni tozalash...\n";
    $deleted = $db->delete(
        "DELETE FROM user_daily_quests WHERE date < CURDATE()"
    );
    echo "   Eski vazifalar tozalandi\n";

    // 3. Archive old data (6+ months) - game_moves
    echo "3. Eski ma'lumotlarni arxivlash...\n";
    $db->delete(
        "DELETE FROM game_moves WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
    );
    echo "   Eski o'yin yurishlari arxivlandi\n";

    // 4. Check arena season end
    echo "4. Arena mavsumini tekshirish...\n";
    $endedArena = $db->first(
        "SELECT * FROM arena_seasons WHERE is_active = 1 AND end_date <= NOW() LIMIT 1"
    );
    if ($endedArena) {
        $arena = new Arena($endedArena);
        $arena->finalizeSeason();
        echo "   Arena mavsumi yakunlandi: {$endedArena['name']}\n";
    } else {
        echo "   Faol arena mavsumi davom etmoqda\n";
    }

    // 5. Update league rankings (recalculate user leagues)
    echo "5. Liga reytinglarini yangilash...\n";
    $db->update("UPDATE users SET league = 'bronze' WHERE rating < 1000 AND league != 'bronze'");
    $db->update("UPDATE users SET league = 'silver' WHERE rating >= 1000 AND rating < 2000 AND league != 'silver'");
    $db->update("UPDATE users SET league = 'gold' WHERE rating >= 2000 AND rating < 3000 AND league != 'gold'");
    $db->update("UPDATE users SET league = 'diamond' WHERE rating >= 3000 AND rating < 4000 AND league != 'diamond'");
    $db->update("UPDATE users SET league = 'royal' WHERE rating >= 4000 AND league != 'royal'");
    echo "   Ligalar yangilandi\n";

    // 6. Clean expired notifications
    echo "6. Eski bildirishnomalarni tozalash...\n";
    $db->delete(
        "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()"
    );
    $db->delete(
        "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    echo "   Bildirishnomalar tozalandi\n";

    // 7. Reset users online status (cleanup stale sessions)
    echo "7. Online statuslarni tozalash...\n";
    $db->update(
        "UPDATE users SET is_online = 0 WHERE is_online = 1 AND last_active < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    echo "   Online statuslar tozalandi\n";

    // 8. Optimize key tables
    echo "8. Jadvallarni optimizatsiya qilish...\n";
    $tables = ['users', 'games', 'notifications', 'transactions'];
    foreach ($tables as $table) {
        try {
            $db->query("ANALYZE TABLE `$table`", [], 'write');
        } catch (Exception $e) {
            // Ignore
        }
    }
    echo "   Jadvallar optimizatsiya qilindi\n";

    $duration = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Daily cron yakunlandi ({$duration}s)\n";

} catch (Exception $e) {
    echo "[XATO] " . $e->getMessage() . "\n";
    error_log("Daily cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
