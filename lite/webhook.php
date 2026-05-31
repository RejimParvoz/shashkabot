<?php
/**
 * Shashka Lite - Telegram Webhook
 * To'g'ridan-to'g'ri URL (mod_rewrite KERAK EMAS):
 *   https://topkons.uz/shashka/webhook.php
 *
 * Webhook'ni shu URL ga o'rnating (set_webhook.php orqali).
 */

require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/db.php';

// Maxfiy token tekshiruvi (agar o'rnatilgan bo'lsa)
if (WEBHOOK_SECRET !== '') {
    $received = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
        ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
    if (!hash_equals(WEBHOOK_SECRET, $received)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

if (!is_array($update)) {
    http_response_code(200);
    echo 'ok';
    exit;
}

try {
    handleUpdate($update);
} catch (Exception $e) {
    error_log('Webhook xato: ' . $e->getMessage());
}

http_response_code(200);
echo 'ok';
exit;

/* ---------------- handlerlar ---------------- */

function handleUpdate($update) {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        tg('answerCallbackQuery', ['callback_query_id' => $cq['id']]);
        if (isset($cq['message']['chat']['id'])) {
            sendPlayButton($cq['message']['chat']['id']);
        }
    } elseif (isset($update['pre_checkout_query'])) {
        // To'lovni tasdiqlash (Telegram Stars)
        tg('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => 'true',
        ]);
    }
}

function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    $from = isset($message['from']) ? $message['from'] : [];

    // Foydalanuvchini ro'yxatga olish
    if (!empty($from['id'])) {
        tgUpsertUser($from);
    }

    if (strpos($text, '/start') === 0) {
        $name = isset($from['first_name']) ? $from['first_name'] : 'do\'st';
        sendPlayButton(
            $chatId,
            "Salom, " . $name . "! 🎯\n\nShashka o'yiniga xush kelibsiz. O'ynashni boshlash uchun tugmani bosing:"
        );
    } elseif (strpos($text, '/help') === 0) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => "Buyruqlar:\n/start - boshlash\n/play - o'ynash\n\nO'yin tugmasini bosib Mini App'da o'ynang.",
        ]);
    } else {
        sendPlayButton($chatId, "O'ynash uchun tugmani bosing 👇");
    }
}

function sendPlayButton($chatId, $text = "🎮 Shashka o'ynash:") {
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => "🎮 O'yinni ochish", 'web_app' => ['url' => APP_URL . '/index.php']],
        ]],
    ];
    tg('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode($keyboard),
    ]);
}
