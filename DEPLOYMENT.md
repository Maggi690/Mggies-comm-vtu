# Universal VTU Pro — Production Deployment Guide

## Table of Contents
1. [Server Requirements](#server-requirements)
2. [Server Setup](#server-setup)
3. [Application Deployment](#application-deployment)
4. [Database Setup](#database-setup)
5. [Redis Setup](#redis-setup)
6. [Queue Workers](#queue-workers)
7. [Nginx Configuration](#nginx-configuration)
8. [SSL Certificate](#ssl-certificate)
9. [Environment Configuration](#environment-configuration)
10. [Post-Deployment Checklist](#post-deployment-checklist)
11. [Monitoring](#monitoring)
12. [CI/CD Pipeline](#cicd-pipeline)
13. [Scaling Guide](#scaling-guide)
14. [Backup Strategy](#backup-strategy)
15. [Troubleshooting](#troubleshooting)

---

## 1. Server Requirements

| Component     | Minimum           | Recommended           |
|--------------|-------------------|-----------------------|
| OS           | Ubuntu 22.04 LTS  | Ubuntu 24.04 LTS      |
| CPU          | 2 vCPUs           | 4–8 vCPUs             |
| RAM          | 4 GB              | 8–16 GB               |
| Storage      | 50 GB SSD         | 100–200 GB NVMe SSD   |
| PHP          | 8.4               | 8.4 (latest patch)    |
| MySQL        | 8.0               | 8.0.x                 |
| Redis        | 7.0               | 7.2                   |
| Nginx        | 1.24              | 1.25+                 |

---

## 2. Server Setup

### 2.1 System Updates
```bash
apt update && apt upgrade -y
apt install -y curl wget git unzip zip software-properties-common
```

### 2.2 Install PHP 8.4
```bash
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis \
    php8.4-mbstring php8.4-xml php8.4-zip php8.4-curl php8.4-bcmath \
    php8.4-gd php8.4-intl php8.4-opcache php8.4-tokenizer php8.4-fileinfo
```

Configure PHP-FPM (`/etc/php/8.4/fpm/php.ini`):
```ini
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

### 2.3 Install MySQL 8
```bash
apt install -y mysql-server-8.0
mysql_secure_installation

# Create database and user
mysql -u root -p <<EOF
CREATE DATABASE universal_vtu_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vtupro'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON universal_vtu_pro.* TO 'vtupro'@'localhost';
FLUSH PRIVILEGES;
EOF
```

MySQL tuning (`/etc/mysql/mysql.conf.d/mysqld.cnf`):
```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 200
query_cache_type = 0
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

### 2.4 Install Redis 7
```bash
apt install -y redis-server

# Configure Redis (/etc/redis/redis.conf)
# Set password
requirepass REDIS_STRONG_PASSWORD

# Set max memory
maxmemory 1gb
maxmemory-policy allkeys-lru

# Enable persistence for queue durability
appendonly yes
appendfsync everysec

systemctl enable redis-server
systemctl restart redis-server
```

### 2.5 Install Nginx
```bash
apt install -y nginx
systemctl enable nginx
```

### 2.6 Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### 2.7 Install Supervisor
```bash
apt install -y supervisor
systemctl enable supervisor
```

---

## 3. Application Deployment

### 3.1 Clone / Upload Application
```bash
mkdir -p /var/www
cd /var/www

# Via Git
git clone https://github.com/your-org/universal-vtu-pro.git
cd universal-vtu-pro

# Set permissions
chown -R www-data:www-data /var/www/universal-vtu-pro
chmod -R 755 /var/www/universal-vtu-pro
chmod -R 775 /var/www/universal-vtu-pro/storage
chmod -R 775 /var/www/universal-vtu-pro/bootstrap/cache
```

### 3.2 Install Dependencies
```bash
cd /var/www/universal-vtu-pro
composer install --optimize-autoloader --no-dev
```

### 3.3 Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Edit .env with your actual values
nano .env
```

Key `.env` settings for production:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.universalvtupro.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=universal_vtu_pro
DB_USERNAME=vtupro
DB_PASSWORD=STRONG_PASSWORD_HERE

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=REDIS_STRONG_PASSWORD
REDIS_PORT=6379

# Payment Gateways
MONNIFY_API_KEY=your_monnify_api_key
MONNIFY_SECRET_KEY=your_monnify_secret_key
MONNIFY_CONTRACT_CODE=your_contract_code
MONNIFY_WEBHOOK_SECRET=your_webhook_secret

PAYSTACK_SECRET_KEY=sk_live_your_paystack_secret
PAYSTACK_WEBHOOK_SECRET=your_paystack_webhook_secret

FLUTTERWAVE_SECRET_KEY=FLWSECK_your_secret
FLUTTERWAVE_WEBHOOK_SECRET=your_flw_webhook_secret

# VTU Providers
VTPASS_API_KEY=your_vtpass_api_key
VTPASS_SECRET_KEY=your_vtpass_secret

# Admin
ADMIN_EMAIL=admin@universalvtupro.com
ADMIN_PASSWORD=StrongAdminPass!
```

### 3.4 Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

---

## 4. Database Setup

```bash
cd /var/www/universal-vtu-pro

# Run all migrations
php artisan migrate --force

# Seed initial data (roles, admin, providers, data plans)
php artisan db:seed --force

# Verify
php artisan tinker --execute="echo 'Users: ' . App\Models\User::count();"
```

### Spatie Permissions Setup
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate --force
```

---

## 5. Redis Setup

Verify Redis connection:
```bash
php artisan tinker --execute="echo Cache::store('redis')->put('test', 'ok', 60) ? 'Redis OK' : 'Redis FAIL';"
```

---

## 6. Queue Workers

### 6.1 Configure Supervisor
```bash
# Copy supervisor config
cp /var/www/universal-vtu-pro/supervisor.conf /etc/supervisor/conf.d/uvtp.conf

# Create log directory
mkdir -p /var/log/uvtp
chown www-data:www-data /var/log/uvtp

# Update supervisor
supervisorctl reread
supervisorctl update
supervisorctl start uvtp-workers:*

# Verify workers
supervisorctl status
```

### 6.2 Queue Queues Overview

| Queue            | Workers | Purpose                                    |
|------------------|---------|--------------------------------------------|
| `vtu`            | 4       | Airtime, Data, Cable, Electricity, Exam    |
| `notifications`  | 2       | Email & SMS notifications                  |
| `webhooks`       | 2       | Outbound webhook delivery (with retries)   |
| `reports`        | 1       | CSV/Excel/PDF report exports               |
| `default`        | 2       | Referral commissions, misc background work |

### 6.3 Scheduled Commands
Add to crontab (`crontab -e -u www-data`):
```
* * * * * cd /var/www/universal-vtu-pro && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Nginx Configuration

```bash
cp /var/www/universal-vtu-pro/nginx.conf /etc/nginx/sites-available/uvtp
ln -s /etc/nginx/sites-available/uvtp /etc/nginx/sites-enabled/uvtp
rm /etc/nginx/sites-enabled/default

# Test and reload
nginx -t && systemctl reload nginx
```

---

## 8. SSL Certificate

```bash
apt install -y certbot python3-certbot-nginx

certbot --nginx -d api.universalvtupro.com \
  --non-interactive --agree-tos \
  -m ssl@universalvtupro.com

# Auto-renew
systemctl enable certbot.timer
```

---

## 9. Environment Configuration

### 9.1 Payment Gateway Webhooks

Configure these webhook URLs in each payment gateway dashboard:

| Gateway      | Webhook URL                                          |
|-------------|------------------------------------------------------|
| Monnify     | `https://api.universalvtupro.com/api/webhooks/monnify`      |
| Paystack    | `https://api.universalvtupro.com/api/webhooks/paystack`     |
| Flutterwave | `https://api.universalvtupro.com/api/webhooks/flutterwave`  |

### 9.2 Sanctum Configuration
```bash
# In .env
SANCTUM_STATEFUL_DOMAINS=app.universalvtupro.com,admin.universalvtupro.com
```

---

## 10. Post-Deployment Checklist

Run this checklist after every deployment:

```bash
#!/bin/bash
echo "=== Universal VTU Pro Deployment Checklist ==="

# 1. Check PHP version
php -v | grep "PHP 8.4" && echo "✓ PHP 8.4" || echo "✗ PHP version mismatch"

# 2. Check .env exists and is not .example
[ -f /var/www/universal-vtu-pro/.env ] && echo "✓ .env exists" || echo "✗ .env missing"

# 3. Check app key
grep -q "APP_KEY=base64:" /var/www/universal-vtu-pro/.env && echo "✓ APP_KEY set" || echo "✗ APP_KEY missing"

# 4. Test database connection
cd /var/www/universal-vtu-pro && php artisan db:show > /dev/null 2>&1 && echo "✓ Database connected" || echo "✗ Database connection failed"

# 5. Test Redis connection
cd /var/www/universal-vtu-pro && php artisan tinker --execute="Cache::put('hc', 1, 10); echo Cache::get('hc') === 1 ? 'Redis OK' : 'Redis FAIL';" 2>/dev/null

# 6. Check queue workers
supervisorctl status | grep "uvtp-worker" | grep -v "RUNNING" && echo "✗ Some workers not running" || echo "✓ All queue workers running"

# 7. Check storage permissions
[ -w /var/www/universal-vtu-pro/storage ] && echo "✓ Storage writable" || echo "✗ Storage not writable"

# 8. Check config cache
[ -f /var/www/universal-vtu-pro/bootstrap/cache/config.php ] && echo "✓ Config cached" || echo "✗ Config not cached — run: php artisan config:cache"

# 9. Check routes cache
[ -f /var/www/universal-vtu-pro/bootstrap/cache/routes-v7.php ] && echo "✓ Routes cached" || echo "✗ Routes not cached — run: php artisan route:cache"

# 10. Test health endpoint
curl -sf https://api.universalvtupro.com/api/health | grep '"status":"ok"' && echo "✓ Health endpoint OK" || echo "✗ Health endpoint failed"

echo "=== Done ==="
```

### Production Hardening Checklist

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] All API keys are production (not sandbox)
- [ ] Webhook secrets set for all gateways
- [ ] Admin password changed from default
- [ ] SSL certificate installed and auto-renewing
- [ ] Database backups configured
- [ ] Log rotation configured
- [ ] Firewall (UFW) configured — only 80, 443, 22 open
- [ ] Redis password set
- [ ] MySQL remote access disabled
- [ ] PHP-FPM running as `www-data`
- [ ] File permissions: `storage/` and `bootstrap/cache/` are 775

---

## 11. Monitoring

### 11.1 Log Files

| Log              | Location                          |
|------------------|------------------------------------|
| Laravel App      | `storage/logs/laravel.log`         |
| Queue Worker VTU | `/var/log/uvtp/worker-vtu.log`     |
| Nginx Access     | `/var/log/nginx/uvtp-access.log`   |
| Nginx Error      | `/var/log/nginx/uvtp-error.log`    |
| MySQL Slow       | `/var/log/mysql/slow.log`          |
| PHP-FPM          | `/var/log/php8.4-fpm.log`          |

### 11.2 Useful Commands

```bash
# Monitor queue workers in real-time
supervisorctl status

# Restart all workers (after deployment)
supervisorctl restart uvtp-workers:*

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush

# Clear all caches
php artisan optimize:clear

# Monitor Redis queue lengths
redis-cli -a $REDIS_PASSWORD llen queues:vtu
redis-cli -a $REDIS_PASSWORD llen queues:notifications

# Check database size
mysql -u vtupro -p universal_vtu_pro -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'universal_vtu_pro' ORDER BY (data_length + index_length) DESC LIMIT 20;"
```

### 11.3 Health Check Endpoint

`GET /api/health` returns:
```json
{
  "status": "ok",
  "service": "Universal VTU Pro API",
  "version": "1.0.0",
  "time": "2024-01-01T00:00:00+00:00"
}
```

Use this with uptime monitoring tools (UptimeRobot, Pingdom, etc.).

---

## 12. CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: uvtp_test
          MYSQL_USER: root
          MYSQL_PASSWORD: ''
          MYSQL_ALLOW_EMPTY_PASSWORD: yes
        ports: ['3306:3306']
      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, xml, zip, mysql, redis, bcmath

      - name: Install Dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Copy .env
        run: cp .env.example .env

      - name: Generate Key
        run: php artisan key:generate

      - name: Configure Test DB
        run: |
          php artisan config:clear
          php artisan migrate --force
          php artisan db:seed --force
        env:
          DB_DATABASE: uvtp_test
          DB_USERNAME: root
          DB_PASSWORD: ''
          QUEUE_CONNECTION: sync

      - name: Run Tests
        run: php artisan test --parallel

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/universal-vtu-pro
            git pull origin main
            composer install --optimize-autoloader --no-dev
            php artisan migrate --force
            php artisan optimize
            supervisorctl restart uvtp-workers:*
            echo "Deployment complete"
```

---

## 13. Scaling Guide

### Horizontal Scaling (Multiple App Servers)

1. Use a load balancer (Nginx, HAProxy, or AWS ALB)
2. Share sessions via Redis (already configured)
3. Share file storage via NFS or S3
4. Point all servers to the same MySQL and Redis instances

### Database Read Replicas

```ini
# .env
DB_CONNECTION=mysql
DB_HOST=primary-db-host
DB_READ_HOST=replica-db-host  # Add replica support in config/database.php
```

### Redis Clustering

For high-throughput deployments, use Redis Sentinel or Redis Cluster for HA.

### Queue Scaling

Increase worker counts in `supervisor.conf`:
- `vtu` workers: Scale to 8–16 for high-transaction volumes
- Use separate Redis databases per queue for isolation

---

## 14. Backup Strategy

### 14.1 Database Backups

```bash
# /etc/cron.d/uvtp-backup
# Daily backup at 2 AM
0 2 * * * root mysqldump -u vtupro -pPASSWORD universal_vtu_pro | gzip > /backups/uvtp_$(date +\%Y\%m\%d).sql.gz

# Keep 30 days of backups
0 3 * * * root find /backups -name "uvtp_*.sql.gz" -mtime +30 -delete
```

### 14.2 Upload Backups to S3 (Optional)

```bash
# Install AWS CLI
apt install -y awscli

# Add to backup script
aws s3 cp /backups/uvtp_$(date +%Y%m%d).sql.gz s3://your-backup-bucket/database/
```

---

## 15. Troubleshooting

### Common Issues

**Queue jobs not processing:**
```bash
supervisorctl status            # Check workers running
supervisorctl restart uvtp-workers:*
php artisan queue:failed        # Check failed jobs
tail -f /var/log/uvtp/worker-vtu.log
```

**500 errors in production:**
```bash
tail -f /var/www/universal-vtu-pro/storage/logs/laravel.log
php artisan config:clear && php artisan cache:clear
```

**Database connection refused:**
```bash
systemctl status mysql
mysql -u vtupro -p -h 127.0.0.1   # Test connection
```

**Redis connection refused:**
```bash
systemctl status redis-server
redis-cli -a $REDIS_PASSWORD ping  # Should return PONG
```

**Permission errors:**
```bash
chown -R www-data:www-data /var/www/universal-vtu-pro/storage
chmod -R 775 /var/www/universal-vtu-pro/storage
chmod -R 775 /var/www/universal-vtu-pro/bootstrap/cache
```

**Webhook signature failures:**
- Verify webhook secret matches what's set in payment gateway dashboard
- Check `MONNIFY_WEBHOOK_SECRET`, `PAYSTACK_WEBHOOK_SECRET`, `FLUTTERWAVE_WEBHOOK_SECRET` in `.env`
- Ensure no proxy is modifying the request body before it reaches your server

**Provider API failures:**
```bash
# Check provider logs
php artisan tinker --execute="App\Models\ProviderLog::where('status','failed')->latest()->limit(5)->get(['provider_id','action','error','created_at']);"

# Check provider routing cache
php artisan cache:clear
```
