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

- **Animatsiyali kirish logotipi** (SVG) va yuklash ekrani.
- **Telegram avto-login** — initData HMAC tekshiruvi.
- **To'g'ri o'zbek/rus shashka qoidalari** — orqaga olish + uchuvchi dama, majburiy yutish, zanjir.
- **Bot bilan o'ynash** — 4 daraja, Minimax+Alpha-Beta AI. **Alohida bot reytingi.**
- **🌐 Online o'ynash** — tasodifiy raqib. **Alohida online reyting.**
- **👥 Do'st bilan 1v1** — havola/kod (bot orqali), do'st kirsa o'yin boshlanadi.
- **💬 O'yin chati** — online o'yinda yozishish, tezkor emojilar.
- **🤝 Durang so'rovi** — online o'yinda taklif qilish/qabul qilish.
- **🔊 Ovoz effektlari** — yurish, yutish, g'alaba, chat (Web Audio API, o'chirish mumkin).
- **🏆 Turnirlar** — kunlik/haftalik/oylik, ochko (g'alaba +3), top 3 olmos yutadi, jonli sanoq.
- **🏪 Do'kon** — tanga terilari + **💎 premium skinlar** (maxsus SVG grafika, canvas'da porlash).
- **💎 Olmos sotib olish** + **🎟 Battle Pass** + **👑 VIP** (3 daraja, kunlik olmos bonusi) — Telegram Stars.
- **🏆 Reyting + podium** — 1 markazda, 2 chapda, 3 o'ngda; o'yinchi ustiga bossangiz to'liq statistika.
- **🎁 Referral** — botga havola; +200🪙 / +100🪙.
- **To'liq iOS style** — glassmorphism, segment, blur, dark/light.

## 🧪 Testlar (47)

```bash
php lite/test_auth.php          # 12
php lite/test_engine_php.php    # 15
node lite/test_engine.js        # 20
```
