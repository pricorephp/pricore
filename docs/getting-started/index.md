# Installation

Get Pricore up and running in minutes with Docker or manual installation.

## Quick Start with Docker

The fastest way to get started is with Docker:

```bash
# Clone the repository
git clone https://github.com/pricorephp/pricore.git
cd pricore

# Copy environment file and set your APP_KEY
cp .env.example .env

# Generate a key
php -r "echo 'base64:'.base64_encode(random_bytes(32));"
# Add this key to your .env file as APP_KEY

# Start the containers
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Create your first user
docker compose exec app php artisan make:user
```

Pricore will be available at `http://localhost:8000`.

## Manual Installation

For development or custom deployments:

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

## Requirements

- PHP 8.4+
- Node.js 22+
- Redis (for queues and caching)
- SQLite, MySQL, or PostgreSQL

## Next Steps

After installation:

1. [Configure your environment](/getting-started/configuration) - Set up database, Redis, and mail
2. [Create an organization](/guide/organizations) - Set up your first organization
3. [Connect a repository](/guide/repositories) - Link your Git repositories
4. [Create access tokens](/guide/tokens) - Generate tokens for Composer authentication
