# Requirements

What you need to run Pricore without Docker. The [Docker image](/self-hosting/docker) contains all of this.

## Software

| | Version | Notes |
|---|---|---|
| PHP | 8.4 or newer | Use at least 8.4.27 or 8.5.12; older releases have [critical security issues](https://www.php.net/archive/2026.php#2026-10-21-5) |
| Composer | 2 | |
| Node.js | 22 or newer | Only to build the frontend |
| Redis | 6 or newer | |
| Database | SQLite 3, MySQL 8.0+ or PostgreSQL 14+ | |
| Git and OpenSSH client | Any recent version | For Generic Git repositories |

PHP needs these extensions: `pdo` with the driver for your database (`pdo_sqlite`, `pdo_mysql` or `pdo_pgsql`), `mbstring`, `openssl`, `curl`, `xml`, `zip`, `bcmath`, `redis` and `pcntl`.

On Ubuntu or Debian, with the [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) packages:

```bash
sudo apt install git openssh-client redis-server \
  php8.4-{cli,fpm,sqlite3,mysql,pgsql,mbstring,xml,curl,zip,bcmath,redis}
```

A small team fits on 1 CPU and 1 GB of RAM. Disk usage depends on your packages; every version and every branch push adds an archive. See [Dist Archives](/guide/dist-mirroring#cleaning-up) to keep it in check.

## Web server

Point the web server's document root at `public/`.

::: code-group

```caddyfile [FrankenPHP]
{
    frankenphp
}

pricore.example.com {
    root * /var/www/pricore/public
    encode zstd gzip
    php_server
}
```

```nginx [nginx + PHP-FPM]
server {
    listen 80;
    server_name pricore.example.com;
    root /var/www/pricore/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

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

:::

The web server user must be able to write to `storage` and `bootstrap/cache`.

## Background processes

Horizon, the scheduler and Reverb must keep running. With systemd, create one unit per process. This is the unit for Horizon:

```ini
# /etc/systemd/system/pricore-horizon.service
[Unit]
Description=Pricore Horizon
After=network.target redis-server.service

[Service]
User=www-data
WorkingDirectory=/var/www/pricore
ExecStart=/usr/bin/php artisan horizon
Restart=always

[Install]
WantedBy=multi-user.target
```

Create `pricore-reverb.service` the same way, with `ExecStart=/usr/bin/php artisan reverb:start`. For the scheduler, add a cron entry for the same user instead:

```
* * * * * cd /var/www/pricore && php artisan schedule:run >> /dev/null 2>&1
```

Then enable the services:

```bash
sudo systemctl enable --now pricore-horizon pricore-reverb
```

After deploying a new version, run `php artisan horizon:terminate` so Horizon restarts with the new code.
