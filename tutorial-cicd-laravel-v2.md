# Tutorial CI/CD Laravel dengan GitHub Actions

## Deploy Manual & Deploy dengan Docker

**Mata Kuliah:** Workshop Developer Operational  
**Topik:** CI/CD Pipeline — GitHub Actions

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Membuat Project Laravel](#2-membuat-project-laravel)
3. [Membuat Unit Test Sederhana](#3-membuat-unit-test-sederhana)
4. [Setup Repository GitHub](#4-setup-repository-github)
5. [Bagian A — Deploy Manual (Tanpa Docker)](#bagian-a--deploy-manual-tanpa-docker)
6. [Bagian B — Deploy dengan Docker](#bagian-b--deploy-dengan-docker)
7. [Perbandingan Kedua Pendekatan](#7-perbandingan-kedua-pendekatan)
8. [Troubleshooting — Error yang Sering Ditemui](#8-troubleshooting--error-yang-sering-ditemui)

---

## 1. Prasyarat

### Di mesin lokal (laptop/PC mahasiswa)

- PHP 8.2 atau lebih baru
- Composer 2.x
- Git
- Akun [GitHub](https://github.com)

### Di server (VPS)

- Ubuntu 22.04 LTS
- Akses SSH sebagai root atau user dengan sudo

### Untuk Bagian B (Docker)

- Akun [Docker Hub](https://hub.docker.com)
- Docker Engine terinstall di VPS

> **Catatan:** Tutorial ini menggunakan **Docker Hub** sebagai container registry. Pastikan sudah mendaftarkan akun di [hub.docker.com](https://hub.docker.com) sebelum memulai Bagian B.

---

## 2. Membuat Project Laravel

### 2.1 Buat project baru

Jalankan perintah berikut di terminal lokal:

```bash
composer create-project laravel/laravel cicd-laravel
cd cicd-laravel
```

### 2.2 Buat fitur sederhana — kalkulator

Kita akan membuat sebuah **service class** sederhana sebagai fitur yang akan diuji. Ini juga akan menjadi objek dari unit test kita.

```bash
php artisan make:class Services/Calculator
```

Buka file `app/Services/Calculator.php` dan isi dengan kode berikut:

```php
<?php

namespace App\Services;

class Calculator
{
    public function add(int|float $a, int|float $b): int|float
    {
        return $a + $b;
    }

    public function subtract(int|float $a, int|float $b): int|float
    {
        return $a - $b;
    }

    public function multiply(int|float $a, int|float $b): int|float
    {
        return $a * $b;
    }

    public function divide(int|float $a, int|float $b): int|float
    {
        if ($b === 0 || $b === 0.0) {
            throw new \InvalidArgumentException('Pembagi tidak boleh nol.');
        }

        return $a / $b;
    }
}
```

### 2.3 Buat route sederhana untuk demo

Buka file `routes/web.php` dan tambahkan route berikut:

```php
<?php

use App\Services\Calculator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hitung', function (Calculator $calc) {
    $hasil = $calc->add(10, 5);

    return response()->json([
        'operasi' => '10 + 5',
        'hasil'   => $hasil,
    ]);
});
```

### 2.4 Verifikasi aplikasi berjalan

```bash
php artisan serve
```

Buka browser dan akses `http://localhost:8000/hitung`. Anda seharusnya melihat output JSON:

```json
{
    "operasi": "10 + 5",
    "hasil": 15
}
```

---

## 3. Membuat Unit Test Sederhana

### 3.1 Buat file test

```bash
php artisan make:test CalculatorTest --unit
```

Perintah ini membuat file di `tests/Unit/CalculatorTest.php`.

### 3.2 Tulis test case

Buka `tests/Unit/CalculatorTest.php` dan ganti seluruh isinya dengan:

```php
<?php

namespace Tests\Unit;

use App\Services\Calculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    private Calculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new Calculator();
    }

    /** @test */
    public function it_can_add_two_numbers(): void
    {
        $result = $this->calc->add(10, 5);

        $this->assertEquals(15, $result);
    }

    /** @test */
    public function it_can_subtract_two_numbers(): void
    {
        $result = $this->calc->subtract(10, 3);

        $this->assertEquals(7, $result);
    }

    /** @test */
    public function it_can_multiply_two_numbers(): void
    {
        $result = $this->calc->multiply(4, 3);

        $this->assertEquals(12, $result);
    }

    /** @test */
    public function it_can_divide_two_numbers(): void
    {
        $result = $this->calc->divide(10, 2);

        $this->assertEquals(5, $result);
    }

    /** @test */
    public function it_throws_exception_when_dividing_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pembagi tidak boleh nol.');

        $this->calc->divide(10, 0);
    }
}
```

### 3.3 Jalankan test secara lokal

```bash
php artisan test
```

Output yang diharapkan:

```
   PASS  Tests\Unit\CalculatorTest
  ✓ it can add two numbers                                          0.01s
  ✓ it can subtract two numbers                                     0.01s
  ✓ it can multiply two numbers                                     0.01s
  ✓ it can divide two numbers                                       0.01s
  ✓ it throws exception when dividing by zero                       0.01s

  Tests:    5 passed (5 assertions)
  Duration: 0.08s
```

> **Konsep penting:** Semua test harus **hijau (pass)** sebelum kode boleh masuk ke repository. Dalam CI/CD, pipeline akan menjalankan perintah ini secara otomatis setiap ada push.

### 3.4 Demonstrasi test gagal (opsional, untuk kelas)

Untuk menunjukkan kepada mahasiswa bagaimana pipeline akan berhenti saat test gagal, ubah sementara nilai ekspektasi di salah satu test:

```php
// Ubah ini (sengaja salah)
$this->assertEquals(999, $result); // seharusnya 15
```

Jalankan lagi `php artisan test` dan lihat outputnya merah. **Kembalikan ke nilai yang benar** sebelum melanjutkan.

---

## 4. Setup Repository GitHub

### 4.1 Inisialisasi Git dan push ke GitHub

```bash
# Di dalam folder project cicd-laravel
git init
git add .
git commit -m "feat: initial Laravel project dengan Calculator service dan unit test"
```

Buat repository baru di GitHub (misalnya bernama `cicd-laravel`), kemudian:

```bash
git remote add origin git@github.com:USERNAME/cicd-laravel.git
git branch -M main
git push -u origin main
```

Ganti `USERNAME` dengan username GitHub Anda.

### 4.2 Pastikan file `.gitignore` sudah benar

Laravel sudah menyertakan `.gitignore` yang mengabaikan file-file sensitif. Pastikan baris berikut ada di dalamnya:

```
/vendor
.env
.env.backup
```

File `.env` **tidak boleh** masuk ke repository karena berisi kredensial (password database, app key, dll).

---

---

# Bagian A — Deploy Manual (Tanpa Docker)

Pada bagian ini, pipeline CI/CD akan:

1. Menjalankan **unit test** di GitHub Actions runner
2. Jika test lulus, melakukan **deploy langsung ke VPS via SSH** dengan menarik kode dari GitHub dan menjalankan perintah artisan

```
Push → Test → SSH ke VPS → git pull → composer install → artisan commands → selesai
```

---

## A.1 Persiapan Server VPS

### A.1.1 Install dependensi di VPS

Login ke VPS via SSH, kemudian jalankan:

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 dan ekstensi yang dibutuhkan Laravel
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install -y nginx

# Install MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### A.1.2 Buat user deployer

Membuat user khusus untuk proses deployment adalah praktik keamanan yang baik — user ini tidak memiliki akses root.

```bash
sudo adduser deployer
sudo usermod -aG www-data deployer
```

### A.1.3 Setup direktori aplikasi

```bash
sudo mkdir -p /var/www/cicd-laravel
sudo chown -R deployer:www-data /var/www/cicd-laravel
sudo chmod -R 755 /var/www/cicd-laravel
```

### A.1.4 Konfigurasi Nginx

Buat file konfigurasi Nginx:

```bash
sudo nano /etc/nginx/sites-available/cicd-laravel
```

Isi dengan konfigurasi berikut (ganti `yourdomain.com` dengan IP atau domain Anda):

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/cicd-laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan site dan restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/cicd-laravel /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### A.1.5 Setup database

```bash
sudo mysql -u root -p
```

Di dalam MySQL shell:

```sql
CREATE DATABASE cicd_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel_user'@'localhost' IDENTIFIED BY 'password_anda_yang_kuat';
GRANT ALL PRIVILEGES ON cicd_laravel.* TO 'laravel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## A.2 Setup SSH Key untuk Deployment

GitHub Actions membutuhkan SSH key untuk dapat masuk ke VPS tanpa password.

### A.2.1 Generate SSH key pair di mesin lokal

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy
```

Tekan Enter dua kali untuk **tanpa passphrase** (penting agar Actions bisa login otomatis).

Ini menghasilkan dua file:

- `~/.ssh/github_actions_deploy` — **private key** (untuk GitHub Secrets)
- `~/.ssh/github_actions_deploy.pub` — **public key** (untuk server)

### A.2.2 Daftarkan public key ke server

```bash
# Copy isi public key
cat ~/.ssh/github_actions_deploy.pub
```

Login ke VPS sebagai `deployer`, kemudian:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
```

Paste isi public key ke file tersebut, simpan, kemudian:

```bash
chmod 600 ~/.ssh/authorized_keys
```

### A.2.3 Clone repository pertama kali di server

Masih sebagai user `deployer` di VPS:

```bash
cd /var/www
git clone https://github.com/USERNAME/cicd-laravel.git cicd-laravel
cd cicd-laravel
```

### A.2.4 Setup file `.env` di server

```bash
cp .env.example .env
nano .env
```

Isi nilai-nilai berikut sesuai konfigurasi server:

```env
APP_NAME="CICD Laravel"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cicd_laravel
DB_USERNAME=laravel_user
DB_PASSWORD=password_anda_yang_kuat
```

Generate app key:

```bash
php artisan key:generate
```

Install dependencies dan setup:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan storage:link

# Set permission storage
sudo chown -R deployer:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## A.3 Simpan Secrets di GitHub

Buka repository GitHub Anda → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**.

Tambahkan tiga secrets berikut:

| Name              | Value                                                                              |
| ----------------- | ---------------------------------------------------------------------------------- |
| `SSH_PRIVATE_KEY` | Isi file `~/.ssh/github_actions_deploy` (seluruhnya termasuk `-----BEGIN...-----`) |
| `SSH_HOST`        | IP address VPS Anda                                                                |
| `SSH_USER`        | `deployer`                                                                         |

---

## A.4 Buat Workflow GitHub Actions

Buat folder dan file berikut di dalam project lokal Anda:

```bash
mkdir -p .github/workflows
touch .github/workflows/deploy.yml
```

Isi file `.github/workflows/deploy.yml`:

```yaml
name: CI/CD Laravel — Manual Deploy

on:
    push:
        branches: [main]
    pull_request:
        branches: [main]

jobs:
    # ─────────────────────────────────────────
    # Job 1: Jalankan unit test
    # ─────────────────────────────────────────
    test:
        name: Run Unit Tests
        runs-on: ubuntu-latest

        steps:
            - name: Checkout kode
              uses: actions/checkout@v4

            - name: Setup PHP 8.2
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.2"
                  extensions: mbstring, bcmath, sqlite3
                  coverage: none

            - name: Cache Composer dependencies
              uses: actions/cache@v4
              with:
                  path: vendor
                  key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
                  restore-keys: ${{ runner.os }}-composer-

            - name: Install dependencies
              run: composer install --no-interaction --prefer-dist --no-progress

            - name: Copy .env untuk testing
              run: cp .env.example .env && php artisan key:generate

            - name: Jalankan unit test
              run: php artisan test

    # ─────────────────────────────────────────
    # Job 2: Deploy ke VPS (hanya jika test lulus
    #         dan hanya dari branch main)
    # ─────────────────────────────────────────
    deploy:
        name: Deploy ke VPS
        needs: test
        runs-on: ubuntu-latest
        if: github.ref == 'refs/heads/main' && github.event_name == 'push'

        steps:
            - name: Deploy via SSH
              uses: appleboy/ssh-action@v1.2.0
              with:
                  host: ${{ secrets.SSH_HOST }}
                  username: ${{ secrets.SSH_USER }}
                  key: ${{ secrets.SSH_PRIVATE_KEY }}
                  script: |
                      set -e

                      cd /var/www/cicd-laravel

                      echo ">>> Menarik kode terbaru dari GitHub..."
                      git pull origin main

                      echo ">>> Install/update Composer dependencies..."
                      composer install --no-dev --optimize-autoloader --no-interaction

                      echo ">>> Jalankan migration database..."
                      php artisan migrate --force

                      echo ">>> Bersihkan dan rebuild cache..."
                      php artisan config:cache
                      php artisan route:cache
                      php artisan view:cache

                      echo ">>> Set ulang permission storage..."
                      chmod -R 775 storage bootstrap/cache

                      echo ">>> Deploy selesai!"
```

### A.5 Commit dan push workflow

```bash
git add .github/
git commit -m "ci: tambah workflow GitHub Actions untuk test dan deploy"
git push origin main
```

Buka tab **Actions** di repository GitHub Anda. Anda akan melihat workflow berjalan secara otomatis.

---

## A.6 Demonstrasi Alur CI/CD (untuk kelas)

### Skenario 1: Push dengan test yang lulus ✅

Buat perubahan kecil, misalnya ubah pesan di route `/hitung`:

```php
return response()->json([
    'operasi' => '10 + 5',
    'hasil'   => $hasil,
    'pesan'   => 'Halo dari CI/CD!',  // tambahkan baris ini
]);
```

```bash
git add .
git commit -m "feat: tambah pesan pada response kalkulator"
git push origin main
```

Perhatikan di tab Actions: job `test` berjalan → lulus → job `deploy` otomatis berjalan → aplikasi di server terupdate.

### Skenario 2: Push dengan test yang gagal ❌

Ubah sengaja nilai di `CalculatorTest.php`:

```php
// Ubah sementara menjadi nilai yang salah
$this->assertEquals(999, $result); // seharusnya 15
```

```bash
git add .
git commit -m "test: sengaja gagal untuk demo"
git push origin main
```

Perhatikan di tab Actions: job `test` **gagal** → job `deploy` **tidak berjalan sama sekali**. Server tidak tersentuh.

Kembalikan nilai yang benar, commit, dan push lagi untuk memperbaiki pipeline.

---

---

# Bagian B — Deploy dengan Docker

Pada bagian ini, pipeline CI/CD akan:

1. Menjalankan **unit test**
2. Jika test lulus, **membangun Docker image** dari kode Laravel
3. **Push image ke Docker Hub**
4. SSH ke VPS dan perintahkan server untuk **pull image baru dan restart container**

```
Push → Test → Build Docker image → Push ke Docker Hub → SSH → docker compose pull → docker compose up
```

> **Keuntungan pendekatan ini:** Server tidak perlu install PHP, Composer, atau dependency apapun. Semua sudah terbungkus di dalam image. Konsisten antara environment development, staging, dan production.

---

## B.1 Persiapan Docker di Mesin Lokal

Pastikan Docker Desktop sudah terinstall. Verifikasi dengan:

```bash
docker --version
docker compose version
```

---

## B.2 Membuat File-file Docker

### B.2.1 `Dockerfile`

Buat file `Dockerfile` di root project:

```dockerfile
# ── Stage 1: Builder ──────────────────────────────────────
# Stage ini hanya untuk install dependencies, tidak masuk ke image final
FROM composer:2.7 AS builder

WORKDIR /app

# Copy file manifest dulu (memanfaatkan layer cache Docker)
COPY composer.json composer.lock ./

# Install dependencies tanpa autoloader, tanpa dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy seluruh kode aplikasi
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# ── Stage 2: Production image ─────────────────────────────
FROM php:8.2-fpm-alpine

LABEL maintainer="nama-anda@email.com"

# Install ekstensi PHP yang dibutuhkan Laravel
RUN apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        opcache

# Konfigurasi OPcache untuk production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.memory_consumption=192'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy hasil build dari stage 1
COPY --from=builder /app /var/www/html

# Set permission yang benar
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
```

### B.2.2 `nginx.conf`

Buat file `nginx.conf` di root project:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        # Gunakan hardcode path, bukan $realpath_root
        # $realpath_root membutuhkan akses filesystem langsung dari container Nginx
        # yang tidak tersedia karena folder public/ hanya ada di container app
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Penjelasan penting:** Nginx dan PHP-FPM berjalan di container yang **terpisah**. Nginx tidak punya akses ke filesystem container `app`, sehingga `$realpath_root` akan gagal resolve path `/var/www/html/public` dan menyebabkan error 404 "File not found". Dengan menggunakan hardcode path `/var/www/html/public`, Nginx cukup meneruskan nama path ke PHP-FPM yang memang sudah punya akses ke folder tersebut.

### B.2.3 `docker-compose.yml` (untuk production di server)

Buat file `docker-compose.yml` di root project:

```yaml
services:
    app:
        image: ${DOCKERHUB_USERNAME}/cicd-laravel:${IMAGE_TAG:-latest}
        container_name: laravel_app
        restart: unless-stopped
        volumes:
            # Mount file .env dari server (tidak ikut ke dalam image)
            - ./.env:/var/www/html/.env:ro
            # Storage persisten (upload file, log, dll)
            - laravel_storage:/var/www/html/storage
        environment:
            - APP_ENV=production
        networks:
            - laravel_network
        depends_on:
            db:
                condition: service_healthy

    nginx:
        image: nginx:1.25-alpine
        container_name: laravel_nginx
        restart: unless-stopped
        ports:
            - "80:80"
        volumes:
            - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
            # Nginx hanya perlu config file — akses ke public/ ditangani lewat
            # FastCGI ke container app, bukan lewat filesystem langsung
        networks:
            - laravel_network
        depends_on:
            - app

    db:
        image: mysql:8.0
        container_name: laravel_db
        restart: unless-stopped
        environment:
            MYSQL_DATABASE: ${DB_DATABASE}
            MYSQL_USER: ${DB_USERNAME}
            MYSQL_PASSWORD: ${DB_PASSWORD}
            MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
        volumes:
            - db_data:/var/lib/mysql
        networks:
            - laravel_network
        healthcheck:
            test:
                [
                    "CMD",
                    "mysqladmin",
                    "ping",
                    "-h",
                    "localhost",
                    "-u",
                    "root",
                    "-p${DB_ROOT_PASSWORD}",
                ]
            interval: 10s
            timeout: 5s
            retries: 5

networks:
    laravel_network:
        driver: bridge

volumes:
    db_data:
    laravel_storage:
```

### B.2.4 `.dockerignore`

Buat file `.dockerignore` di root project untuk mencegah file yang tidak perlu masuk ke image:

```
.git
.github
.gitignore
node_modules
vendor
.env
.env.*
*.log
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
tests/
phpunit.xml
README.md
docker-compose.yml
nginx.conf
Dockerfile
```

---

## B.3 Persiapan Docker Hub

### B.3.1 Buat repository di Docker Hub

1. Login ke [hub.docker.com](https://hub.docker.com)
2. Klik **Create repository**
3. Beri nama `cicd-laravel`
4. Visibility: **Public** (untuk latihan) atau **Private** (jika sudah berlangganan)
5. Klik **Create**

### B.3.2 Test build image secara lokal (opsional)

```bash
# Build image
docker build -t cicd-laravel:test .

# Cek image berhasil dibuat
docker images | grep cicd-laravel
```

---

## B.4 Persiapan Server VPS untuk Docker

### B.4.1 Install Docker di VPS

Login ke VPS, kemudian jalankan:

```bash
# Install Docker Engine
curl -fsSL https://get.docker.com | sh

# Tambahkan user ke group docker (agar tidak perlu sudo setiap saat)
sudo usermod -aG docker $USER

# Aktifkan Docker agar berjalan otomatis saat server restart
sudo systemctl enable docker
sudo systemctl start docker

# Verifikasi
docker --version
docker compose version
```

Logout dan login kembali agar perubahan group berlaku.

### B.4.2 Siapkan direktori aplikasi di server

```bash
# Flag -p memastikan perintah tidak error meski direktori sudah ada
# dan membuat semua direktori induk sekaligus jika belum ada
mkdir -p /opt/cicd-laravel
cd /opt/cicd-laravel
```

> **Penting:** Langkah ini **wajib** dilakukan sebelum menjalankan pipeline. Jika direktori tidak ada, pipeline akan gagal dengan error `cd: /opt/cicd-laravel: No such file or directory` saat job deploy berjalan.

### B.4.3 Buat file `.env` production di server

```bash
nano /opt/cicd-laravel/.env
```

Isi dengan konfigurasi production:

```env
APP_NAME="CICD Laravel"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://IP_SERVER_ANDA

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=cicd_laravel
DB_USERNAME=laravel_user
DB_PASSWORD=password_db_yang_kuat
DB_ROOT_PASSWORD=password_root_yang_kuat

DOCKERHUB_USERNAME=username_dockerhub_anda
```

> **Catatan penting — `DB_HOST=db`:** Nilai `db` mengacu pada nama service di `docker-compose.yml`, bukan `localhost`. Ini karena PHP-FPM dan MySQL berjalan di container yang berbeda dalam satu Docker network. Jika diisi `localhost` atau `127.0.0.1`, koneksi database akan gagal.

> **Catatan — `APP_KEY` masih kosong:** Biarkan kosong dulu. Nilai ini akan di-generate dan diisi secara manual setelah container pertama kali berjalan. Lihat langkah **B.7**.

### B.4.4 Copy file docker-compose.yml dan nginx.conf ke server

Dari mesin lokal, jalankan:

```bash
scp docker-compose.yml nginx.conf USER@IP_SERVER:/opt/cicd-laravel/
```

---

## B.5 Simpan Secrets di GitHub

Buka repository GitHub → **Settings** → **Secrets and variables** → **Actions**.

Tambahkan secrets berikut:

| Name                 | Value                                       |
| -------------------- | ------------------------------------------- |
| `SSH_PRIVATE_KEY`    | Isi private key SSH (sama seperti Bagian A) |
| `SSH_HOST`           | IP address VPS                              |
| `SSH_USER`           | Username Linux di VPS                       |
| `DOCKERHUB_USERNAME` | Username Docker Hub Anda                    |
| `DOCKERHUB_TOKEN`    | Access token Docker Hub (bukan password!)   |

**Cara mendapatkan Docker Hub Access Token:**

1. Login ke Docker Hub → klik nama akun → **Account Settings**
2. Pilih **Security** → **New Access Token**
3. Beri nama token (misal: `github-actions`)
4. Permissions: **Read, Write, Delete**
5. Klik **Generate** dan **copy token-nya sekarang** (tidak bisa dilihat lagi)

---

## B.6 Buat Workflow GitHub Actions untuk Docker

Ganti isi `.github/workflows/deploy.yml` dengan:

```yaml
name: CI/CD Laravel — Docker Deploy

on:
    push:
        branches: [main]
    pull_request:
        branches: [main]

env:
    IMAGE_NAME: ${{ secrets.DOCKERHUB_USERNAME }}/cicd-laravel

jobs:
    # ─────────────────────────────────────────
    # Job 1: Jalankan unit test
    # ─────────────────────────────────────────
    test:
        name: Run Unit Tests
        runs-on: ubuntu-latest

        steps:
            - name: Checkout kode
              uses: actions/checkout@v4

            - name: Setup PHP 8.2
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.2"
                  extensions: mbstring, bcmath, sqlite3
                  coverage: none

            - name: Cache Composer dependencies
              uses: actions/cache@v4
              with:
                  path: vendor
                  key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
                  restore-keys: ${{ runner.os }}-composer-

            - name: Install dependencies
              run: composer install --no-interaction --prefer-dist --no-progress

            - name: Copy .env untuk testing
              run: cp .env.example .env && php artisan key:generate

            - name: Jalankan unit test
              run: php artisan test

    # ─────────────────────────────────────────
    # Job 2: Build dan push Docker image
    #         (hanya jika test lulus dan dari main)
    # ─────────────────────────────────────────
    build-and-push:
        name: Build & Push Docker Image
        needs: test
        runs-on: ubuntu-latest
        if: github.ref == 'refs/heads/main' && github.event_name == 'push'

        outputs:
            image_tag: ${{ steps.set_tag.outputs.tag }}

        steps:
            - name: Checkout kode
              uses: actions/checkout@v4

            - name: Tentukan tag image dari SHA commit
              id: set_tag
              # Gunakan 'cut' untuk memotong 8 karakter pertama SHA
              # Lebih portable dibanding ${GITHUB_SHA::8} yang hanya jalan di bash tertentu
              run: echo "tag=sha-$(echo $GITHUB_SHA | cut -c1-8)" >> $GITHUB_OUTPUT

            - name: Login ke Docker Hub
              uses: docker/login-action@v3
              with:
                  username: ${{ secrets.DOCKERHUB_USERNAME }}
                  password: ${{ secrets.DOCKERHUB_TOKEN }}

            - name: Setup Docker Buildx
              uses: docker/setup-buildx-action@v3

            - name: Build dan push image
              uses: docker/build-push-action@v6
              with:
                  context: .
                  push: true
                  tags: |
                      ${{ env.IMAGE_NAME }}:latest
                      ${{ env.IMAGE_NAME }}:${{ steps.set_tag.outputs.tag }}
                  # Gunakan cache dari image latest sebelumnya untuk mempercepat build
                  cache-from: type=registry,ref=${{ env.IMAGE_NAME }}:latest
                  cache-to: type=inline

    # ─────────────────────────────────────────
    # Job 3: Deploy ke server
    # ─────────────────────────────────────────
    deploy:
        name: Deploy ke Server
        needs: build-and-push
        runs-on: ubuntu-latest

        steps:
            - name: Deploy via SSH
              uses: appleboy/ssh-action@v1.2.0
              with:
                  host: ${{ secrets.SSH_HOST }}
                  username: ${{ secrets.SSH_USER }}
                  key: ${{ secrets.SSH_PRIVATE_KEY }}
                  script: |
                      set -e

                      # Gunakan tag yang sama persis dengan yang di-build
                      # cut -c1-8 menghasilkan nilai yang identik dengan job build-and-push
                      IMAGE_TAG="sha-$(echo "${{ github.sha }}" | cut -c1-8)"
                      DOCKERHUB_USERNAME="${{ secrets.DOCKERHUB_USERNAME }}"

                      echo ">>> Login ke Docker Hub..."
                      echo "${{ secrets.DOCKERHUB_TOKEN }}" | docker login \
                        -u "$DOCKERHUB_USERNAME" --password-stdin

                      echo ">>> Masuk ke direktori aplikasi..."
                      cd /opt/cicd-laravel

                      echo ">>> Pull image terbaru: $IMAGE_TAG"
                      IMAGE_TAG=$IMAGE_TAG DOCKERHUB_USERNAME=$DOCKERHUB_USERNAME \
                        docker compose pull app

                      echo ">>> Jalankan migration di container sementara..."
                      IMAGE_TAG=$IMAGE_TAG DOCKERHUB_USERNAME=$DOCKERHUB_USERNAME \
                        docker compose run --rm app php artisan migrate --force

                      echo ">>> Restart container app dengan image baru..."
                      IMAGE_TAG=$IMAGE_TAG DOCKERHUB_USERNAME=$DOCKERHUB_USERNAME \
                        docker compose up -d --no-deps app

                      echo ">>> Tunggu container siap..."
                      sleep 5

                      echo ">>> Cek status container..."
                      docker compose ps

                      echo ">>> Bersihkan image lama yang tidak terpakai..."
                      docker image prune -f

                      echo ">>> Deploy berhasil! Image: $IMAGE_TAG"
```

---

## B.7 Generate APP_KEY dan Update `.env` di Server

File `.env` di-mount sebagai **read-only** ke dalam container (flag `:ro`), sehingga `php artisan key:generate` tidak bisa menulis langsung ke dalamnya dari dalam container. Gunakan flag `--show` untuk menampilkan key tanpa mencoba menulis ke file:

```bash
cd /opt/cicd-laravel

# Jalankan container sementara hanya untuk generate dan menampilkan key
docker compose run --rm app php artisan key:generate --show
```

Output yang muncul:

```
base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

Copy nilai tersebut, kemudian tulis langsung ke file `.env` di server:

```bash
nano /opt/cicd-laravel/.env
```

Cari baris `APP_KEY=` dan isi dengan nilai yang baru di-copy:

```env
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

Simpan file, kemudian restart container `app` agar membaca ulang `.env`:

```bash
docker compose restart app
```

**Verifikasi key sudah terbaca:**

```bash
docker compose exec app php artisan tinker --execute="echo config('app.key');"
```

Jika output menampilkan nilai `base64:xxx...`, berarti key sudah terbaca dengan benar.

> **Catatan:** Perintah `php artisan about` memang **tidak menampilkan nilai APP_KEY** — ini perilaku normal Laravel sebagai langkah keamanan agar key tidak ter-expose di log atau output terminal. Selama aplikasi bisa diakses tanpa error 500, berarti key sudah benar.

---

## B.8 Deploy Pertama Kali (Bootstrap)

### Pastikan semua file sudah ada di server

Sebelum menjalankan pipeline, verifikasi tiga file ini sudah ada di `/opt/cicd-laravel/`:

```bash
ls -la /opt/cicd-laravel/
```

Harus terlihat minimal:

```
-rw-rw-r-- 1 user user  .env
-rw-r--r-- 1 user user  docker-compose.yml
-rw-r--r-- 1 user user  nginx.conf
```

Jika belum ada, copy dari mesin lokal:

```bash
scp docker-compose.yml nginx.conf USER@IP_SERVER:/opt/cicd-laravel/
```

### Push untuk memicu pipeline

```bash
git add Dockerfile nginx.conf docker-compose.yml .dockerignore
git add .github/workflows/deploy.yml
git commit -m "feat: tambah konfigurasi Docker dan update workflow CI/CD"
git push origin main
```

Pantau jalannya pipeline di tab **Actions** GitHub.

### Jalankan semua service untuk pertama kalinya

Setelah pipeline selesai dan image sudah ada di Docker Hub:

```bash
cd /opt/cicd-laravel

# Jalankan semua service
IMAGE_TAG=latest DOCKERHUB_USERNAME=username_anda docker compose up -d

# Verifikasi semua container berjalan
docker compose ps
```

Output yang diharapkan:

```
NAME              IMAGE                         STATUS          PORTS
laravel_app       username/cicd-laravel:latest  Up 2 minutes    9000/tcp
laravel_nginx     nginx:1.25-alpine             Up 2 minutes    0.0.0.0:80->80/tcp
laravel_db        mysql:8.0                     Up 2 minutes    3306/tcp
```

Jika ada container yang statusnya `Exit` atau `Restarting`, cek log-nya:

```bash
docker compose logs --tail=30 app
docker compose logs --tail=30 nginx
```

### Generate APP_KEY (lanjut ke B.7)

Setelah container berjalan, lanjutkan ke langkah **B.7** untuk mengisi `APP_KEY`.

---

## B.9 Demonstrasi Alur CI/CD Docker (untuk kelas)

### Skenario 1: Perubahan fitur normal ✅

```bash
# Tambahkan field baru pada response
# Edit routes/web.php:
# 'versi' => app()->version()

git add .
git commit -m "feat: tambah versi Laravel di response API"
git push origin main
```

Di tab Actions, perhatikan tiga job berjalan berurutan:

- `Run Unit Tests` → hijau
- `Build & Push Docker Image` → hijau (lihat image baru muncul di Docker Hub)
- `Deploy ke Server` → hijau

Di server, verifikasi image yang berjalan berganti:

```bash
docker inspect laravel_app | grep Image
```

### Skenario 2: Test gagal, deploy tidak terjadi ❌

```bash
# Rusak satu test (sengaja untuk demo)
# Di CalculatorTest.php ubah: assertEquals(999, $result)

git add .
git commit -m "demo: test yang gagal"
git push origin main
```

Di tab Actions: job `test` merah → job `build-and-push` dan `deploy` **tidak muncul sama sekali**. Server tetap menjalankan image lama yang stabil.

---

---

## 7. Perbandingan Kedua Pendekatan

| Aspek                         | Bagian A (Manual)                                  | Bagian B (Docker)                              |
| ----------------------------- | -------------------------------------------------- | ---------------------------------------------- |
| **Kerumitan setup awal**      | Lebih sederhana                                    | Lebih kompleks                                 |
| **Dependensi di server**      | PHP, Composer, Nginx, MySQL harus diinstall manual | Hanya Docker                                   |
| **Konsistensi environment**   | Bisa berbeda antara lokal dan server               | Dijamin sama (sama-sama dari image)            |
| **Rollback**                  | Manual (`git reset` + deploy ulang)                | `docker compose up -d --image TAG_LAMA`        |
| **Skalabilitas**              | Sulit (harus install ulang di tiap server)         | Mudah (pull image yang sama di server manapun) |
| **Cocok untuk**               | Belajar dasar CI/CD, project sederhana             | Industri, microservices, multi-server          |
| **Pemakaian resource server** | Lebih ringan                                       | Sedikit lebih berat (overhead Docker)          |

---

## 8. Troubleshooting — Error yang Sering Ditemui

Bagian ini merangkum error nyata yang umum ditemui saat pertama kali setup CI/CD Laravel dengan Docker, beserta penyebab dan solusinya.

---

### Error 1 — `cd: /opt/cicd-laravel: No such file or directory`

**Muncul di:** Log job `deploy` di GitHub Actions

**Penyebab:** Direktori aplikasi di server belum dibuat sebelum pipeline dijalankan.

**Solusi:** Login ke server dan buat direktori terlebih dahulu:

```bash
mkdir -p /opt/cicd-laravel
```

Pastikan juga file `docker-compose.yml`, `nginx.conf`, dan `.env` sudah ada di direktori tersebut sebelum menjalankan pipeline ulang.

---

### Error 2 — `pull access denied`, `repository does not exist or may require 'docker login'`

**Muncul di:** Log job `deploy` saat menjalankan `docker compose pull`

**Penyebab:** Server belum login ke Docker Hub, sehingga tidak bisa pull image.

**Solusi:** Tambahkan langkah login di awal script deploy pada `deploy.yml`:

```yaml
script: |
    echo "${{ secrets.DOCKERHUB_TOKEN }}" | docker login \
      -u "${{ secrets.DOCKERHUB_USERNAME }}" --password-stdin
    # ... sisa script
```

Flag `--password-stdin` lebih aman karena token tidak muncul di log proses Linux.

---

### Error 3 — `failed to resolve reference "docker.io/.../cicd-laravel:sha-xxxxxxxx"`

**Muncul di:** Log job `deploy` saat pull image

**Penyebab:** Tag image yang dicoba di-pull di server **tidak cocok** dengan tag yang di-push oleh job `build-and-push`. Ini terjadi karena cara memotong SHA commit tidak konsisten antar job.

**Penyebab teknis:** Ekspresi `${GITHUB_SHA::8}` adalah sintaks bash yang tidak selalu berperilaku sama di semua shell. Gunakan `cut` yang lebih portable:

```bash
# Cara yang konsisten di semua job
sha-$(echo "$GITHUB_SHA" | cut -c1-8)
```

**Cara debug:** Cek tag yang benar-benar ada di Docker Hub:

```
https://hub.docker.com/r/USERNAME/cicd-laravel/tags
```

Dan tambahkan sementara step debug di workflow:

```yaml
- name: Debug tag
  run: |
      echo "SHA penuh  : $GITHUB_SHA"
      echo "Tag (cut)  : sha-$(echo $GITHUB_SHA | cut -c1-8)"
```

---

### Error 4 — Nginx menampilkan "File not found" (404)

**Muncul di:** Browser saat mengakses IP server

**Penyebab:** `$realpath_root` di `nginx.conf` mencoba resolve path `/var/www/html/public` dari filesystem container Nginx — padahal folder tersebut hanya ada di container `app`, bukan di container `nginx`.

**Log Nginx yang muncul:**

```
realpath() "/var/www/html/public" failed (2: No such file or directory)
```

**Solusi:** Ganti `$realpath_root` dengan hardcode path di `nginx.conf`:

```nginx
# Sebelum (bermasalah)
fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;

# Sesudah (benar)
fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
```

Setelah mengubah `nginx.conf` di server:

```bash
docker compose restart nginx
```

Jangan lupa update juga file `nginx.conf` di repository lokal agar perubahan ini ikut ke pipeline berikutnya.

---

### Error 5 — Laravel menampilkan Error 500

**Muncul di:** Browser saat mengakses IP server

**Langkah diagnosa pertama — cek log Laravel:**

```bash
docker compose exec app tail -50 /var/www/html/storage/logs/laravel.log
```

**Penyebab paling umum: `APP_KEY` kosong**

Cek dengan:

```bash
docker compose exec app php artisan tinker --execute="echo config('app.key');"
```

Jika output kosong, generate dan isi `APP_KEY` secara manual (lihat langkah B.7).

> **Catatan:** `php artisan about` memang tidak menampilkan nilai `APP_KEY` — ini disengaja oleh Laravel sebagai langkah keamanan. Gunakan `tinker` seperti di atas untuk memverifikasi key sudah terbaca.

---

### Error 6 — `file_put_contents(/var/www/html/.env): Failed to open stream: Read-only file system`

**Muncul di:** Output saat menjalankan `php artisan key:generate` di dalam container

**Penyebab:** File `.env` di-mount dengan flag `:ro` (read-only) sehingga tidak bisa ditulis dari dalam container.

**Solusi:** Gunakan flag `--show` agar key hanya ditampilkan tanpa mencoba menulis ke file:

```bash
docker compose run --rm app php artisan key:generate --show
```

Copy nilai yang muncul, lalu tulis manual ke `/opt/cicd-laravel/.env` di server:

```bash
nano /opt/cicd-laravel/.env
# Isi: APP_KEY=base64:nilai-yang-baru-di-copy
```

Kemudian restart container:

```bash
docker compose restart app
```

---

### Error 7 — Container tidak bisa connect ke database

**Muncul di:** Log Laravel (`SQLSTATE[HY000] [2002] Connection refused`)

**Penyebab paling umum:** Nilai `DB_HOST` di `.env` diisi `localhost` atau `127.0.0.1`.

**Solusi:** Ganti dengan nama service database di `docker-compose.yml`:

```env
DB_HOST=db   # bukan localhost atau 127.0.0.1
```

Di dalam Docker network, antar container berkomunikasi menggunakan nama service-nya, bukan `localhost`.

---

### Cara debug cepat — jalankan semua pengecekan sekaligus

```bash
cd /opt/cicd-laravel

echo "=== Status container ==="
docker compose ps

echo "=== Log Nginx (20 baris terakhir) ==="
docker compose logs --tail=20 nginx

echo "=== Log App (20 baris terakhir) ==="
docker compose logs --tail=20 app

echo "=== Isi direktori server ==="
ls -la /opt/cicd-laravel/

echo "=== Isi /var/www/html di container ==="
docker compose exec app ls -la /var/www/html/

echo "=== APP_KEY terbaca? ==="
docker compose exec app php artisan tinker --execute="echo config('app.key');"
```

---

## Struktur Akhir Project

```
cicd-laravel/
├── .github/
│   └── workflows/
│       └── deploy.yml          ← Workflow CI/CD
├── app/
│   └── Services/
│       └── Calculator.php      ← Service class yang kita buat
├── routes/
│   └── web.php                 ← Route dengan endpoint /hitung
├── tests/
│   └── Unit/
│       └── CalculatorTest.php  ← Unit test (5 test case)
├── .dockerignore               ← (Bagian B)
├── .env.example
├── Dockerfile                  ← (Bagian B)
├── docker-compose.yml          ← (Bagian B, untuk server)
├── nginx.conf                  ← (Bagian B)
└── composer.json
```

---

_Tutorial ini dibuat untuk mata kuliah Workshop Developer Operational_  
_Versi: 1.1 — April 2026_
