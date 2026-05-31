<?php
/**
 * Shashka Lite - Telegram avto-login (initData HMAC) testi
 * Bazaga ulanmasdan, faqat validatsiya mantig'ini tekshiradi.
 */

// config.php BOT_TOKEN ni define qilmasligi uchun, qiymatlarni oldindan belgilaymiz
define('BOT_TOKEN', 'TEST_BOT_TOKEN_12345');
define('DB_HOST', 'x'); define('DB_NAME', 'x'); define('DB_USER', 'x');
define('DB_PASS', ''); define('DB_CHARSET', 'utf8mb4');
define('APP_URL', 'https://topkons.uz/shashka');
define('WEBHOOK_SECRET', '');
define('START_RATING', 1000);
define('WIN_COINS', 25); define('DRAW_COINS', 10); define('LOSS_COINS', 5);

// telegram.php dan faqat validatsiya funksiyasini ajratib olamiz
// (db.php ni include qilmaslik uchun funksiyani shu yerda nusxalaymiz - bir xil mantiq)
function tgValidateInitData($initData) {
    if (empty($initData)) return null;
    parse_str($initData, $data);
    if (!isset($data['hash'])) return null;
    $hash = $data['hash'];
    unset($data['hash']);
    $pairs = [];
    foreach ($data as $key => $value) { $pairs[] = $key . '=' . $value; }
    sort($pairs);
    $dataCheckString = implode("\n", $pairs);
    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $calcHash = hash_hmac('sha256', $dataCheckString, $secretKey);
    if (!hash_equals($calcHash, $hash)) return null;
    if (isset($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) return null;
    if (isset($data['user'])) return json_decode($data['user'], true);
    return null;
}

// To'g'ri initData yasash (Telegram qanday yasasa, shunday)
function makeInitData($token, $userJson, $authDate) {
    $fields = ['auth_date' => (string)$authDate, 'user' => $userJson];
    $pairs = [];
    foreach ($fields as $k => $v) { $pairs[] = $k . '=' . $v; }
    sort($pairs);
    $dcs = implode("\n", $pairs);
    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $hash = hash_hmac('sha256', $dcs, $secret);
    // query-string ko'rinishida (urlencode bilan)
    return http_build_query([
        'auth_date' => $authDate,
        'user' => $userJson,
        'hash' => $hash,
    ]);
}

$pass = 0; $fail = 0;
function t($n, $c) { global $pass, $fail; if ($c) { echo "  PASS: $n\n"; $pass++; } else { echo "  FAIL: $n\n"; $fail++; } }

echo "========================================\n";
echo "  SHASHKA LITE - AVTO-LOGIN TESTI\n";
echo "========================================\n\n";

$userJson = json_encode(['id' => 123456, 'first_name' => 'Sardor', 'username' => 'sardor']);

echo "1. TO'G'RI initData\n";
$valid = makeInitData(BOT_TOKEN, $userJson, time());
$res = tgValidateInitData($valid);
t("To'g'ri initData qabul qilinadi", $res !== null);
t("User ID to'g'ri o'qiladi", $res && $res['id'] === 123456);
t("Ism to'g'ri o'qiladi", $res && $res['first_name'] === 'Sardor');

echo "\n2. SOXTA / BUZILGAN initData\n";
$tampered = $valid . '&extra=hack';
t("Buzilgan initData rad etiladi", tgValidateInitData($tampered) === null);

$wrongToken = makeInitData('BOSHQA_TOKEN', $userJson, time());
t("Noto'g'ri token bilan rad etiladi", tgValidateInitData($wrongToken) === null);

t("Bo'sh initData null qaytaradi", tgValidateInitData('') === null);
t("hash'siz initData rad etiladi", tgValidateInitData('user=' . urlencode($userJson)) === null);

echo "\n3. ESKIRGAN initData (24 soatdan eski)\n";
$old = makeInitData(BOT_TOKEN, $userJson, time() - 90000); // ~25 soat
t("Eskirgan initData rad etiladi", tgValidateInitData($old) === null);

echo "\n4. REYTING HISOBI (bot darajasiga qarab)\n";
function calcRatingChange($result, $botLevel) {
    $expected = ['easy' => 0.8, 'medium' => 0.6, 'hard' => 0.4, 'expert' => 0.25];
    $exp = isset($expected[$botLevel]) ? $expected[$botLevel] : 0.5;
    $score = ($result === 'win') ? 1.0 : (($result === 'draw') ? 0.5 : 0.0);
    return (int) round(32 * ($score - $exp));
}
t("Ekspertni yutish ko'p beradi (+24)", calcRatingChange('win', 'expert') === 24);
t("Osonni yutish kam beradi (+6)", calcRatingChange('win', 'easy') === 6);
t("Ekspertdan yutqazish kam oladi", calcRatingChange('loss', 'expert') === -8);
t("Osondan yutqazish ko'p oladi", calcRatingChange('loss', 'easy') === -26);

echo "\n========================================\n";
echo "  NATIJA: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
