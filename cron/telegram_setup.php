<?php
/**
 * Shashka Game - Telegram Webhook Setup Helper (CLI)
 *
 * Foydalanish (Usage):
 *   php cron/telegram_setup.php set      # webhook o'rnatish
 *   php cron/telegram_setup.php info     # webhook holatini ko'rish
 *   php cron/telegram_setup.php delete   # webhook o'chirish
 *
 * Webhook URL .env dagi TELEGRAM_WEBHOOK_URL dan olinadi:
 *   https://topkons.uz/shashka/api/telegram/webhook
 */

if (php_sapi_name() !== 'cli') {
    die("Bu skript faqat buyruq qatoridan ishga tushiriladi\n");
}

require_once __DIR__ . '/../core/App.php';

$app = App::getInstance();
$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$webhookUrl = $_ENV['TELEGRAM_WEBHOOK_URL'] ?? 'https://topkons.uz/shashka/api/telegram/webhook';
$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

if (empty($token) || $token === 'your_bot_token_here') {
    die("XATO: .env faylida TELEGRAM_BOT_TOKEN ni to'ldiring!\n");
}

$command = $argv[1] ?? 'info';

function tgApi($token, $method, $params = []) {
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    return json_decode($res, true);
}

switch ($command) {
    case 'set':
        echo "Webhook o'rnatilmoqda: {$webhookUrl}\n";
        $params = [
            'url' => $webhookUrl,
            'max_connections' => 100,
            'allowed_updates' => json_encode([
                'message', 'callback_query', 'pre_checkout_query'
            ]),
            'drop_pending_updates' => 'true',
        ];
        if (!empty($secret)) {
            $params['secret_token'] = $secret;
            echo "Secret token ishlatilmoqda.\n";
        }
        $result = tgApi($token, 'setWebhook', $params);
        if (!empty($result['ok'])) {
            echo "✅ Muvaffaqiyatli! " . ($result['description'] ?? '') . "\n";
        } else {
            echo "❌ Xato: " . ($result['description'] ?? $result['error'] ?? 'noma\'lum') . "\n";
        }
        break;

    case 'delete':
        echo "Webhook o'chirilmoqda...\n";
        $result = tgApi($token, 'deleteWebhook', ['drop_pending_updates' => 'true']);
        echo (!empty($result['ok']) ? "✅ O'chirildi\n" : "❌ Xato\n");
        break;

    case 'info':
    default:
        echo "Webhook holati:\n";
        $result = tgApi($token, 'getWebhookInfo');
        if (!empty($result['ok'])) {
            $info = $result['result'];
            echo "  URL: " . ($info['url'] ?: '(o\'rnatilmagan)') . "\n";
            echo "  Kutilayotgan yangilanishlar: " . ($info['pending_update_count'] ?? 0) . "\n";
            if (!empty($info['last_error_message'])) {
                echo "  Oxirgi xato: " . $info['last_error_message'] . "\n";
                echo "  Xato vaqti: " . date('Y-m-d H:i:s', $info['last_error_date'] ?? 0) . "\n";
            }
        } else {
            echo "  ❌ Ma'lumot olib bo'lmadi: " . ($result['description'] ?? $result['error'] ?? '') . "\n";
        }
        echo "\nWebhook o'rnatish uchun: php cron/telegram_setup.php set\n";
        break;
}

exit(0);
