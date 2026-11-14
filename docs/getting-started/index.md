# Installation

## Docker

The Docker image is the recommended way to run Pricore. You only need the compose file:

```bash
curl -o docker-compose.yml https://raw.githubusercontent.com/pricorephp/pricore/main/docker-compose.yml
docker compose up -d
```

The first boot generates an `APP_KEY`, creates a SQLite database and runs the migrations. Once the `app` container is healthy, create your account and first organization:

```bash
docker compose exec app php artisan pricore:install
```

Pricore is now running at `http://localhost:8000`. Before you put it on a real domain, read the [Docker guide](/self-hosting/docker): you'll want to set `APP_URL`, provide your own `APP_KEY` and put a reverse proxy with TLS in front of it.

## From source

To work on Pricore itself, or to run it without Docker, you need PHP 8.4, Node.js 22, Composer and Redis. See [Requirements](/self-hosting/requirements) for the full list.

```bash
git clone https://github.com/pricorephp/pricore.git
cd pricore
composer setup
php artisan pricore:install
composer run dev
```

`composer setup` installs the PHP and Node dependencies, creates `.env`, generates the application and OAuth keys, runs the migrations and builds the frontend. `composer run dev` starts the web server, Horizon, Reverb and Vite together.

## First steps

1. [Connect a repository](/guide/repositories). Pricore reads `composer.json` from every tag and branch and creates the package.
2. [Create an access token](/guide/tokens) under **Organization Settings** > **Access Tokens**. The dialog shows the Composer commands to use it.
3. [Invite your team](/guide/organizations#members). Sign-up is invite-only by default.
