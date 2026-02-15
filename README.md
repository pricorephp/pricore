<p align="center"><img src="./.github/logo.svg" width="400" alt="Pricore Logo"></p>

<p align="center">
<a href="https://github.com/pricorephp/pricore/actions/workflows/lint.yml"><img src="https://github.com/pricorephp/pricore/actions/workflows/lint.yml/badge.svg" alt="linter"></a>
<a href="https://github.com/pricorephp/pricore/actions/workflows/tests.yml"><img src="https://github.com/pricorephp/pricore/actions/workflows/tests.yml/badge.svg" alt="tests"></a>
<a href="https://github.com/pricorephp/pricore/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-Apache%202.0-blue.svg" alt="License"></a>
</p>

<p align="center">
<strong>Self-hosted private Composer registry for teams</strong>
</p>

---

## About Pricore

Pricore is an open-source private Composer registry designed to help teams securely manage and distribute their PHP packages. It provides a centralized, reliable way to store package metadata, control access with tokens, and integrate seamlessly with Git-based workflows.

With Pricore, teams gain full ownership of their package ecosystem while keeping dependency management fast, consistent, and transparent.

### Why Pricore?

- **Effortless Setup**: Two commands to go from zero to running — no cloning, no manual configuration
- **Self-hosted**: Keep your packages on your own infrastructure
- **Open Source**: Apache 2.0 licensed, fully transparent, no vendor lock-in
- **Modern Stack**: Built with Laravel 12, React 19, and Inertia.js
- **Production Ready**: Docker support with FrankenPHP for high performance

## Features

- **Private Package Hosting** - Host and serve private Composer packages with fine-grained access control
- **Multi-Provider Git Integration** - Automatically sync packages from GitHub, GitLab, Bitbucket, or generic Git repositories
- **Organization Management** - Multi-tenant architecture with organizations, teams, and role-based access
- **Token-Based Authentication** - Secure API tokens with package-level permissions for Composer clients
- **Automatic Package Discovery** - Webhooks and automated sync to detect new versions from connected repositories
- **Composer-Compatible API** - Drop-in replacement for Packagist with full `composer.json` metadata support
- **Two-Factor Authentication** - Optional 2FA for enhanced account security
- **Queue Dashboard** - Built-in Laravel Horizon for monitoring background jobs

## Quick Start with Docker

Get up and running in seconds — no cloning, no manual key generation, no running migrations:

```bash
# Download the compose file
curl -o docker-compose.yml https://raw.githubusercontent.com/pricorephp/pricore/main/docker-compose.yml

# Start Pricore
docker compose up -d

# Create your first user
docker compose exec app php artisan make:user
```

Pricore will be available at `http://localhost:8000`.

> The entrypoint automatically generates an `APP_KEY`, creates the SQLite database, runs migrations, and caches configuration on first boot. For production, you can provide your own `APP_KEY` via environment variable.

### Docker Services

| Service | Description | Port |
|---------|-------------|------|
| `app` | FrankenPHP web server | 8000 |
| `horizon` | Queue worker with Horizon dashboard | - |
| `scheduler` | Runs scheduled tasks | - |
| `redis` | Cache, sessions, and queues | - |

## Manual Installation

For development or custom deployments. Requires PHP 8.4+, Node.js 22+, Redis, and SQLite/MySQL/PostgreSQL.

```bash
# Clone the repository
git clone https://github.com/pricorephp/pricore.git
cd pricore

# Install dependencies
composer install
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

## Configuration

### Environment Variables

Key configuration options in your `.env` file:

```bash
# Application
APP_NAME=Pricore
APP_ENV=production
APP_URL=https://packages.yourcompany.com

# Database (SQLite, MySQL, or PostgreSQL)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# Redis (required for queues)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

## Using Pricore with Composer

### 1. Create an Access Token

1. Log in to your Pricore instance
2. Navigate to your organization settings
3. Create a new access token with the required scopes

### 2. Configure Composer

Add Pricore as a repository in your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.yourcompany.com/org/your-organization"
        }
    ]
}
```

### 3. Authenticate

```bash
composer config --global --auth http-basic.packages.yourcompany.com token YOUR_ACCESS_TOKEN
```

### 4. Install Packages

```bash
composer require your-vendor/your-private-package
```

## Development

### Running the Development Server

```bash
# Start all services (server, queue, logs, vite)
composer run dev

# Or with SSR support
composer run dev:ssr
```

### Code Quality

```bash
# Run tests
composer test

# Static analysis
composer run phpstan

# Format PHP code
composer run pint

# Format frontend code
npm run format

# Type-check TypeScript
npm run types
```

### Project Structure

```
app/
├── Domains/           # Domain-driven design modules
│   └── Repository/    # Git repository syncing logic
├── Http/Controllers/  # HTTP controllers
├── Models/            # Eloquent models
└── Services/          # Application services

resources/
├── js/
│   ├── components/    # React components
│   ├── layouts/       # Page layouts
│   └── pages/         # Inertia pages
└── views/             # Blade templates (minimal)
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests and linting (`composer test && composer run pint`)
5. Commit your changes (`git commit -m 'feat: add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards (enforced by Pint)
- Write tests for new features
- Update documentation as needed
- Keep commits focused and atomic

## Security

If you discover a security vulnerability, please send an email to pricore@maartenbode.com instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## License

Pricore is open-source software licensed under the [Apache License 2.0](LICENSE).

## Acknowledgments

Pricore is built on the shoulders of giants:

- [Laravel](https://laravel.com) - The PHP framework
- [Inertia.js](https://inertiajs.com) - Modern monolith approach
- [React](https://react.dev) - UI library
- [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS
- [FrankenPHP](https://frankenphp.dev) - Modern PHP app server

---

<p align="center">
Made with 💚 for the PHP community
</p>
