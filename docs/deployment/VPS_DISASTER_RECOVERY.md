# VPS_DISASTER_RECOVERY.md

> SILAPPKASAL Disaster Recovery Guide
>
> Tujuan:
> Memulihkan SILAPPKASAL ke VPS baru apabila VPS lama mati, hilang, suspended, corrupt, atau tidak dapat diakses.
>
> Target Recovery Time:
>
> * Minimum: 30 menit
> * Normal: 1–2 jam
> * Maksimum: 1 hari

---

# Recovery Prerequisites

Pastikan tersedia:

## 1. GitHub Repository

```text
https://github.com/hanlol01/SILAPPKASAL
```

## 2. Database Backup

Minimal tersedia:

```text
backup-latest.sql
```

atau

```text
backup-YYYY-MM-DD.sql
```

## 3. Deployment Documents

```text
DEPLOYMENT_DEMO_VPS.md
DEPLOYMENT_UPDATE.md
ENV_TEMPLATE.md
```

## 4. Domain Access

Akses ke panel DNS domain:

```text
app.domain.com
api.domain.com
```

---

# Scenario A - VPS Mati Total

Gejala:

```text
Tidak bisa SSH
Tidak bisa Ping
Provider menghapus VPS
VPS corrupt
```

---

## Step 1 - Beli VPS Baru

Spesifikasi minimum:

```text
Ubuntu 22.04
2 vCPU
2 GB RAM
40 GB SSD
```

Catat:

```text
IP VPS Baru
Username
Password Root
```

---

## Step 2 - Login VPS Baru

Gunakan:

```bash
ssh root@IP_BARU
```

atau PuTTY.

---

## Step 3 - Ikuti Deployment Guide

Buka:

```text
DEPLOYMENT_DEMO_VPS.md
```

Lakukan seluruh proses:

```text
Install PHP
Install PostgreSQL
Install NodeJS
Install Nginx
Clone GitHub
Setup Laravel
Setup Frontend
```

Sampai aplikasi dapat dijalankan.

---

## Step 4 - Restore Database

Masukkan backup:

```bash
psql silappkasal < backup-latest.sql
```

Verifikasi:

```bash
php artisan tinker
```

```php
App\Models\User::count();
```

Pastikan data berhasil kembali.

---

## Step 5 - Build Ulang Frontend

```bash
cd frontend

npm install
npm run build
```

---

## Step 6 - Verifikasi Backend

Tes login:

```bash
curl -X POST http://IP_BARU/api/v1/auth/login
```

Pastikan:

```text
Login successful
```

---

## Step 7 - Verifikasi Frontend

Buka:

```text
http://IP_BARU:8080
```

Pastikan:

```text
Login page tampil
Dashboard tampil
Reporter Portal tampil
```

---

## Step 8 - Update DNS

Masuk ke panel domain.

Ubah:

```text
app.domain.com
api.domain.com
```

menjadi:

```text
IP VPS Baru
```

Contoh:

```text
A Record
app -> 103.xxx.xxx.xxx

A Record
api -> 103.xxx.xxx.xxx
```

---

## Step 9 - Tunggu Propagasi DNS

Cek:

```bash
ping app.domain.com
ping api.domain.com
```

atau:

```bash
dig app.domain.com
dig api.domain.com
```

---

## Step 10 - Aktifkan SSL

Jika menggunakan domain:

```bash
certbot --nginx
```

Pastikan:

```text
https://app.domain.com
https://api.domain.com
```

berjalan normal.

---

# Recovery Validation Checklist

## Backend

* [ ] Laravel berjalan
* [ ] Database terkoneksi
* [ ] Login berhasil
* [ ] API merespon

## Frontend

* [ ] Login page tampil
* [ ] Dashboard tampil
* [ ] Reporter Portal tampil

## Database

* [ ] User tersedia
* [ ] Laporan tersedia
* [ ] Kasus tersedia
* [ ] Audit log tersedia

## Domain

* [ ] DNS mengarah ke VPS baru
* [ ] SSL aktif

---

# Recovery Complete

Recovery dianggap selesai apabila:

```text
Frontend dapat diakses
Backend dapat diakses
Database berhasil direstore
DNS mengarah ke VPS baru
```

Status:

```text
SYSTEM RECOVERED
```
