# Contributing to Pricore

We welcome contributions! Whether it's a bug fix, new feature, or documentation improvement, your help is appreciated.

## How to Contribute

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests and linting (`composer test && composer run pint`)
5. Commit your changes (`git commit -m 'feat: add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Development Setup

```bash
# Clone the repository
git clone https://github.com/pricorephp/pricore.git
cd pricore

# Install dependencies and set up the project
composer setup

# Create your first user and organization
php artisan pricore:install
```

Registration is invite-only by default, so `pricore:install` is how you get an account
to sign in with. Set `SIGN_UP_ENABLED=true` in `.env` if you would rather register
through the UI.

### Running the Development Server

```bash
# Start all services (server, queue, logs, vite)
composer run dev

# Or with SSR support
composer run dev:ssr
```

### Working in Git Worktrees

To run a second copy of Pricore from a git worktree next to your main checkout:

```bash
git worktree add ../pricore-my-feature -b feature/my-feature
cd ../pricore-my-feature
composer worktree:setup
```

This copies `.env` from the main checkout and gives the worktree its own SQLite
database (migrated and seeded, log in with `test@example.com` / `password`), its own
ports for the web server, Vite and Reverb, and its own Redis, cache, Horizon and
session prefixes, so worktrees don't share queues or sessions. `composer run dev`
then serves it on the printed URL. Run `bin/worktree-setup --force` to start over
with a fresh `.env` and database.

Claude Code sessions run this setup automatically when they start in a worktree.

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

# Lint frontend code
npm run lint

# Type-check TypeScript
npm run types
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

## Development Guidelines

- Follow PSR-12 coding standards (enforced by Pint)
- Write tests for new features
- Update documentation as needed
- Keep commits focused and atomic

## Security

If you discover a security vulnerability, please send an email to **pricore@maartenbode.com** instead of using the issue tracker. All security vulnerabilities will be promptly addressed.
