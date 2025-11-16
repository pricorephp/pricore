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

### Code Architecture & Patterns

This project follows a **Domain-Driven Design** approach with specific architectural patterns:

#### Domain Organization

Complex business logic is organized into **domain directories** under `app/Domains/{DomainName}/`:

```
app/Domains/Repository/
├── Actions/              # Single-purpose action classes
├── Commands/             # Artisan console commands
├── Contracts/
│   ├── Data/            # Data Transfer Objects (DTOs)
│   └── Enums/           # Backed enums with helper methods
├── Exceptions/          # Domain-specific exceptions
└── Jobs/                # Queueable jobs
```

**When to use domains:**
- Use domains for complex, multi-step business logic (like repository syncing)
- Keep simple CRUD operations in standard controllers
- Each domain should represent a cohesive bounded context

#### Enums (Strongly Typed)

All enums use **backed string enums** with helper methods:

```php
enum GitProvider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
        };
    }

    public static function options(): array
    {
        return [
            self::GitHub->value => self::GitHub->label(),
            self::GitLab->value => self::GitLab->label(),
        ];
    }
}
```

**Enum conventions:**
- Use PascalCase for case names (e.g., `GitProvider::GitHub`)
- Always include a `label()` method for human-readable names
- Add `options()` static method for select dropdowns
- Add convenience boolean methods (e.g., `isSuccess()`, `isFailed()`)
- Store enum locations in `app/Domains/{Domain}/Contracts/Enums/`

#### Data Transfer Objects (Spatie Laravel Data)

Use [Spatie Laravel Data](https://spatie.be/docs/laravel-data) for typed data structures:

```php
use Spatie\LaravelData\Data;

class RefData extends Data
{
    public function __construct(
        public string $name,
        public string $commit,
    ) {}
}

class SyncResultData extends Data
{
    public function __construct(
        public int $added,
        public int $updated,
        public int $skipped,
    ) {}

    public function total(): int
    {
        return $this->added + $this->updated + $this->skipped;
    }

    public function hasChanges(): bool
    {
        return $this->added > 0 || $this->updated > 0;
    }
}
```

**DTO conventions:**
- Use constructor property promotion for all properties
- Add computed methods for derived values (e.g., `total()`, `hasChanges()`)
- Use `DataCollection` for collections of DTOs
- Store in `app/Domains/{Domain}/Contracts/Data/`
- Prefer DTOs over arrays for complex data structures

#### Action Classes

**Single-purpose action classes** encapsulate business logic:

```php
class SyncRefAction
{
    public function __construct(
        protected FindOrCreatePackageAction $findOrCreatePackage
    ) {}

    /**
     * Sync a single ref (tag or branch).
     *
     * @return string added|updated|skipped
     */
    public function handle(
        GitProviderInterface $provider,
        Repository $repository,
        RefData $ref
    ): string {
        // Business logic here
    }
}
```

**Action conventions:**
- One public method: `handle()` with explicit parameters
- Use constructor injection for dependencies (including other actions)
- Return explicit types (DTOs, enums, strings with doc comments)
- Name actions with verb suffixes: `CreateSyncLogAction`, `CollectRefsAction`
- Store in `app/Domains/{Domain}/Actions/`
- Keep actions focused on a single responsibility

#### Custom Exceptions

Create **domain-specific exceptions** for clear error handling:

```php
namespace App\Domains\Repository\Exceptions;

use Exception;

class GitProviderException extends Exception
{
    //
}
```

**Exception conventions:**
- Extend base `Exception` class
- Name with `Exception` suffix
- Keep simple unless custom behavior needed
- Store in `app/Domains/{Domain}/Exceptions/`

#### Factory Pattern

Use **static factory methods** for complex object creation:

```php
class GitProviderFactory
{
    public static function make(Repository $repository): GitProviderInterface
    {
        $credentials = static::getCredentials($repository);

        return match ($repository->provider) {
            GitProvider::GitHub => new GitHubProvider($repository->repo_identifier, $credentials),
            GitProvider::GitLab => new GitLabProvider($repository->repo_identifier, $credentials),
        };
    }
}
```

**Factory conventions:**
- Use `static` over `self` for better inheritance
- Use `match` expressions for type-safe branching
- Return interface types, not concrete implementations
- Store in `app/Services/{ServiceName}/`

#### Interface-First Design

Define **clean contracts** before implementations:

```php
interface GitProviderInterface
{
    /**
     * Get all tags from the repository.
     *
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array;

    public function getBranches(): array;
    public function getFileContent(string $ref, string $path): ?string;
    public function validateCredentials(): bool;
}
```

**Interface conventions:**
- Store contracts in `app/Services/{Service}/Contracts/` or `app/Domains/{Domain}/Contracts/`
- Use PHPDoc array shapes for complex return types
- Name with `Interface` suffix
- Define minimal, focused contracts

#### Modern PHP Patterns

Use modern PHP 8.3+ features consistently:

- **Match expressions** instead of switch statements
- **Constructor property promotion** everywhere
- **Explicit return types** on all methods
- **Typed properties** on all class properties
- **Array shapes in PHPDoc** for array return types
- **Named arguments** for better readability

#### Comments & Documentation

**Avoid redundant comments and docblocks.** Code should be self-documenting through clear naming and types.

**When NOT to add docblocks:**
```php
// ❌ BAD - Redundant docblock
/**
 * Get the user's name.
 *
 * @return string
 */
public function getName(): string
{
    return $this->name;
}

// ✅ GOOD - Type hint is sufficient
public function getName(): string
{
    return $this->name;
}
```

**When TO add docblocks:**
```php
// ✅ GOOD - Complex array shape needs documentation
/**
 * @return array<int, array{name: string, commit: string}>
 */
public function getTags(): array
{
    return $this->tags;
}

// ✅ GOOD - Business logic explanation adds value
/**
 * Sync a single ref (tag or branch).
 *
 * @return string added|updated|skipped
 */
public function handle(GitProviderInterface $provider, Repository $repository, RefData $ref): string
{
    // Implementation
}
```

**Docblock guidelines:**
- Only add docblocks when they provide **non-obvious information**
- Use docblocks for complex array shapes, union types, or return values that need clarification
- Avoid repeating what the method signature already tells you
- Never add inline comments unless the logic is genuinely complex
- Let descriptive method and variable names speak for themselves

#### Laravel Prompts

Use [Laravel Prompts](https://laravel.com/docs/prompts) for beautiful CLI interactions:

```php
use function Laravel\Prompts\spin;

spin(
    callback: fn() => SyncRepositoryJob::dispatchSync($repository),
    message: 'Dispatching sync jobs...',
);
```

**Prompt conventions:**
- Import prompt functions individually (e.g., `use function Laravel\Prompts\spin;`)
- Use named arguments for clarity
- Available functions: `spin()`, `text()`, `select()`, `confirm()`, `progress()`

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.8
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== wayfinder/core rules ===

## Laravel Wayfinder

Wayfinder generates TypeScript functions and types for Laravel controllers and routes which you can import into your client side code. It provides type safety and automatic synchronization between backend routes and frontend code.

### Development Guidelines
- Always use `search-docs` to check wayfinder correct usage before implementing any features.
- Always Prefer named imports for tree-shaking (e.g., `import { show } from '@/actions/...'`)
- Avoid default controller imports (prevents tree-shaking)
- Run `php artisan wayfinder:generate` after route changes if Vite plugin isn't installed

### Feature Overview
- Form Support: Use `.form()` with `--with-form` flag for HTML form attributes — `<form {...store.form()}>` → `action="/posts" method="post"`
- HTTP Methods: Call `.get()`, `.post()`, `.patch()`, `.put()`, `.delete()` for specific methods — `show.head(1)` → `{ url: "/posts/1", method: "head" }`
- Invokable Controllers: Import and invoke directly as functions. For example, `import StorePost from '@/actions/.../StorePostController'; StorePost()`
- Named Routes: Import from `@/routes/` for non-controller routes. For example, `import { show } from '@/routes/post'; show(1)` for route name `post.show`
- Parameter Binding: Detects route keys (e.g., `{post:slug}`) and accepts matching object properties — `show("my-post")` or `show({ slug: "my-post" })`
- Query Merging: Use `mergeQuery` to merge with `window.location.search`, set values to `null` to remove — `show(1, { mergeQuery: { page: 2, sort: null } })`
- Query Parameters: Pass `{ query: {...} }` in options to append params — `show(1, { query: { page: 1 } })` → `"/posts/1?page=1"`
- Route Objects: Functions return `{ url, method }` shaped objects — `show(1)` → `{ url: "/posts/1", method: "get" }`
- URL Extraction: Use `.url()` to get URL string — `show.url(1)` → `"/posts/1"`

### Example Usage

<code-snippet name="Wayfinder Basic Usage" lang="typescript">
    // Import controller methods (tree-shakable)
    import { show, store, update } from '@/actions/App/Http/Controllers/PostController'

    // Get route object with URL and method...
    show(1) // { url: "/posts/1", method: "get" }

    // Get just the URL...
    show.url(1) // "/posts/1"

    // Use specific HTTP methods...
    show.get(1) // { url: "/posts/1", method: "get" }
    show.head(1) // { url: "/posts/1", method: "head" }

    // Import named routes...
    import { show as postShow } from '@/routes/post' // For route name 'post.show'
    postShow(1) // { url: "/posts/1", method: "get" }
</code-snippet>


### Wayfinder + Inertia
If your application uses the `<Form>` component from Inertia, you can use Wayfinder to generate form action and method automatically.
<code-snippet name="Wayfinder Form Component (React)" lang="typescript">

<Form {...store.form()}><input name="title" /></Form>

</code-snippet>

### Common Patterns & Gotchas

#### Multiple Route Parameters
When your route has multiple parameters (e.g., `organizations/{organization}/members/{member}`), you **must** pass them as an array to Wayfinder functions.

<code-snippet name="Multiple Route Parameters" lang="typescript">
// routes/web.php
Route::patch('organizations/{organization}/members/{member}', [MemberController::class, 'update']);

// ❌ WRONG - Will cause "Cannot read properties of undefined (reading 'toString')"
update.url(organization, memberUuid)
update.url(organization.slug, memberUuid)
update.url(organization, { member: memberUuid })

// ✅ CORRECT - Use array for multiple parameters
update.url([organization.slug, memberUuid])

// Also works with router methods
router.patch(update.url([organization.slug, memberUuid]), { role: 'admin' })
router.delete(destroy.url([organization.slug, memberUuid]))
</code-snippet>

#### Form Helper Availability
Not all Wayfinder actions have the `.form()` helper method. When it's unavailable, use explicit `action` and `method` props instead.

<code-snippet name="Form Helper Alternatives" lang="typescript">
// ❌ WRONG - May cause "form is not a function" error
<Form {...store.form(organization)}>
<Form {...update.form([organization.slug])}>

// ✅ CORRECT - Use explicit action and method props
<Form action={store.url(organization.slug)} method="post">
    <input name="email" type="email" />
    <button type="submit">Add</button>
</Form>

<Form action={update.url([organization.slug])} method="patch">
    <input name="name" defaultValue={organization.name} />
    <button type="submit">Save</button>
</Form>
</code-snippet>

#### Route Parameter Order
Parameters must be passed in the same order as they appear in the route definition.

<code-snippet name="Parameter Order Matters" lang="typescript">
// routes/web.php
Route::get('organizations/{organization}/packages/{package}', [PackageController::class, 'show']);

// ✅ CORRECT - Parameters in route order: organization, then package
show.url([organizationSlug, packageId])

// ❌ WRONG - Parameters reversed
show.url([packageId, organizationSlug]) // Will generate incorrect URL
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== inertia-react/core rules ===

## Inertia + React

- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="react">

import { Link } from '@inertiajs/react'
<Link href="/">Home</Link>

</code-snippet>


=== inertia-react/v2/forms rules ===

## Inertia + React Forms

<code-snippet name="`<Form>` Component Example" lang="react">

import { Form } from '@inertiajs/react'

export default () => (
    <Form action="/users" method="post">
        {({
            errors,
            hasErrors,
            processing,
            wasSuccessful,
            recentlySuccessful,
            clearErrors,
            resetAndClearErrors,
            defaults
        }) => (
        <>
        <input type="text" name="name" />

        {errors.name && <div>{errors.name}</div>}

        <button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
        </button>

        {wasSuccessful && <div>User created successfully!</div>}
        </>
    )}
    </Form>
)

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== laravel/fortify rules ===

## Laravel Fortify

Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.

**Before implementing any authentication features, use the `search-docs` tool to get the latest docs for that specific feature.**

### Configuration & Setup
- Check `config/fortify.php` to see what's enabled. Use `search-docs` for detailed information on specific features.
- Enable features by adding them to the `'features' => []` array: `Features::registration()`, `Features::resetPasswords()`, etc.
- To see the all Fortify registered routes, use the `list-routes` tool with the `only_vendor: true` and `action: "Fortify"` parameters.
- Fortify includes view routes by default (login, register). Set `'views' => false` in the configuration file to disable them if you're handling views yourself.

### Customization
- Views can be customized in `FortifyServiceProvider`'s `boot()` method using `Fortify::loginView()`, `Fortify::registerView()`, etc.
- Customize authentication logic with `Fortify::authenticateUsing()` for custom user retrieval / validation.
- Actions in `app/Actions/Fortify/` handle business logic (user creation, password reset, etc.). They're fully customizable, so you can modify them to change feature behavior.

## Available Features
- `Features::registration()` for user registration.
- `Features::emailVerification()` to verify new user emails.
- `Features::twoFactorAuthentication()` for 2FA with QR codes and recovery codes.
  - Add options: `['confirmPassword' => true, 'confirm' => true]` to require password confirmation and OTP confirmation before enabling 2FA.
- `Features::updateProfileInformation()` to let users update their profile.
- `Features::updatePasswords()` to let users change their passwords.
- `Features::resetPasswords()` for password reset via email.
</laravel-boost-guidelines>
