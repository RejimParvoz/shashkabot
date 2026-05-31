<?php
/**
 * Shashka Lite - Telegram yordamchi funksiyalari
 */

require_once __DIR__ . '/config.php';

/**
 * Telegram Bot API ga so'rov yuborish (cURL)
 */
function tg($method, $params = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        error_log('Telegram API xato: ' . $err);
        return null;
    }
    return json_decode($res, true);
}

/**
 * Mini App initData ni tekshirish (xavfsizlik).
 * To'g'ri bo'lsa user massivini qaytaradi, aks holda null.
 */
function tgValidateInitData($initData) {
    if (empty($initData)) {
        return null;
    }

    parse_str($initData, $data);
    if (!isset($data['hash'])) {
        return null;
    }

    $hash = $data['hash'];
    unset($data['hash']);

    // data_check_string
    $pairs = [];
    foreach ($data as $key => $value) {
        $pairs[] = $key . '=' . $value;
    }
    sort($pairs);
    $dataCheckString = implode("\n", $pairs);

    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $calcHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($calcHash, $hash)) {
        return null;
    }

    // auth_date eskirmaganini tekshirish (24 soat)
    if (isset($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) {
        return null;
    }

    if (isset($data['user'])) {
        return json_decode($data['user'], true);
    }
    return null;
}

/**
 * Foydalanuvchini bazaga qo'shish yoki yangilash.
 * $refCode - taklif qilgan odamning telegram_id si (ixtiyoriy).
 */
function tgUpsertUser($tgUser, $refCode = null) {
    require_once __DIR__ . '/db.php';

    $existing = dbFirst("SELECT * FROM users WHERE telegram_id = ?", [$tgUser['id']]);

    if (!$existing) {
        // Referralni tekshirish (o'zini taklif qila olmaydi)
        $referrerTgId = null;
        if ($refCode !== null && is_numeric($refCode) && (int)$refCode !== (int)$tgUser['id']) {
            $ref = dbFirst("SELECT telegram_id FROM users WHERE telegram_id = ?", [(int)$refCode]);
            if ($ref) { $referrerTgId = (int)$refCode; }
        }

        dbInsert(
            "INSERT INTO users (telegram_id, username, first_name, photo_url, rating, referred_by, coins, last_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $tgUser['id'],
                isset($tgUser['username']) ? $tgUser['username'] : null,
                isset($tgUser['first_name']) ? $tgUser['first_name'] : '',
                isset($tgUser['photo_url']) ? $tgUser['photo_url'] : null,
                START_RATING,
                $referrerTgId,
                $referrerTgId ? 100 : 0,   // yangi foydalanuvchiga xush kelibsiz bonusi
            ]
        );

        // Taklif qilgan odamga mukofot
        if ($referrerTgId) {
            dbExec(
                "UPDATE users SET coins = coins + 200, referral_count = referral_count + 1 WHERE telegram_id = ?",
                [$referrerTgId]
            );
        }
    } else {
        dbExec(
            "UPDATE users SET username = ?, first_name = ?, last_active = NOW() WHERE telegram_id = ?",
            [
                isset($tgUser['username']) ? $tgUser['username'] : null,
                isset($tgUser['first_name']) ? $tgUser['first_name'] : '',
                $tgUser['id'],
            ]
        );
    }

    return dbFirst("SELECT * FROM users WHERE telegram_id = ?", [$tgUser['id']]);
}


/**
 * Telegram Stars uchun invoice havolasi yaratish.
 * Mini App buni tg.openInvoice(link) bilan ochadi.
 * $payload - successful_payment da qaytadi (masalan "dia_<tgid>_<amount>").
 */
function tgCreateInvoiceLink($title, $description, $payload, $starsAmount) {
    $res = tg('createInvoiceLink', [
        'title' => $title,
        'description' => $description,
        'payload' => $payload,
        'currency' => 'XTR',                       // Telegram Stars
        'prices' => json_encode([['label' => $title, 'amount' => (int)$starsAmount]]),
    ]);
    if ($res && !empty($res['ok'])) {
        return $res['result'];   // invoice link (string)
    }
    return null;
}
