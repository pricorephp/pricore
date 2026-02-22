# API Reference

Pricore provides a Composer-compatible API for package distribution and webhook endpoints for automatic syncing.

## Authentication

The Composer API requires authentication via access token. Two methods are supported:

### HTTP Basic Auth

Use `token` as the username and your access token as the password:

```bash
curl -u "token:YOUR_ACCESS_TOKEN" https://pricore.yourcompany.com/your-org/packages.json
```

### Authorization Header

Alternatively, use the Bearer token format:

```bash
curl -H "Authorization: Bearer YOUR_ACCESS_TOKEN" https://pricore.yourcompany.com/your-org/packages.json
```

## Composer Repository API

These endpoints implement the [Composer v2 Repository](https://getcomposer.org/doc/05-repositories.md#composer) specification.

### Get Package List

Returns the metadata URL template for an organization.

```
GET /{organization}/packages.json
```

**Response:**

```json
{
    "metadata-url": "https://pricore.yourcompany.com/your-org/p2/%package%.json"
}
```

Composer uses this template to resolve individual package metadata.

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
    }
}
```

Responses include caching headers (`Cache-Control: max-age=3600`).

### Get Package Metadata (Dev)

Returns metadata for dev versions only.

```
GET /{organization}/p2/{vendor}/{package}~dev.json
```

Same response format as the stable endpoint, filtered to dev versions (e.g., `dev-main`).

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
composer config --global --auth http-basic.pricore.yourcompany.com token YOUR_ACCESS_TOKEN
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
| `401` | Unauthorized — invalid or missing token |
| `403` | Forbidden — token lacks access to this organization |
| `404` | Not found |
| `500` | Server error |
