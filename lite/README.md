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

- **Telegram avto-login** — Mini App ochilganda initData HMAC tekshiruvi bilan
  avtomatik kirish (parol kerak emas).
- **To'g'ri o'zbek/rus shashka qoidalari:**
  - Oddiy tosh **oldinga VA orqaga** yutadi (orqaga olish) ✅
  - **Dama uchuvchi** — diagonal bo'ylab istalgan masofaga yuradi va yutadi ✅
  - Majburiy yutish, ko'p sakrash (zanjir), damaga aylanish
- **Bot bilan o'ynash** — 4 daraja (Oson/O'rta/Qiyin/Ekspert), Minimax+Alpha-Beta AI.
- **🌐 Online o'ynash** — boshqa o'yinchilar bilan (server tomon tekshiruvli, polling).
- **🏪 Do'kon (faqat tanga)** — 5 doska + 5 tosh terilari, sotib olish va tanlash.
- **🎁 Do'st taklif qilish** — Telegram share havolasi; taklif qilgan +200🪙, yangi +100🪙.
- **Elo reyting** — bot va online uchun, **Tangalar**, **Reyting jadvali**, **Profil**.
- **To'liq iOS style** — glassmorphism, segment control, blur, dark/light tema.

## 🧪 Testlar (jami 47)

```bash
php lite/test_auth.php          # avto-login (12 test)
php lite/test_engine_php.php    # server dvigatel (15 test)
node lite/test_engine.js        # klient dvigatel (20 test)
```
