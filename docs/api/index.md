# API Reference

Pricore provides a Composer-compatible API for package distribution and webhook endpoints for automatic syncing.

## Authentication

The Composer API requires authentication via access token. Two methods are supported:

### HTTP Basic Auth

Use your access token as the username with an empty password:

```bash
curl -u "YOUR_ACCESS_TOKEN:" https://pricore.yourcompany.com/your-org/packages.json
```

### Authorization Header

Alternatively, use the Bearer token format:

```bash
curl -H "Authorization: Bearer YOUR_ACCESS_TOKEN" https://pricore.yourcompany.com/your-org/packages.json
```

## Composer Repository API

These endpoints implement the [Composer v2 Repository](https://getcomposer.org/doc/05-repositories.md#composer) specification.

### Get Package List

Returns the root configuration for an organization's Composer repository.

```
GET /{organization}/packages.json
```

**Response:**

```json
{
    "metadata-url": "https://pricore.yourcompany.com/your-org/p2/%package%.json",
    "available-packages": ["acme/billing", "acme/utils"],
    "notify-batch": "https://pricore.yourcompany.com/your-org/notify-batch"
}
```

| Field | Description |
|-------|-------------|
| `metadata-url` | URL template Composer uses to resolve individual package metadata |
| `available-packages` | Exhaustive list of packages in this repository — lets Composer skip unnecessary metadata lookups for packages that don't exist here |
| `notify-batch` | URL where Composer sends download notifications after installing packages |

Responses include caching headers (`Cache-Control`, `ETag`, `Last-Modified`). Clients can send `If-None-Match` with a previously received `ETag` to receive a `304 Not Modified` response when content hasn't changed.

### Get Package Metadata (Stable)

Returns metadata for all stable versions of a specific package.

```
GET /{organization}/p2/{vendor}/{package}.json
```

**Response:**

```json
{
    "packages": {
        "vendor/package-name": [
            {
                "name": "vendor/package-name",
                "version": "1.0.0",
                "version_normalized": "1.0.0.0",
                "type": "library",
                "require": {
                    "php": "^8.2"
                },
                "autoload": {
                    "psr-4": {
                        "Vendor\\Package\\": "src/"
                    }
                }
            }
        ]
    },
    "minified": "composer/2.0"
}
```

Responses use **minified metadata** (`"minified": "composer/2.0"`) per the Composer 2 spec — only the first version entry contains all keys; subsequent entries omit keys whose values match the previous entry, reducing payload size.

Responses include caching headers (`Cache-Control`, `ETag`, `Last-Modified`). Clients can send `If-None-Match` with a previously received `ETag` to receive a `304 Not Modified` response when content hasn't changed.

### Get Package Metadata (Dev)

Returns metadata for dev versions only.

```
GET /{organization}/p2/{vendor}/{package}~dev.json
```

Same response format and caching behavior as the stable endpoint, filtered to dev versions (e.g., `dev-main`).

### Notify Batch (Download Tracking)

Receives download notifications from Composer after packages are installed.

```
POST /{organization}/notify-batch
```

**Request body:**

```json
{
    "downloads": [
        { "name": "vendor/package", "version": "1.0.0" },
        { "name": "vendor/other-package", "version": "2.3.1" }
    ]
}
```

**Response:** `204 No Content`

Composer calls this endpoint automatically when the `notify-batch` URL is present in the root `packages.json`. Download counts are tracked per package version.

## Webhooks

### GitHub Webhook

```
POST /webhooks/github/{repository-uuid}
```

Receives push, release, and ping events from GitHub. Triggers an automatic repository sync when new commits or tags are pushed.

Requires webhook signature verification via the `X-Hub-Signature-256` header.

**Supported events:**

| Event | Action |
|-------|--------|
| `ping` | Returns 200 OK |
| `push` | Triggers repository sync |
| `release` | Triggers repository sync |

## Configuring Composer

Add Pricore as a repository in your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://pricore.yourcompany.com/your-org"
        }
    ]
}
```

Then authenticate:

```bash
composer config --global --auth http-basic.pricore.yourcompany.com YOUR_ACCESS_TOKEN ""
```

## Error Responses

All errors return a consistent format:

```json
{
    "message": "Error description"
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `204` | No Content — notify-batch accepted |
| `304` | Not Modified — content unchanged (conditional request) |
| `401` | Unauthorized — invalid or missing token |
| `403` | Forbidden — token lacks access to this organization |
| `404` | Not found |
| `500` | Server error |
