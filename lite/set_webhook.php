<?php
/**
 * Shashka Lite - Webhook'ni o'rnatish/tekshirish (brauzerdan)
 *
 *   Holatni ko'rish:  https://topkons.uz/shashka/set_webhook.php
 *   O'rnatish:        https://topkons.uz/shashka/set_webhook.php?do=set
 *   O'chirish:        https://topkons.uz/shashka/set_webhook.php?do=delete
 */

require_once __DIR__ . '/telegram.php';

header('Content-Type: text/html; charset=utf-8');

if (BOT_TOKEN === 'BOT_TOKENNI_SHU_YERGA' || BOT_TOKEN === '') {
    exit("<h3 style='color:red'>config.php da BOT_TOKEN ni to'ldiring!</h3>");
}

$webhookUrl = APP_URL . '/webhook.php';
$do = isset($_GET['do']) ? $_GET['do'] : 'info';

echo "<h2>Shashka Lite - Webhook</h2>";

if ($do === 'set') {
    $params = [
        'url' => $webhookUrl,
        'allowed_updates' => json_encode(['message', 'callback_query', 'pre_checkout_query']),
        'drop_pending_updates' => 'true',
    ];
    if (WEBHOOK_SECRET !== '') {
        $params['secret_token'] = WEBHOOK_SECRET;
    }
    $res = tg('setWebhook', $params);
    echo "<p>O'rnatilmoqda: <code>" . htmlspecialchars($webhookUrl) . "</code></p>";
    echo "<pre>" . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT)) . "</pre>";
    if (!empty($res['ok'])) {
        echo "<h3 style='color:green'>✅ Webhook o'rnatildi!</h3>";
    } else {
        echo "<h3 style='color:red'>❌ Xato. Yuqoridagi javobni tekshiring.</h3>";
    }

    // Bot menyu tugmasi (doimiy "O'ynash" tugmasi Mini App ochadi)
    $menuRes = tg('setChatMenuButton', [
        'menu_button' => json_encode([
            'type' => 'web_app',
            'text' => "🎮 O'ynash",
            'web_app' => ['url' => APP_URL . '/index.php'],
        ]),
    ]);
    if (!empty($menuRes['ok'])) {
        echo "<p style='color:green'>✅ Menyu tugmasi o'rnatildi (botda 🎮 O'ynash)</p>";
    }
} elseif ($do === 'delete') {
    $res = tg('deleteWebhook', ['drop_pending_updates' => 'true']);
    echo "<pre>" . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    $res = tg('getWebhookInfo');
    echo "<p>Joriy holat:</p>";
    echo "<pre>" . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT)) . "</pre>";
    echo "<p><a href='?do=set'>➡️ Webhook'ni o'rnatish</a></p>";
}
