# AGENTS.md

This file is for coding agents operating in the Pricore repository.

## Project Summary

Pricore is a Laravel 13 application for hosting a private Composer registry.
It uses Inertia.js with React and TypeScript for the frontend.
Core domains include organizations, repositories, packages, mirrors, tokens, and security advisories.

## Stack

- PHP 8.4
- Laravel 13
- Pest + PHPUnit
- Larastan / PHPStan
- Laravel Pint
- React 19
- TypeScript
- Vite
- ESLint + Prettier
- Tailwind CSS 4

## Important Repo Guidance

- No `.cursor/rules/`, `.cursorrules`, or `.github/copilot-instructions.md` files were found in this repository.
- Follow existing architecture before introducing new abstractions.
- Prefer the smallest correct change.
- Do not add compatibility layers unless there is a concrete need.

## Setup And Dev Commands

- Initial setup: `composer setup`
- Install PHP dependencies only: `composer install`
- Install frontend dependencies only: `npm install`
- Start full local dev stack: `composer run dev`
- Start full dev stack with SSR: `composer run dev:ssr`
- Frontend dev server only: `npm run dev`
- Production frontend build: `npm run build`
- Production frontend SSR build: `npm run build:ssr`
- Generate TypeScript types from PHP: `composer run ts`

## Test Commands

- Run all tests: `composer test`
- Run all tests directly: `php artisan test`
- Run a single test file: `php artisan test tests/Feature/Composer/ComposerApiTest.php`
- Run a single test by name: `php artisan test --filter=test_name_here`
- Run a Pest file directly: `vendor/bin/pest tests/Unit/ComposerMetadataParserTest.php`
- Run a specific Pest test by filter: `vendor/bin/pest --filter="parses valid composer.json"`
- Run only unit tests: `php artisan test --testsuite=Unit`
- Run only feature tests: `php artisan test --testsuite=Feature`

## Test Environment Notes

- Tests run with `APP_ENV=testing`.
- Tests use in-memory SQLite: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
- Queue, cache, session, and mail use in-memory or sync drivers during tests.
- `tests/Pest.php` applies `RefreshDatabase` to Feature tests.
- Prefer adding or updating focused tests near the changed behavior.

## Lint / Format / Static Analysis

- Format PHP: `composer run pint`
- Format only changed PHP files: `vendor/bin/pint --dirty`
- Run static analysis: `composer run phpstan`
- Run frontend lint with autofix: `npm run lint`
- Format frontend files: `npm run format`
- Check frontend formatting: `npm run format:check`
- Run TypeScript type checking: `npm run types`

## Docs Commands

- Docs dev server: `npm run docs:dev`
- Docs build: `npm run docs:build`
- Docs preview: `npm run docs:preview`

## File Layout And Architecture

- Use domain directories under `app/Domains/{DomainName}` for non-trivial business logic.
- Keep simple CRUD logic in normal controllers when that is already the established pattern.
- Routes are defined in `routes/*.php`, with settings routes in `routes/settings.php`.
- Laravel bootstrap, middleware, exceptions, and command registration live in `bootstrap/app.php`.
- Commands are registered by directory, not through a legacy kernel file.
- Frontend source lives in `resources/js`.
- Shared generated TypeScript declarations live in `resources/types`.

## PHP Style Guidelines

- Use strict typing via explicit parameter, property, and return types everywhere practical.
- Prefer constructor property promotion.
- Prefer modern PHP 8.3+ syntax such as `match` expressions.
- Use named arguments when they improve readability.
- Prefer DTOs over raw arrays for structured data.
- Keep one public `handle()` method in action classes.
- Inject dependencies through constructors instead of resolving them inline.
- Return explicit domain types, enums, DTOs, or clearly documented strings.
- Use short, value-adding docblocks only when types or behavior are non-obvious.
- Avoid redundant docblocks that restate the signature.
- Add inline comments only when logic is genuinely hard to follow.

## PHP Naming Conventions

- Classes: PascalCase.
- Enums: backed string enums with PascalCase cases.
- Enums should usually expose `label()` and `options()` methods.
- Action classes should be verb-based, e.g. `CreateMirrorSyncLogAction`.
- Exceptions should end with `Exception`.
- Interfaces should end with `Interface`.
- DTOs belong in `Contracts/Data` and usually extend `Spatie\LaravelData\Data`.

## Laravel And Domain Patterns

- Follow interface-first design where a service contract already exists.
- Use factories for complex object creation when the repo already uses them.
- Use domain exceptions for domain failures.
- Prefer Eloquent relationships and scopes over repeating query logic.
- Respect UUID primary keys used across core models.
- When adding model relationships or scopes, keep generic types and PHPDoc accurate for Larastan.
- If returning arrays with complex shapes, document the array shape in PHPDoc.

## Error Handling Guidelines

- Fail with domain-specific exceptions for domain errors.
- Log operational failures with useful structured context.
- Return HTTP responses that match existing controller behavior.
- Preserve current behavior for JSON vs Inertia error responses.
- Do not swallow exceptions silently unless cancellation or abort behavior is intentional.
- In frontend async code, handle `AbortError` separately when using `AbortController`.

## Frontend Style Guidelines

- Use TypeScript with strict types; avoid introducing `any`.
- Prefer local interfaces or type aliases for page props.
- Use the `@/` alias for frontend imports.
- Let Prettier organize imports; do not hand-format import order unnecessarily.
- Keep React components functional and follow existing file-local helper patterns.
- Do not add `useMemo` or `useCallback` by default unless there is a clear need or surrounding code already uses them.
- Preserve the existing UI patterns and design language instead of inventing a parallel system.
- Prefer existing UI components from `@/components/ui/*` before creating new ones.
- Use Inertia `Form`, `Link`, `Head`, and generated route/action helpers where the codebase already does.

## Frontend Naming And Structure

- React components: PascalCase.
- Hooks: `use...` naming.
- Utility functions: concise verb-based camelCase names.
- Keep helper functions near the component when they are only used once file-local.
- Reuse generated app types such as `App.Domains...` when available.
- Prefer explicit return types on exported utility functions when helpful.

## Formatting Rules

- `.editorconfig` sets LF endings, UTF-8, final newline, and 4-space indentation by default.
- YAML uses 2-space indentation.
- Markdown allows trailing whitespace preservation.
- Frontend formatting is enforced by Prettier.
- ESLint uses React, React Hooks, TypeScript, and Prettier flat configs.
- TypeScript uses `strict`, `noImplicitAny`, `isolatedModules`, `forceConsistentCasingInFileNames`, and `jsx: react-jsx`.
- PHPStan runs at level 8 against `app/`.

## Testing Conventions

- Tests are written in Pest style.
- Prefer `it(...)` blocks with descriptive behavior-focused names.
- Use factories for setup.
- Fake HTTP, bus, queues, or events when isolating behavior.
- Add assertions for both happy path and failure path when changing domain logic.
- When touching query scopes or version ordering, include tests for edge cases and database differences when relevant.

## Agent Workflow Expectations

- Inspect the relevant files before editing.
- Match existing conventions in the touched area.
- Do not refactor unrelated code.
- Do not introduce new dependencies without clear justification.
- Run the narrowest useful verification first, then broader checks if needed.
- For PHP changes, typically run the most relevant `php artisan test` target and `vendor/bin/pint --dirty`.
- For frontend changes, typically run `npm run types`, `npm run lint`, and `npm run format:check` when the change warrants it.
- If you add or rename backend DTOs or enums that feed the frontend, consider `composer run ts`.

## Git Safety

- The worktree may already contain user changes; do not revert unrelated work.
- Never use destructive git commands unless explicitly requested.
- Do not amend commits unless explicitly requested.
- Do not commit or push unless explicitly requested.

## Good Defaults For Agents

- Prefer minimal edits.
- Prefer explicit types.
- Prefer domain actions for multi-step business logic.
- Prefer focused tests.
- Prefer consistency with surrounding code over personal style.
