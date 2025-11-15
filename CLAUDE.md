# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Pricore is an open-source private Composer registry (similar to Repman or Satis) designed to help teams securely manage and distribute their PHP packages. It provides:

- **Private Package Hosting**: Host and serve private Composer packages with fine-grained access control
- **Multi-Provider Git Integration**: Automatically sync packages from GitHub, GitLab, Bitbucket, or generic Git repositories
- **Organization Management**: Multi-tenant architecture with organizations, teams, and role-based access
- **Token-Based Authentication**: Secure API tokens with package-level permissions for Composer clients
- **Automatic Package Discovery**: Webhooks and automated sync to detect new versions from connected repositories
- **Composer-Compatible API**: Drop-in replacement for Packagist with full `composer.json` metadata support

## Development Commands

### Setup
```bash
composer setup  # Install dependencies, copy .env, generate key, run migrations, build frontend
```

### Development Server
```bash
composer run dev  # Runs server, queue, logs (pail), and vite concurrently
composer run dev:ssr  # Same as above but with SSR support via Inertia
```

### Testing
```bash
composer test  # Run all tests (clears config first)
php artisan test  # Run all tests
php artisan test tests/Feature/ExampleTest.php  # Run specific test file
php artisan test --filter=testName  # Run specific test by name
```

### Code Quality
```bash
composer run phpstan  # Run static analysis with Larastan
composer run pint  # Format PHP code with Pint
vendor/bin/pint --dirty  # Format only changed files (required before finalizing changes)
npm run lint  # Lint frontend code with ESLint
npm run format  # Format frontend code with Prettier
npm run format:check  # Check frontend formatting
npm run types  # Type-check TypeScript (noEmit)
```

### Frontend
```bash
npm run dev  # Start Vite dev server
npm run build  # Build for production
npm run build:ssr  # Build with SSR support
```

## Architecture

### Domain Model

Pricore is built around a multi-tenant organization model with the following core entities:

#### Organizations
- Multi-tenant isolation: Each organization has its own packages, repositories, and access tokens
- Ownership model: Organizations have an owner and can have multiple members with different roles
- Identified by unique slug for URL-friendly access

#### Repositories
- Git repository connections supporting multiple providers: `github`, `gitlab`, `bitbucket`, or generic `git`
- Store `repo_identifier` (e.g., "owner/repo" for GitHub) and track sync status
- Track `last_synced_at` and `sync_status` (ok/failed/pending) for monitoring
- Belong to an organization and can be linked to one or more packages

#### Packages
- Composer packages with standard metadata (name, description, type)
- Each package belongs to an organization and is uniquely named within that org
- Can be linked to a repository for automatic syncing or managed manually
- Support `visibility` (private/public) and `is_proxy` flag for proxying external packages
- Package names follow Composer conventions (vendor/package-name)

#### Package Versions
- Store complete `composer.json` metadata for each version as JSON
- Track `version` (e.g., "1.0.0", "dev-main") and `normalized_version` for comparison
- Include `released_at` timestamp for chronological ordering
- Unique constraint per package to prevent duplicate versions

#### Access Tokens
- Organization-scoped or user-scoped tokens for API authentication
- Store hashed tokens (never plain text) with optional expiration
- Support `scopes` for permission management (stored as JSON)
- Track `last_used_at` for auditing and cleanup of unused tokens
- Can grant access to specific packages via `token_package_access` pivot table

#### Organization Members
- Many-to-many relationship between users and organizations
- Role-based access control with `role` field (owner/admin/member/etc.)
- Unique constraint prevents duplicate memberships

### Backend Structure

This is a Laravel 12 application using the streamlined directory structure (no `app/Http/Middleware/` or `app/Console/Kernel.php`). Key architectural patterns:

- **Authentication**: Laravel Fortify provides headless authentication with customizable actions in `app/Actions/Fortify/`
- **Routing**: Type-safe routes via Laravel Wayfinder, which generates TypeScript functions for all Laravel routes
- **Settings Routes**: Settings-related routes are grouped in `routes/settings.php`
- **Middleware & Bootstrap**: Configured in `bootstrap/app.php` instead of separate Kernel files
- **Service Providers**: Application-specific providers in `bootstrap/providers.php`
- **UUID Primary Keys**: All core domain models use UUIDs instead of auto-incrementing IDs for better distributed system support

### Frontend Structure

Modern React 19 + Inertia.js v2 + Tailwind CSS v4 setup with strong typing:

- **Pages**: Inertia pages in `resources/js/pages/` (kebab-case filenames)
- **Components**: Reusable React components in `resources/js/components/`
  - `components/ui/`: shadcn/ui-style components (Radix UI primitives with Tailwind)
  - Application-specific components at root level (e.g., `app-header.tsx`, `nav-main.tsx`)
- **Layouts**: Page layouts in `resources/js/layouts/`
- **Type-Safe Routing**: Wayfinder-generated actions in `resources/js/actions/` and routes in `resources/js/routes/`
- **Utilities**: Helper functions in `resources/js/lib/` (e.g., `cn()` for className merging)
- **Hooks**: Custom React hooks in `resources/js/hooks/`
- **TypeScript Config**: Path alias `@/*` maps to `resources/js/*`

### UI Component Library

This project uses a shadcn/ui-inspired component library built with:
- Radix UI primitives for accessibility
- Tailwind CSS v4 for styling
- class-variance-authority for component variants
- lucide-react for icons

Before creating new components, check `resources/js/components/ui/` for existing primitives.

### Styling Conventions

- **Tailwind v4**: Uses CSS-first configuration with `@import "tailwindcss"` (no `@tailwind` directives)
- **CSS Variables**: Extend theme using `@theme` directive in CSS
- **Spacing**: Always use `gap-*` utilities for list spacing, not margins
- **Class Utility**: Use `cn()` helper from `@/lib/utils` for conditional className merging
- **Dark Mode**: Components support dark mode via `dark:` prefix

### Type Safety & Code Generation

- **Wayfinder**: Auto-generates TypeScript types and functions from Laravel routes
  - Import from `@/actions/App/Http/Controllers/...` for controller methods
  - Import from `@/routes/...` for named routes
  - Supports form helpers: `{...store.form()}` for Inertia forms
  - Run `php artisan wayfinder:generate` after route changes (Vite plugin handles this automatically during dev)

- **TypeScript**: Strict mode enabled with explicit type declarations required

### Development Workflow

1. **Laravel Herd**: Application is served at `https://pricore.test` via Laravel Herd (no manual server setup needed)
2. **Concurrent Dev Tools**: `composer run dev` runs server + queue + logs + vite simultaneously
3. **Hot Reload**: Vite provides HMR for frontend; Laravel dev server restarts automatically
4. **Testing**: Write Pest tests for all features (supports browser testing with Pest v4)

### Inertia.js Features

Leverage modern Inertia v2 capabilities:
- Deferred props for loading states
- Infinite scrolling with merging props
- Prefetching for improved UX
- Polling for real-time updates
- `<Form>` component for form handling with automatic error states

### React Compiler

This project uses the experimental React Compiler (babel-plugin-react-compiler) for automatic optimization. Write idiomatic React without manual memoization.

## Key Files

- `bootstrap/app.php`: Middleware, exceptions, routing configuration
- `bootstrap/providers.php`: Service provider registration
- `routes/web.php`: Main web routes
- `routes/settings.php`: Settings-related routes
- `vite.config.ts`: Vite configuration with React, Tailwind, and Wayfinder plugins
- `tsconfig.json`: TypeScript configuration with `@/*` path alias
- `resources/js/app.tsx`: Frontend entry point
- `resources/js/ssr.tsx`: SSR entry point

## Important Conventions

- **PHP**: Use constructor property promotion, explicit return types, and type hints
- **React**: Components use PascalCase, files use kebab-case
- **Imports**: Use `@/*` alias for all `resources/js/*` imports
- **Forms**: Prefer Inertia `<Form>` component over `useForm` hook when possible
- **Navigation**: Use Inertia `<Link>` or Wayfinder route functions, never plain `<a>` tags
- **Validation**: Always create Form Request classes, never inline validation
- **Database**: Prefer Eloquent relationships over raw queries; eager load to prevent N+1
- **Config**: Never use `env()` outside config files; always use `config()`
- **Testing**: Write Feature tests by default; Unit tests only for isolated logic