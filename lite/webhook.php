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
        // Muvaffaqiyatli to'lov (Telegram Stars)
        if (isset($update['message']['successful_payment'])) {
            handlePayment($update['message']);
            return;
        }
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        tg('answerCallbackQuery', ['callback_query_id' => $cq['id']]);
        if (isset($cq['message']['chat']['id'])) {
            sendPlayButton($cq['message']['chat']['id']);
        }
    } elseif (isset($update['pre_checkout_query'])) {
        // To'lovni tasdiqlash (10 soniya ichida javob berish shart)
        tg('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => 'true',
        ]);
    }
}

/**
 * Telegram Stars to'lovi muvaffaqiyatli tugagach.
 * payload: "dia_<tgid>_<diamonds>" yoki "bp_<tgid>"
 */
function handlePayment($message) {
    $sp = $message['successful_payment'];
    $payload = isset($sp['invoice_payload']) ? $sp['invoice_payload'] : '';
    $chargeId = isset($sp['telegram_payment_charge_id']) ? $sp['telegram_payment_charge_id'] : '';
    $stars = isset($sp['total_amount']) ? (int)$sp['total_amount'] : 0;
    $chatId = $message['chat']['id'];
    $fromId = isset($message['from']['id']) ? (int)$message['from']['id'] : 0;

    if ($chargeId === '' || $payload === '') { return; }

    // Takror kreditni oldini olish
    $exists = dbFirst("SELECT id FROM payments WHERE charge_id = ?", [$chargeId]);
    if ($exists) { return; }

    $user = dbFirst("SELECT * FROM users WHERE telegram_id = ?", [$fromId]);
    if (!$user) { return; }

    $parts = explode('_', $payload);
    if ($parts[0] === 'dia' && isset($parts[2])) {
        $diamonds = (int)$parts[2];
        dbExec("UPDATE users SET diamonds = diamonds + ? WHERE id = ?", [$diamonds, $user['id']]);
        dbInsert("INSERT INTO payments (charge_id, user_id, kind, amount, stars, created_at) VALUES (?, ?, 'diamonds', ?, ?, NOW())",
            [$chargeId, $user['id'], $diamonds, $stars]);
        tg('sendMessage', ['chat_id' => $chatId, 'text' => "✅ {$diamonds} 💎 hisobingizga qo'shildi! Rahmat!"]);
    } elseif ($parts[0] === 'bp') {
        dbExec("UPDATE users SET bp_premium = 1 WHERE id = ?", [$user['id']]);
        dbInsert("INSERT INTO payments (charge_id, user_id, kind, amount, stars, created_at) VALUES (?, ?, 'bp_premium', 1, ?, NOW())",
            [$chargeId, $user['id'], $stars]);
        tg('sendMessage', ['chat_id' => $chatId, 'text' => "✅ Battle Pass Premium ochildi! 🎟"]);
    } elseif ($parts[0] === 'vip' && isset($parts[2])) {
        $level = $parts[2];
        // mavjud VIP ustiga 30 kun qo'shamiz
        $base = (!empty($user['vip_until']) && strtotime($user['vip_until']) > time()) ? strtotime($user['vip_until']) : time();
        $until = date('Y-m-d H:i:s', $base + 30 * 86400);
        dbExec("UPDATE users SET vip_until = ?, vip_level = ? WHERE id = ?", [$until, $level, $user['id']]);
        dbInsert("INSERT INTO payments (charge_id, user_id, kind, amount, stars, created_at) VALUES (?, ?, 'vip', 1, ?, NOW())",
            [$chargeId, $user['id'], $stars]);
        tg('sendMessage', ['chat_id' => $chatId, 'text' => "👑 VIP " . ucfirst($level) . " faollashtirildi! (30 kun)"]);
    }
}

function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    $from = isset($message['from']) ? $message['from'] : [];

    // /start dan parametr ajratish: "/start ref_123" yoki "/start match_ABC123"
    $refCode = null;
    $joinCode = null;
    if (strpos($text, '/start') === 0) {
        $parts = explode(' ', $text, 2);
        if (isset($parts[1])) {
            $param = trim($parts[1]);
            if (strpos($param, 'match_') === 0) {
                $joinCode = substr($param, 6);
            } else {
                $p = str_replace('ref_', '', $param);
                if (is_numeric($p)) { $refCode = $p; }
            }
        }
    }

    // Foydalanuvchini ro'yxatga olish (referral bilan)
    if (!empty($from['id'])) {
        tgUpsertUser($from, $refCode);
    }

    if (strpos($text, '/start') === 0) {
        $name = isset($from['first_name']) ? $from['first_name'] : 'do\'st';
        if ($joinCode) {
            // Do'st o'yiniga taklif havolasi orqali kirgan
            sendPlayButton(
                $chatId,
                "Do'stingiz sizni o'yinga taklif qildi! 🎯\nQabul qilish uchun tugmani bosing:",
                '/index.php?join=' . urlencode($joinCode)
            );
        } else {
            sendPlayButton(
                $chatId,
                "Salom, " . $name . "! 🎯\n\nShashka o'yiniga xush kelibsiz. O'ynashni boshlash uchun tugmani bosing:"
            );
        }
    } elseif (strpos($text, '/help') === 0) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => "Buyruqlar:\n/start - boshlash\n/play - o'ynash\n\nO'yin tugmasini bosib Mini App'da o'ynang.",
        ]);
    } else {
        sendPlayButton($chatId, "O'ynash uchun tugmani bosing 👇");
    }
}

function sendPlayButton($chatId, $text = "🎮 Shashka o'ynash:", $path = '/index.php') {
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => "🎮 O'yinni ochish", 'web_app' => ['url' => APP_URL . $path]],
        ]],
    ];
    tg('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode($keyboard),
    ]);
}
