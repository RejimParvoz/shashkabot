<?php
/**
 * Shashka Lite - Konfiguratsiya
 * mod_rewrite KERAK EMAS. Har bir fayl to'g'ridan-to'g'ri ochiladi.
 *
 * Bu fayllarni serverga yuklang: topkons.uz/shashka/
 * Keyin install.php ni bir marta oching, so'ng webhook'ni o'rnating.
 */

// ---- Ma'lumotlar bazasi (MySQL) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'shashka_db');        // bazangiz nomi
define('DB_USER', 'root');              // foydalanuvchi
define('DB_PASS', '');                  // parol
define('DB_CHARSET', 'utf8mb4');

// ---- Telegram bot ----
define('BOT_TOKEN', 'BOT_TOKENNI_SHU_YERGA');     // @BotFather dan
define('BOT_USERNAME', 'ShashkaBot');              // @siz (faqat nom, @ siz)
define('APP_URL', 'https://topkons.uz/shashka');  // loyiha manzili
define('WEBHOOK_SECRET', '');                      // ixtiyoriy maxfiy token

// ---- O'yin sozlamalari ----
define('START_RATING', 1000);
define('WIN_COINS', 25);
define('DRAW_COINS', 10);
define('LOSS_COINS', 5);

// Xatolarni logga yozish (ekranda ko'rsatmaslik)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

date_default_timezone_set('Asia/Tashkent');
