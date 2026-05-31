<?php
/**
 * Shashka Game - Auction Generation Cron Job
 * Runs every 5 minutes to finalize ended auctions and ensure availability
 *
 * Crontab (every 5 minutes): the schedule "5-slash star star star star" then the php command
 * Example: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/shashka/cron/auction_generate.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

require_once __DIR__ . '/../core/App.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Auction.php';

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Auction cron boshlandi\n";

try {
    $app = App::getInstance();
    $db = $app->db();

    // 1. Finalize ended auctions
    echo "1. Tugagan auksionlarni yakunlash...\n";
    $endedAuctions = $db->get(
        "SELECT id FROM auctions WHERE status = 'active' AND end_time <= NOW()"
    );

    $finalized = 0;
    foreach ($endedAuctions as $row) {
        $auction = Auction::find($row['id']);
        if ($auction) {
            $auction->finalize();
            $finalized++;
        }
    }
    echo "   {$finalized} ta auksion yakunlandi\n";

    // 2. Check active auction count
    echo "2. Faol auksionlar sonini tekshirish...\n";
    $activeCount = Auction::countActive();
    echo "   Faol auksionlar: {$activeCount}\n";

    // 3. Emergency generation if below threshold
    if ($activeCount < Auction::EMERGENCY_THRESHOLD) {
        echo "3. Favqulodda generatsiya (threshold: " . Auction::EMERGENCY_THRESHOLD . ")...\n";
        $created = Auction::generateAuctions();
        echo "   {$created} ta yangi auksion yaratildi\n";
    } else {
        echo "3. Generatsiya kerak emas\n";
    }

    // 4. Daily scheduled generation (at midnight only)
    $currentHour = (int) date('G');
    $currentMin = (int) date('i');
    if ($currentHour === 0 && $currentMin < 5) {
        echo "4. Kunlik auksion generatsiyasi (00:00)...\n";
        $settings = $db->first("SELECT * FROM auction_auto_settings WHERE is_active = 1 LIMIT 1");
        $lastGen = $settings ? $settings['last_generated'] : null;

        // Only generate if not already done today
        if (!$lastGen || date('Y-m-d', strtotime($lastGen)) !== date('Y-m-d')) {
            $created = Auction::generateAuctions();
            echo "   {$created} ta kunlik auksion yaratildi\n";
        } else {
            echo "   Bugun allaqachon generatsiya qilingan\n";
        }
    }

    $duration = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Auction cron yakunlandi ({$duration}s)\n";

} catch (Exception $e) {
    echo "[XATO] " . $e->getMessage() . "\n";
    error_log("Auction cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
