# API Reference

Pricore provides a Composer-compatible API for package distribution and a REST API for management.

## Authentication

All API requests require authentication via access token.

### HTTP Basic Auth

Use your access token as the password with `token` as the username:

```bash
curl -u "token:YOUR_ACCESS_TOKEN" https://packages.yourcompany.com/org/your-org/packages.json
```

### Authorization Header

Alternatively, use the Bearer token format:

```bash
curl -H "Authorization: Bearer YOUR_ACCESS_TOKEN" https://packages.yourcompany.com/api/...
```

## Composer Repository API

These endpoints implement the [Composer Repository](https://getcomposer.org/doc/05-repositories.md#composer) specification.

### Get Package List

Returns all available packages for an organization.

```
GET /org/{organization}/packages.json
```

**Response:**

```json
{
    "packages": {
        "vendor/package-name": {
            "1.0.0": {
                "name": "vendor/package-name",
                "version": "1.0.0",
                "type": "library",
                "require": {
                    "php": "^8.2"
                },
                "autoload": {
                    "psr-4": {
                        "Vendor\\Package\\": "src/"
                    }
                },
                "dist": {
                    "type": "zip",
                    "url": "https://packages.yourcompany.com/org/your-org/packages/vendor/package-name/1.0.0.zip"
                }
            },
            "dev-main": {
                "name": "vendor/package-name",
                "version": "dev-main",
                "type": "library"
            }
        }
    }
}
```

### Get Package Metadata

Returns metadata for a specific package.

```
GET /org/{organization}/p2/{vendor}/{package}.json
```

**Response:**

```json
{
    "packages": {
        "vendor/package-name": {
            "1.0.0": { ... },
            "1.0.1": { ... },
            "dev-main": { ... }
        }
    }
}
```

### Download Package

Download a package distribution archive.

```
GET /org/{organization}/packages/{vendor}/{package}/{version}.zip
```

Returns a ZIP archive of the package at the specified version.

## REST API

### Organizations

#### List Organizations

```
GET /api/organizations
```

Returns organizations the authenticated user has access to.

**Response:**

```json
{
    "data": [
        {
            "id": "uuid",
            "name": "My Organization",
            "slug": "my-org",
            "created_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

#### Get Organization

```
GET /api/organizations/{slug}
```

**Response:**

```json
{
    "data": {
        "id": "uuid",
        "name": "My Organization",
        "slug": "my-org",
        "description": "Organization description",
        "created_at": "2024-01-01T00:00:00Z"
    }
}
```

### Packages

#### List Packages

```
GET /api/organizations/{slug}/packages
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Filter by package name |
| `page` | integer | Page number |
| `per_page` | integer | Items per page (max 100) |

**Response:**

```json
{
    "data": [
        {
            "id": "uuid",
            "name": "vendor/package-name",
            "description": "Package description",
            "type": "library",
            "latest_version": "1.0.0",
            "created_at": "2024-01-01T00:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

#### Get Package

```
GET /api/organizations/{slug}/packages/{package}
```

**Response:**

```json
{
    "data": {
        "id": "uuid",
        "name": "vendor/package-name",
        "description": "Package description",
        "type": "library",
        "repository": {
            "id": "uuid",
            "provider": "github",
            "repo_identifier": "owner/repo"
        },
        "versions": [
            {
                "version": "1.0.0",
                "normalized_version": "1.0.0.0",
                "released_at": "2024-01-01T00:00:00Z"
            }
        ],
        "created_at": "2024-01-01T00:00:00Z"
    }
}
```

### Repositories

#### List Repositories

```
GET /api/organizations/{slug}/repositories
```

**Response:**

```json
{
    "data": [
        {
            "id": "uuid",
            "provider": "github",
            "repo_identifier": "owner/repo",
            "sync_status": "ok",
            "last_synced_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

#### Trigger Sync

```
POST /api/organizations/{slug}/repositories/{id}/sync
```

Triggers a manual sync of the repository.

**Response:**

```json
{
    "message": "Sync queued successfully"
}
```

### Access Tokens

#### List Tokens

```
GET /api/organizations/{slug}/tokens
```

**Response:**

```json
{
    "data": [
        {
            "id": "uuid",
            "name": "CI Pipeline",
            "scopes": ["packages:read"],
            "last_used_at": "2024-01-01T00:00:00Z",
            "expires_at": null,
            "created_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

#### Create Token

```
POST /api/organizations/{slug}/tokens
```

**Request Body:**

```json
{
    "name": "CI Pipeline",
    "scopes": ["packages:read"],
    "expires_at": "2025-01-01",
    "packages": ["uuid1", "uuid2"]
}
```

**Response:**

```json
{
    "data": {
        "id": "uuid",
        "name": "CI Pipeline",
        "token": "pri_xxxxxxxxxxxxxxxxxxxx",
        "scopes": ["packages:read"],
        "created_at": "2024-01-01T00:00:00Z"
    }
}
```

::: warning
The token value is only returned once at creation time. Store it securely.
:::

#### Revoke Token

```
DELETE /api/organizations/{slug}/tokens/{id}
```

**Response:**

```json
{
    "message": "Token revoked successfully"
}
```

## Webhooks

### GitHub Webhook

```
POST /webhooks/github/{repository-id}
```

Receives push and tag events from GitHub.

### GitLab Webhook

```
POST /webhooks/gitlab/{repository-id}
```

Receives push events from GitLab.

### Bitbucket Webhook

```
POST /webhooks/bitbucket/{repository-id}
```

Receives push events from Bitbucket.

## Error Responses

All errors return a consistent format:

```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad request |
| `401` | Unauthorized |
| `403` | Forbidden |
| `404` | Not found |
| `422` | Validation error |
| `429` | Too many requests |
| `500` | Server error |

## Rate Limiting

API requests are rate limited:

- **Authenticated requests:** 1000 requests per minute
- **Package downloads:** 100 downloads per minute per package

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640000000
```
