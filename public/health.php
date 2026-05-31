<?php
/**
 * Shashka Game - Health Check (standalone)
 *
 * To'g'ridan-to'g'ri ochiladi, hech qanday routing/DB talab qilmaydi:
 *   https://topkons.uz/shashka/public/health.php
 *
 * Agar bu sahifa JSON qaytarsa -> PHP ishlayapti va fayllar yuklangan.
 * Keyin https://topkons.uz/shashka/ ni tekshiring (routing/.htaccess uchun).
 */

header('Content-Type: application/json; charset=utf-8');

$modRewrite = 'unknown';
if (function_exists('apache_get_modules')) {
    $modRewrite = in_array('mod_rewrite', apache_get_modules()) ? 'enabled' : 'disabled';
}

echo json_encode([
    'status' => 'ok',
    'service' => 'Shashka Game',
    'php_version' => PHP_VERSION,
    'server_time' => date('c'),
    'checks' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'mbstring' => extension_loaded('mbstring'),
        'openssl' => extension_loaded('openssl'),
        'redis' => extension_loaded('redis'),
    ],
    'mod_rewrite' => $modRewrite,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
    'hint' => 'Bu fayl ochilsa PHP ishlayapti. Endi https://topkons.uz/shashka/ ni tekshiring.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
