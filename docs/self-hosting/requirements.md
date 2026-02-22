# System Requirements

Requirements for running Pricore without Docker.

## Server Requirements

### PHP 8.4+

Pricore requires PHP 8.4 or higher with the following extensions:

| Extension | Purpose |
|-----------|---------|
| `pdo` | Database abstraction |
| `pdo_sqlite` / `pdo_mysql` / `pdo_pgsql` | Database driver |
| `mbstring` | String handling |
| `openssl` | Encryption |
| `json` | JSON parsing |
| `curl` | HTTP client |
| `xml` | XML parsing |
| `zip` | Archive handling |
| `bcmath` | Arbitrary precision math |
| `redis` | Redis connection |
| `pcntl` | Process control (for Horizon) |

### Install on Ubuntu/Debian

```bash
sudo apt update
sudo apt install php8.4 php8.4-{cli,fpm,common,mysql,pgsql,sqlite3,mbstring,xml,curl,zip,bcmath,redis}
```

### Install on macOS (Homebrew)

```bash
brew install php@8.4
pecl install redis
```

## Node.js 22+

Required for building frontend assets:

```bash
# Using nvm
nvm install 22
nvm use 22

# Using apt
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## Composer

PHP package manager:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

## Redis

Required for queues, caching, and sessions:

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# macOS
brew install redis
brew services start redis
```

Verify Redis is running:

```bash
redis-cli ping
# Should return: PONG
```

## Database

Choose one of the following:

### SQLite

No additional installation needed. Create the database file:

```bash
touch database/database.sqlite
```

### MySQL 8.0+

```bash
# Ubuntu/Debian
sudo apt install mysql-server
sudo mysql_secure_installation

# Create database
mysql -u root -p
CREATE DATABASE pricore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pricore'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON pricore.* TO 'pricore'@'localhost';
FLUSH PRIVILEGES;
```

### PostgreSQL 14+

```bash
# Ubuntu/Debian
sudo apt install postgresql postgresql-contrib

# Create database
sudo -u postgres psql
CREATE DATABASE pricore;
CREATE USER pricore WITH PASSWORD 'your-password';
GRANT ALL PRIVILEGES ON DATABASE pricore TO pricore;
```

## Web Server

### FrankenPHP (Recommended)

Download and install:

```bash
# Download
curl -L https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
chmod +x frankenphp
sudo mv frankenphp /usr/local/bin/

# Create Caddyfile
cat > Caddyfile << 'EOF'
{
    frankenphp
}

:8000 {
    root * /var/www/pricore/public
    encode zstd gzip
    php_server
}
EOF

# Start
frankenphp run
```

### Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name pricore.yourcompany.com;
    root /var/www/pricore/public;

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
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Process Manager

For running Horizon and the scheduler in production:

### Systemd

**Horizon service** (`/etc/systemd/system/pricore-horizon.service`):

```ini
[Unit]
Description=Pricore Horizon
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/pricore
ExecStart=/usr/bin/php artisan horizon
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

**Scheduler service** (`/etc/systemd/system/pricore-scheduler.service`):

```ini
[Unit]
Description=Pricore Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/pricore
ExecStart=/bin/bash -c "while true; do /usr/bin/php artisan schedule:run --verbose --no-interaction; sleep 60; done"
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable pricore-horizon pricore-scheduler
sudo systemctl start pricore-horizon pricore-scheduler
```

### Supervisor

**Horizon** (`/etc/supervisor/conf.d/pricore-horizon.conf`):

```ini
[program:pricore-horizon]
process_name=%(program_name)s
command=php /var/www/pricore/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/pricore/storage/logs/horizon.log
stopwaitsecs=3600
```

**Scheduler** (`/etc/supervisor/conf.d/pricore-scheduler.conf`):

```ini
[program:pricore-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php /var/www/pricore/artisan schedule:run; sleep 60; done"
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/pricore/storage/logs/scheduler.log
```

## File Permissions

Set proper permissions:

```bash
cd /var/www/pricore

# Set ownership
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Make storage and cache writable
sudo chmod -R 775 storage bootstrap/cache
```

## Minimum Hardware

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 1 core | 2+ cores |
| RAM | 1 GB | 2+ GB |
| Storage | 10 GB | 20+ GB (depends on packages) |

## Checklist

Before installation, ensure you have:

- [ ] PHP 8.4+ with required extensions
- [ ] Node.js 22+
- [ ] Composer
- [ ] Redis running
- [ ] Database configured
- [ ] Web server installed
- [ ] Process manager configured
- [ ] Proper file permissions
