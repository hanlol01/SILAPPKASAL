# DEPLOYMENT_DEMO_VPS.md — SILAPPKASAL Demo Deployment

# Variable Reference

Sebelum memulai deployment, tentukan nilai berikut:

| Variable              | Description              |
| --------------------- | ------------------------ |
| `<SERVER_IP>`         | IP VPS saat ini          |
| `<APP_DOMAIN>`        | Domain frontend          |
| `<API_DOMAIN>`        | Domain backend API       |
| `<DB_NAME>`           | Nama database PostgreSQL |
| `<DB_USER>`           | User PostgreSQL          |
| `<DB_PASSWORD>`       | Password PostgreSQL      |
| `<GITHUB_REPOSITORY>` | Repository GitHub        |
| `<DEMO_PASSWORD>`     | Password akun demo       |

Contoh:

SERVER_IP=103.xxx.xxx.xxx

APP_DOMAIN=app.silappkasal.com

API_DOMAIN=api.silappkasal.com


## 1. Server Assumptions

Example server:

```text
SERVER_IP=178.128.84.76
OS=Ubuntu 22.04
User=root
Project path=/var/www/silappkasal
Backend path=/var/www/silappkasal/backend/api
Frontend path=/var/www/silappkasal/frontend
```

For a new VPS, replace `178.128.84.76` with the new server IP.

---

## 2. Initial Security Step

After first login as root:

```bash
passwd
```

Use a new strong root password.

Optional but recommended later:

```bash
adduser deploy
usermod -aG sudo deploy
```

---

## 3. Update System

```bash
apt update && apt upgrade -y
apt install -y nginx git curl unzip zip software-properties-common ca-certificates lsb-release gnupg apt-transport-https
```

---

## 4. Install PHP 8.3

Laravel dependency requires PHP `>= 8.2`, so use PHP 8.3.

```bash
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd
```

Set PHP CLI to 8.3:

```bash
update-alternatives --set php /usr/bin/php8.3
update-alternatives --set php-config /usr/bin/php-config8.3
update-alternatives --set phpize /usr/bin/phpize8.3
```

Verify:

```bash
php -v
```

Expected:

```text
PHP 8.3.x
```

---

## 5. Install Composer

If Composer is not available:

```bash
apt install -y composer
```

Verify:

```bash
composer --version
```

---

## 6. Install PostgreSQL

```bash
apt install -y postgresql postgresql-contrib
systemctl enable --now postgresql
```

Create database and user:

```bash
sudo -u postgres psql
```

Inside PostgreSQL:

```sql
CREATE DATABASE silappkasal;
CREATE USER silappkasal_user WITH PASSWORD 'CHANGE_THIS_STRONG_DB_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE silappkasal TO silappkasal_user;
GRANT ALL ON SCHEMA public TO silappkasal_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO silappkasal_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO silappkasal_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO silappkasal_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO silappkasal_user;
\q
```

---

## 7. Install Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

Verify:

```bash
node -v
npm -v
```

Expected:

```text
Node.js v20.x
npm installed
```

---

## 8. Prepare GitHub Private Repository Access

Because the repository is private, use a GitHub deploy key.

Create deploy key on VPS:

```bash
ssh-keygen -t ed25519 -C "silappkasal-vps" -f ~/.ssh/silappkasal_deploy
cat ~/.ssh/silappkasal_deploy.pub
```

Copy the public key.

In GitHub:

```text
Repository → Settings → Deploy keys → Add deploy key
Title: SILAPPKASAL VPS Demo
Key: paste public key
Allow write access: OFF
```

Create SSH config:

```bash
nano ~/.ssh/config
```

Add:

```text
Host github-silappkasal
  HostName github.com
  User git
  IdentityFile ~/.ssh/silappkasal_deploy
  IdentitiesOnly yes
```

Test connection:

```bash
ssh -T github-silappkasal
```

---

## 9. Clone Project

```bash
mkdir -p /var/www
cd /var/www
git clone git@github-silappkasal:hanlol01/SILAPPKASAL.git silappkasal
```

Check structure:

```bash
cd /var/www/silappkasal
ls
```

Expected:

```text
backend
frontend
docs
```

---

## 10. Backend Laravel Setup

```bash
cd /var/www/silappkasal/backend/api
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Edit `.env`:

```bash
nano .env
```

Example demo/staging `.env` using IP only:

```APP_URL=http://<SERVER_IP>

DB_DATABASE=<DB_NAME>
DB_USERNAME=<DB_USER>
DB_PASSWORD=<DB_PASSWORD>

SANCTUM_STATEFUL_DOMAINS=<SERVER_IP>:8080

FRONTEND_URL=http://<SERVER_IP>:8080
```

After editing `.env`, regenerate/cache:

```bash
php artisan key:generate
php artisan optimize:clear
```

Run migration and seeders:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=DemoDataSeeder --force
```

Storage link and permissions:

```bash
php artisan storage:link
mkdir -p bootstrap/cache storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Cache config/routes:

```bash
php artisan config:cache
php artisan route:cache
```

Check migrations:

```bash
php artisan migrate:status
```

---

## 11. Backend Nginx Config

Create Nginx site:

```bash
nano /etc/nginx/sites-available/silappkasal-api
```

Content:

```nginx
server {
    listen 80;
    server_name 178.128.84.76;

    root /var/www/silappkasal/backend/api/public;
    index index.php index.html;

    client_max_body_size 20M;

    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /storage/ {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
ln -s /etc/nginx/sites-available/silappkasal-api /etc/nginx/sites-enabled/ 2>/dev/null || true
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl restart php8.3-fpm
systemctl reload nginx
```

Test backend login:

```bash
curl -X POST "http://178.128.84.76/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"identifier":"demo.admin@silappkasal.test","password":"DemoPass123!"}'
```

Expected: JSON response with token.

---

## 12. Frontend Setup

```bash
cd /var/www/silappkasal/frontend
cp .env.example .env.production
nano .env.production
```

Set:

```env
VITE_API_BASE_URL=http://<SERVER_IP>/api/v1
```

Install and build:

```bash
npm ci
npm run build
```

The production Node build output is:

```text
dist/client
dist/server/index.mjs
```

`dist/client` contains browser assets. `dist/server/index.mjs` is the Nitro `node-server`
entrypoint that serves SSR routes and those assets. Do not use `vite preview` as the
production service command, and do not require Wrangler or Miniflare on the VPS.

---

## 13. Frontend Node Service

Create systemd service:

```bash
nano /etc/systemd/system/silappkasal-frontend.service
```

Content:

```ini
[Unit]
Description=SILAPPKASAL Frontend Node Server
After=network.target

[Service]
Type=simple
User=ubuntu
Group=ubuntu
WorkingDirectory=/var/www/silappkasal/frontend
Environment=NODE_ENV=production
Environment=HOST=127.0.0.1
Environment=PORT=3000
ExecStart=/usr/bin/npm run start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Nitro's Node preset reads `HOST` and `PORT` (or the equivalent `NITRO_HOST` and
`NITRO_PORT`) at runtime. Keep this service bound to `127.0.0.1:3000`; Nginx remains
the public reverse proxy.

Enable service:

```bash
systemctl daemon-reload
systemctl enable --now silappkasal-frontend
systemctl status silappkasal-frontend --no-pager
```

Test locally on VPS:

```bash
curl -I http://127.0.0.1:3000
```

---

## 14. Frontend Nginx Config

Create frontend Nginx site:

```bash
nano /etc/nginx/sites-available/silappkasal-frontend
```

Content:

```nginx
server {
    listen 8080;
    server_name 178.128.84.76;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable:

```bash
ln -s /etc/nginx/sites-available/silappkasal-frontend /etc/nginx/sites-enabled/ 2>/dev/null || true
nginx -t
systemctl reload nginx
```

Open in browser:

```text
http://178.128.84.76:8080
```

Dashboard example:

```text
http://178.128.84.76:8080/dashboard
```

---

## 15. Demo Accounts

If `DemoDataSeeder` has been run:

```text
Super Admin:
demo.superadmin@silappkasal.test
DemoPass123!

Admin:
demo.admin@silappkasal.test
DemoPass123!

Satgas:
demo.satgas@silappkasal.test
DemoPass123!
```

Reporter accounts may need to be created through reporter registration + approval flow unless a QA/demo reporter is manually created.

---

## 16. Common Deployment Update Flow

When local code changes are pushed to GitHub:

```bash
cd /var/www/silappkasal
git pull
```

Backend update:

```bash
cd /var/www/silappkasal/backend/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache
systemctl restart php8.3-fpm
systemctl reload nginx
```

Frontend update:

```bash
cd /var/www/silappkasal/frontend
npm ci
npm run build
systemctl restart silappkasal-frontend
systemctl reload nginx
```

---

## 17. Optional Domain Setup Later

Recommended future domain structure:

```text
<APP_DOMAIN> → frontend
<API_DOMAIN> → backend
Contoh :
APP_DOMAIN=app.silappkasal.com
API_DOMAIN=api.silappkasal.com
```

DNS records:

```text
app  A  <SERVER_IP>
api  A  <SERVER_IP>
```

After DNS is active, update:

Backend `.env`:

```env
APP_URL=https://api.ossprinting.biz.id
SANCTUM_STATEFUL_DOMAINS=app.ossprinting.biz.id
FRONTEND_URL=https://app.ossprinting.biz.id
```

Frontend `.env.production`:

```env
VITE_API_BASE_URL=https://api.ossprinting.biz.id/api/v1
```

Then rebuild frontend:

```bash
cd /var/www/silappkasal/frontend
npm run build
systemctl restart silappkasal-frontend
```

Nginx should later be changed from IP-based config to domain-based config, and SSL should be installed using Certbot.

Install Certbot later:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d app.ossprinting.biz.id -d api.ossprinting.biz.id
```

---

## 18. Quick Health Checks

Backend login:

```bash
curl -X POST "http://178.128.84.76/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"identifier":"demo.admin@silappkasal.test","password":"DemoPass123!"}'
```

Frontend:

```bash
curl -I http://178.128.84.76:8080
```

Nginx:

```bash
nginx -t
systemctl status nginx --no-pager
```

PHP-FPM:

```bash
systemctl status php8.3-fpm --no-pager
```

Frontend service:

```bash
systemctl status silappkasal-frontend --no-pager
```

Logs:

```bash
tail -n 100 /var/www/silappkasal/backend/api/storage/logs/laravel.log
tail -n 100 /var/log/nginx/error.log
journalctl -u silappkasal-frontend -n 100 --no-pager
```

---

## 19. Current Demo URLs Without Domain

```text
Frontend:
http://<SERVER_IP>:8080

Backend API:
http://<SERVER_IP>/api/v1
```

Use this until domain/subdomain DNS is active.

# VPS Independence Principle

SILAPPKASAL must never depend on a specific VPS.

Source of Truth:

1. GitHub Repository
2. PostgreSQL Backup
3. Deployment Documentation

The VPS is considered disposable infrastructure.

If a VPS is lost:

1. Purchase a new VPS.
2. Follow DEPLOYMENT_DEMO_VPS.md.
3. Restore PostgreSQL backup.
4. Update DNS.
5. Verify application health.

No business-critical data should exist only on the VPS.
