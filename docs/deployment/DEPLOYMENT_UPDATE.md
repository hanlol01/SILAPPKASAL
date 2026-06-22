# DEPLOYMENT_UPDATE.md

> SOP update SILAPPKASAL pada VPS yang sudah berjalan

## Prasyarat

Pastikan perubahan sudah:

- Commit
- Push ke GitHub

Jangan pernah mengubah kode langsung di VPS.

Flow wajib:

Local
↓
Git Commit
↓
Git Push
↓
VPS Git Pull

---

## Update Backend

cd /var/www/silappkasal

git pull

cd backend/api

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache

php artisan route:cache

chown -R www-data:www-data storage bootstrap/cache

systemctl restart php8.3-fpm

---

## Update Frontend

cd /var/www/silappkasal/frontend

npm install

npm run build

systemctl restart silappkasal-frontend

---

## Verifikasi

Backend:

curl -X POST http://<SERVER_IP>/api/v1/auth/login

Frontend:

http://<APP_DOMAIN>

atau

http://<SERVER_IP>:8080

---

## Rollback

git log --oneline

git checkout <commit-id>

composer install

npm run build

systemctl restart php8.3-fpm

systemctl restart silappkasal-frontend