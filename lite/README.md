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

- **Telegram avto-login** — Mini App ochilganda initData HMAC tekshiruvi
  bilan avtomatik kirish (parol kerak emas).
- **Bot bilan o'ynash** — 4 daraja (Oson/O'rta/Qiyin/Ekspert), Minimax+Alpha-Beta AI.
- **Shashka qoidalari** — majburiy yutish, ko'p sakrash (zanjir), damaga aylanish.
- **Elo reyting** — bot darajasiga qarab o'zgaradi.
- **Tangalar** — g'alaba +25, durang +10, mag'lubiyat +5.
- **Reyting jadvali** (top 100) va **Profil** (statistika).
- **Telegram bot** — `/start`, `/help`, o'yin tugmasi.
- **Dark/Light tema**.

## ⚠️ Bu LITE versiya (cheklovlar)

To'liq versiyadagi quyidagilar bu yengil versiyada YO'Q (chunki ular murakkab
routing/WebSocket talab qiladi):
- Online multiplayer (faqat bot bilan)
- Do'kon, Auksion, VIP, Battle Pass, Turnirlar, Arena, Klan
- Real vaqt chat

> To'liq versiya repozitoriyning asosiy papkasida (`/`), lekin u mod_rewrite
> talab qiladi. Agar keyinchalik server'da mod_rewrite yoqsangiz, to'liq
> versiyaga o'tish mumkin.

## 🧪 Testlar

```bash
php lite/test_auth.php      # avto-login (12 test)
node lite/test_engine.js    # o'yin dvigateli (14 test)
```
