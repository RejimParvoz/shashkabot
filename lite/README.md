# 🎯 Shashka Lite — mod_rewrite KERAK EMAS versiya

Bu sodda versiya **`.htaccess` / mod_rewrite'siz** ishlaydi. Har bir fayl
to'g'ridan-to'g'ri URL orqali ochiladi — shuning uchun topkons.uz kabi
oddiy hostingda muammosiz ishlaydi.

## 📂 Fayllar

| Fayl | Vazifa | URL |
|------|--------|-----|
| `index.php` | O'yin (Mini App) | `topkons.uz/shashka/index.php` |
| `game.js` | O'yin mantig'i + bot AI | — |
| `api.php` | API (query orqali) | `topkons.uz/shashka/api.php?action=...` |
| `webhook.php` | Telegram webhook | `topkons.uz/shashka/webhook.php` |
| `set_webhook.php` | Webhook o'rnatish | `topkons.uz/shashka/set_webhook.php?do=set` |
| `install.php` | Jadvallar yaratish | `topkons.uz/shashka/install.php` |
| `config.php` | Sozlamalar | — |
| `db.php`, `telegram.php` | Yordamchilar | — |

## 🚀 O'rnatish (5 qadam)

1. **Fayllarni yuklang** — `lite/` ichidagi hamma faylni serverdagi
   `shashka/` papkaga yuklang (FTP/cPanel File Manager).

2. **`config.php` ni to'ldiring:**
   ```php
   define('DB_NAME', 'baza_nomi');
   define('DB_USER', 'foydalanuvchi');
   define('DB_PASS', 'parol');
   define('BOT_TOKEN', '@BotFather dan olingan token');
   define('APP_URL', 'https://topkons.uz/shashka');
   ```

3. **Jadvallarni yarating** — brauzerda oching:
   `https://topkons.uz/shashka/install.php`
   (tugagach bu faylni o'chiring).

4. **Webhook o'rnating** — brauzerda oching:
   `https://topkons.uz/shashka/set_webhook.php?do=set`

5. **@BotFather** da Mini App tugmasini `https://topkons.uz/shashka/index.php`
   ga ulang (yoki bot `/start` bosilganda chiqadigan tugma orqali ochiladi).

## ✅ Nima ishlaydi

- **Telegram avto-login** — initData HMAC tekshiruvi bilan avtomatik kirish.
- **To'g'ri o'zbek/rus shashka qoidalari** — orqaga olish + uchuvchi dama, majburiy yutish, zanjir.
- **Bot bilan o'ynash** — 4 daraja, Minimax+Alpha-Beta AI.
- **🌐 Online o'ynash** — tasodifiy raqib (server tomon tekshiruvli).
- **👥 Do'st bilan 1v1** — havola/kod yarating, do'stga yuboring; u kirsa o'yin boshlanadi.
- **🏪 Do'kon** — tanga terilari + **💎 premium (olmos) skinlar** (marmar, galaktika, olmos, yoqut, shohona...).
- **💎 Olmos sotib olish** — Telegram Stars orqali (5 paket).
- **🎟 Battle Pass** — 10 daraja, free + premium track, XP (o'yin +10, g'alaba +25), mukofotlar.
- **🏆 Reyting + podium** — 1-o'rin markazda, 2-chapda, 3-o'ngda; o'yinchi ustiga bossangiz to'liq statistika.
- **🎁 Do'st taklif (referral)** — botga havola; taklif qilgan +200🪙, yangi +100🪙.
- **To'liq iOS style** — glassmorphism, segment, blur, dark/light.

## 🧪 Testlar (47)

```bash
php lite/test_auth.php          # 12
php lite/test_engine_php.php    # 15
node lite/test_engine.js        # 20
```
