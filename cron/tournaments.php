<?php
/**
 * Shashka Game - Tournament Scheduler Cron Job
 * Creates scheduled tournaments (daily/weekly/monthly) and starts ready ones
 *
 * Crontab: 0 * * * * /usr/bin/php /path/to/shashka/cron/tournaments.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

require_once __DIR__ . '/../core/App.php';
require_once __DIR__ . '/../models/Tournament.php';

echo "[" . date('Y-m-d H:i:s') . "] Tournament cron boshlandi\n";

try {
    $app = App::getInstance();
    $db = $app->db();

    $now = new DateTime();
    $currentTime = $now->format('H:i:00');
    $dayOfWeek = (int) $now->format('N'); // 1=Mon, 7=Sun
    $dayOfMonth = (int) $now->format('j');

    // 1. Check schedules and create tournaments
    echo "1. Jadvallarni tekshirish...\n";
    $schedules = $db->get("SELECT * FROM tournament_schedule WHERE is_active = 1");

    $createdCount = 0;
    foreach ($schedules as $schedule) {
        $shouldCreate = false;
        $scheduleTime = substr($schedule['time'], 0, 5);
        $nowTime = $now->format('H:i');

        // Match within the current hour window
        if ($scheduleTime !== $nowTime && abs(strtotime($scheduleTime) - strtotime($nowTime)) > 3600) {
            continue;
        }

        if ($schedule['type'] === 'daily') {
            $shouldCreate = true;
        } elseif ($schedule['type'] === 'weekly' && (int) $schedule['day_of_week'] === $dayOfWeek) {
            $shouldCreate = true;
        } elseif ($schedule['type'] === 'monthly' && (int) $schedule['day_of_month'] === $dayOfMonth) {
            $shouldCreate = true;
        }

        // Avoid duplicate creation
        if ($shouldCreate && $schedule['last_created']) {
            if (date('Y-m-d', strtotime($schedule['last_created'])) === $now->format('Y-m-d')) {
                $shouldCreate = false;
            }
        }

        if ($shouldCreate) {
            Tournament::createFromSchedule($schedule);
            $db->update(
                "UPDATE tournament_schedule SET last_created = NOW() WHERE id = ?",
                [$schedule['id']]
            );
            $createdCount++;
            echo "   Yaratildi: {$schedule['name_template']}\n";
        }
    }
    echo "   {$createdCount} ta turnir yaratildi\n";

    // 2. Start tournaments whose registration ended
    echo "2. Tayyor turnirlarni boshlash...\n";
    $readyTournaments = $db->get(
        "SELECT id FROM tournaments WHERE status = 'registration' AND registration_end <= NOW()"
    );

    $startedCount = 0;
    foreach ($readyTournaments as $row) {
        $tournament = Tournament::find($row['id']);
        if (!$tournament) continue;

        if ($tournament->current_players >= 2) {
            try {
                $tournament->generateBracket();
                $startedCount++;
                echo "   Boshlandi: turnir #{$row['id']}\n";
            } catch (Exception $e) {
                echo "   Xato (#{$row['id']}): " . $e->getMessage() . "\n";
            }
        } else {
            // Cancel and refund if not enough players
            $tournament->status = 'cancelled';
            $tournament->save();
            echo "   Bekor qilindi (kam o'yinchi): #{$row['id']}\n";
        }
    }
    echo "   {$startedCount} ta turnir boshlandi\n";

    echo "[" . date('Y-m-d H:i:s') . "] Tournament cron yakunlandi\n";

} catch (Exception $e) {
    echo "[XATO] " . $e->getMessage() . "\n";
    error_log("Tournament cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
