# APELS — VPS Deployment Guide

## Prerequisites

- Ubuntu 22.04 LTS
- PHP 8.2+ with extensions: pdo_mysql, mbstring, openssl, xml, curl, zip, pcntl
- MySQL 8.0+
- Nginx
- Node.js 20+ & npm
- Composer 2.x
- Supervisor (for queue workers)
- Certbot (for SSL)

## Initial Setup

```bash
# Clone project
git clone <repo-url> /var/www/apels
cd /var/www/apels

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies & build assets
npm ci && npm run build

# Set permissions
chown -R www-data:www-data /var/www/apels/storage
chown -R www-data:www-data /var/www/apels/bootstrap/cache
chmod -R 775 /var/www/apels/storage
chmod -R 775 /var/www/apels/bootstrap/cache

# Configure environment
cp .env.production.example .env
nano .env   # Fill in DB credentials, OPENAI_API_KEY, APP_URL, APP_KEY

# Generate app key
php artisan key:generate

# Run migrations & seeders
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=ModuleSeeder --force

# Optimize for production (Req 32.2)
php artisan optimize
```

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/apels/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Block direct access to private storage
    location ~ ^/storage/private {
        deny all;
        return 403;
    }
}
```

## SSL via Let's Encrypt (Req 32.4)

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx -y

# Obtain certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal is configured by certbot; verify:
sudo systemctl status certbot.timer

# Manual renewal test:
sudo certbot renew --dry-run
```

## Queue Workers with Supervisor (Req 31.2)

```bash
# Install supervisor
sudo apt install supervisor -y

# Copy config
sudo cp /var/www/apels/deploy/supervisor/apels-worker.conf /etc/supervisor/conf.d/

# Edit path if needed
sudo nano /etc/supervisor/conf.d/apels-worker.conf

# Reload and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start apels-worker:*

# Monitor workers
sudo supervisorctl status
```

## Cron Scheduler (Req 23.5)

```bash
# Add to crontab (run as www-data or deploy user)
sudo -u www-data crontab -e

# Add this line:
* * * * * cd /var/www/apels && php artisan schedule:run >> /dev/null 2>&1
```

## Deployment (subsequent releases)

```bash
cd /var/www/apels
./deploy/deploy.sh
```

## Environment Variables Checklist

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_KEY` | ✅ | Generate with `php artisan key:generate` |
| `APP_ENV` | ✅ | Set to `production` |
| `APP_DEBUG` | ✅ | Set to `false` |
| `APP_URL` | ✅ | Full HTTPS URL |
| `DB_*` | ✅ | MySQL credentials |
| `OPENAI_API_KEY` | ✅ | Required for Whisper + GPT-4o Mini |
| `QUEUE_CONNECTION` | ✅ | Set to `redis` for production |
| `CACHE_STORE` | ✅ | Set to `redis` for production |
| `REDIS_*` | ✅ | Redis connection details |

## Budget Alert

OpenAI API target: ≤ $20/month for 100+ students (Req 25.4).
Monitor usage at https://platform.openai.com/usage.
Cost-control mechanisms:
- Daily test limit: 1 test/student/day
- NLP cache: 24h TTL (avoids duplicate API calls)
- Audio file deleted after processing (no storage cost)
