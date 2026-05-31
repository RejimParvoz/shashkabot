# 🎯 Shashka Game Platform

1 million foydalanuvchiga mo'ljallangan yuqori unumdorlikdagi Shashka (Checkers) o'yini platformasi.

## 🚀 Texnologiyalar

- **Backend**: PHP 7.4 (sof PHP, hech qanday framework yo'q)
- **Database**: MySQL 5.7
- **Cache**: Redis / File cache
- **WebSocket**: Ratchet (real-time o'yin)
- **Frontend**: Vanilla JavaScript, Canvas API
- **UI**: iOS style dizayn
- **Web Server**: Nginx + PHP-FPM

## 📋 Talab qilinganlar

- PHP 7.4+
- MySQL 5.7+
- Redis (ixtiyoriy)
- Nginx yoki Apache
- SSL sertifikat (HTTPS)
- 4GB+ RAM (production uchun 16GB+)

## ⚙️ O'rnatish

1. **Loyihani klonlash:**
```bash
git clone https://github.com/username/shashka-game.git
cd shashka-game
```

2. **Konfiguratsiya:**
```bash
cp .env.example .env
# .env faylini tahrirlang
```

3. **Ma'lumotlar bazasini yaratish:**
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/indexes.sql
```

4. **Ruxsatlarni o'rnatish:**
```bash
chmod -R 755 public/
chmod -R 777 storage/
```

5. **Web serverni sozlash:**
- Nginx uchun: `nginx.conf` faylini nusxalang
- Apache uchun: `.htaccess` fayl allaqachon mavjud

6. **Cron joblarni o'rnatish:**
```bash
# Root foydalanuvchi sifatida
crontab -e
# Quyidagi qatorlarni qo'shing:
0 0 * * * /usr/bin/php /path/to/shashka/cron/daily.php
*/5 * * * * /usr/bin/php /path/to/shashka/cron/auction_generate.php
0 19 * * * /usr/bin/php /path/to/shashka/cron/daily_tournament.php
0 20 * * 5 /usr/bin/php /path/to/shashka/cron/weekly_tournament.php
0 21 1 * * /usr/bin/php /path/to/shashka/cron/monthly_tournament.php
```

7. **WebSocket serverni ishga tushirish:**
```bash
# Screen yoki systemd orqali
php websocket/server.php
```

## 🎮 Xususiyatlar

### O'yin tizimi
- ✅ 8x8 shashka taxtasi (Canvas)
- ✅ 5 ta o'yin rejimi (Klassik, Blitz, Bullet, Rapid, Marafon)
- ✅ AI bot (4 qiyinchilik darajasi)
- ✅ Online multiplayer (WebSocket)
- ✅ O'yin repleyi va statistika

### Foydalanuvchi tizimi
- ✅ Telegram orqali avtorizatsiya
- ✅ Elo reyting tizimi
- ✅ 5 liga (Bronza → Shohona)
- ✅ Profil va statistika

### Iqtisodiyot
- ✅ Ikki valyuta (💎 Olmos, 🪙 Tanga)
- ✅ VIP tizim (4 daraja)
- ✅ Do'kon va inventar
- ✅ Avtomatik auksion
- ✅ Battle Pass (oylik)

### Turnirlar
- ✅ Avtomatik turnirlar (kunlik, haftalik, oylik)
- ✅ Arena tizimi
- ✅ Klan tizimi
- ✅ Yutuqlar

### Admin panel
- ✅ Real-time dashboard
- ✅ Foydalanuvchilar boshqaruvi
- ✅ Statistika va hisobotlar

## 🔧 Performans

### 1M+ foydalanuvchi uchun optimizatsiyalar:
- ✅ OPcache yoqilgan
- ✅ Prepared statements
- ✅ Database indexlar
- ✅ Partitioning katta jadvallar uchun
- ✅ 2-level caching (Redis + File)
- ✅ CDN integratsiyasi
- ✅ Rate limiting
- ✅ Connection pooling
- ✅ Cursor-based pagination

## 🛡️ Xavfsizlik

- ✅ CSRF himoyasi
- ✅ XSS himoyasi
- ✅ SQL injection himoyasi
- ✅ Rate limiting
- ✅ Security headers
- ✅ Input validation
- ✅ Error logging

## 📊 Monitoring

- Log fayllar: `storage/logs/`
- Error tracking
- Performance monitoring
- Database slow query log

## 🚀 Production Deploy

1. **Server talablari:**
   - CPU: 8+ core
   - RAM: 16GB+
   - SSD: 500GB+
   - Bandwidth: 100Mbps+

2. **Load Balancer:**
   - Nginx yoki HAProxy
   - SSL termination
   - Health checks

3. **Database:**
   - Master-Slave replication
   - Regular backups
   - Query optimization

4. **Monitoring:**
   - Grafana + Prometheus
   - New Relic yoki DataDog
   - Uptime monitoring

## 📝 API Dokumentatsiya

### Autentifikatsiya
```php
POST /api/auth/telegram
{
    "init_data": "telegram_init_data_here"
}
```

### O'yin yaratish
```php
POST /api/game/create
{
    "mode": "classic",
    "opponent": "bot" // yoki user_id
}
```

### Yurish
```php
POST /api/game/move
{
    "game_id": 123,
    "from": "a3",
    "to": "b4"
}
```

## 🤝 Hissa qo'shish

1. Fork qiling
2. Feature branch yarating
3. Commit qiling
4. Push qiling
5. Pull Request yarating

## 📄 Litsenziya

MIT License - batafsil ma'lumot uchun `LICENSE` faylini ko'ring.

## 📞 Aloqa

- Telegram: [@shashka_support](https://t.me/shashka_support)
- Email: support@shashka.uz
- Website: https://shashka.uz

---

💎 **Shashka Game** - O'zbekiston #1 Shashka platformasi!