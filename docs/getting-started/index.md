# Installation

Get Pricore up and running in seconds with Docker, or install manually for development.

## Quick Start with Docker

```bash
# Download the compose file
curl -o docker-compose.yml https://raw.githubusercontent.com/pricorephp/pricore/main/docker-compose.yml

# Start Pricore
docker compose up -d

# Set up your first user and organization
docker compose exec app php artisan pricore:install
```

Pricore will be available at `http://localhost:8000`.

> Everything is handled automatically on first boot: APP_KEY generation, database creation, migrations, and cache warming. See the [Docker deployment guide](/self-hosting/docker) for full details.

## Manual Installation

For development or custom deployments. Requires PHP 8.4+, Node.js 22+, Redis, and SQLite/MySQL/PostgreSQL.

```bash
# Clone the repository
git clone https://github.com/pricorephp/pricore.git
cd pricore

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Start the development server
composer run dev
```

## Next Steps

After installation:

1. [Configure your environment](/getting-started/configuration) - Set up database, Redis, and mail
2. [Create an organization](/guide/organizations) - Set up your first organization
3. [Connect a repository](/guide/repositories) - Link your Git repositories
4. [Create access tokens](/guide/tokens) - Generate tokens for Composer authentication
