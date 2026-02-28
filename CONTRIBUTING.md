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
```

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
