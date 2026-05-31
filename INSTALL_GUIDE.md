# 🎮 SHASHKA GAME - O'RNATISH YO'RIQNOMASI

## 📋 Talab qilinganlar

- **PHP 7.4+** (OPcache yoqilgan)
- **MySQL 5.7+**
- **Redis** (ixtiyoriy, lekin tavsiya etiladi)
- **Nginx** yoki **Apache**
- **SSL sertifikat** (HTTPS uchun)
- **4GB+ RAM** (production uchun 16GB+)

## 🚀 O'rnatish bosqichlari

### 1. Loyihani yuklab oling
```bash
# Barcha fayllarni o'z serveringizga ko'chiring
# Folder tuzilishi:
shashka/
├── public/index.php
├── .env
├── .htaccess
├── nginx.conf
├── config/ (5 ta fayl)
├── database/ (6 ta fayl)
├── core/ (8 ta fayl + cache/ papka)
├── models/ (3 ta fayl)
├── controllers/Api/ (1 ta fayl)
└── storage/ (4 ta papka)
```

### 2. Ma'lumotlar bazasini o'rnating
```bash
# MySQL ga kiring
mysql -u root -p

# Ma'lumotlar bazasini yarating
CREATE DATABASE shashka_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Jadvallarni import qiling
mysql -u root -p shashka_game < database/complete_schema.sql
mysql -u root -p shashka_game < database/indexes.sql
```

### 3. Konfiguratsiyani sozlang
```bash
# .env faylini tahrirlang
DB_HOST=localhost
DB_NAME=shashka_game
DB_USER=your_username
DB_PASS=your_password

# Telegram bot tokenini qo'shing
TELEGRAM_BOT_TOKEN=your_bot_token_here

# Redis sozlamalarini kiriting (agar mavjud bo'lsa)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. Ruxsatlarni o'rnating
```bash
chmod -R 755 public/
chmod -R 777 storage/
chown -R www-data:www-data shashka/
```

### 5. Nginx konfiguratsiyasi
```bash
# nginx.conf faylidan nusxa oling va Nginx-ga qo'ying
sudo cp nginx.conf /etc/nginx/sites-available/shashka.uz
sudo ln -s /etc/nginx/sites-available/shashka.uz /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. PHP-FPM sozlamalari
```bash
# /etc/php/7.4/fpm/pool.d/shashka.conf yarating
[shashka]
user = www-data
group = www-data
listen = /run/php/php7.4-fpm.sock
pm = dynamic
pm.max_children = 200
pm.start_servers = 50
pm.min_spare_servers = 25
pm.max_spare_servers = 100
php_admin_value[memory_limit] = 256M
php_admin_value[opcache.enable] = 1
```

### 7. Cron joblarni o'rnating
```bash
# Root sifatida crontab -e
0 0 * * * /usr/bin/php /path/to/shashka/cron/daily.php
*/5 * * * * /usr/bin/php /path/to/shashka/cron/auction_generate.php
0 19 * * * /usr/bin/php /path/to/shashka/cron/daily_tournament.php
```

### 8. Telegram Bot sozlamalari
```bash
# Bot yarating @BotFather orqali
# Webhook o'rnating
curl -X POST "https://api.telegram.org/bot{YOUR_BOT_TOKEN}/setWebhook" \
  -d "url=https://shashka.uz/api/telegram/webhook"

# Mini App o'rnating
# @BotFather -> /newapp -> shashka.uz
```

### 9. SSL sertifikatini o'rnating
```bash
# Let's Encrypt bilan
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d shashka.uz
```

### 10. Test qiling
```bash
# Saytni oching: https://shashka.uz
# API testini o'tkazing: https://shashka.uz/api/auth/status
# Telegram bot orqali /start bosing
```

## 🔧 Qo'shimcha sozlamalar

### Redis o'rnatish (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### MySQL optimizatsiya
```bash
# /etc/mysql/mysql.conf.d/mysqld.cnf ga qo'shing:
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
query_cache_type = 1
query_cache_size = 256M
max_connections = 1000
```

### Monitoring o'rnatish
```bash
# Grafana + Prometheus
sudo apt-get install -y adduser libfontconfig1
wget https://dl.grafana.com/oss/release/grafana_8.5.2_amd64.deb
sudo dpkg -i grafana_8.5.2_amd64.deb
```

## ⚠️ Production uchun muhim

1. **SSL majburiy** - HTTP yo'q
2. **Firewall** - faqat 80, 443, 22 portlar
3. **Regular backup** - ma'lumotlar bazasi va fayllar
4. **Monitoring** - server va ilova performance
5. **Log rotation** - disk to'lib ketmasligi uchun
6. **Rate limiting** - DDoS hujumlardan himoya

## 🆘 Muammolar hal qilish

### PHP xatolari
```bash
# Error loglarni tekshiring
tail -f storage/logs/error.log
tail -f /var/log/nginx/error.log
```

### Ma'lumotlar bazasi muammolari
```bash
# MySQL loglarni tekshiring  
tail -f /var/log/mysql/error.log

# Sekin so'rovlarni tekshiring
tail -f /var/log/mysql/slow.log
```

### Nginx muammolari
```bash
# Konfiguratsiyani tekshiring
sudo nginx -t

# Loglarni ko'ring
tail -f /var/log/nginx/shashka_access.log
```

## 📞 Yordam

Agar muammolar bo'lsa:
1. Loglarni tekshiring
2. PHP va Nginx konfiguratsiyasini qayta ko'ring  
3. Ma'lumotlar bazasi ulanishini tekshiring
4. Redis ishlayotganini tekshiring

**Omad tilaymiz! 🎮**