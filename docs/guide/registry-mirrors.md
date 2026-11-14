# Registry Mirrors

A registry mirror imports the packages of another Composer registry into your organization. Use it to bring packages from an existing Satis instance or a vendor's private registry into Pricore, so your projects need only one repository and one token.

Mirrors are managed by admins and the owner under **Organization Settings** > **Registry Mirrors**.

## Adding a mirror

Click **Add Mirror** and fill in:

- **Name**, such as "Internal Satis".
- **Registry URL**: the URL Composer would use, where `packages.json` lives.
- **Authentication**: none, HTTP Basic (username and password), or a Bearer token.
- **Mirror dist archives**: download every archive into Pricore. Leave this on unless you have a reason not to; without it, Composer downloads from the upstream registry and every developer needs credentials for it.

The first sync starts right away. Mirrored packages appear in the package list and install like any other package. Their page says which mirror they come from.

## Supported registries

Pricore reads registries that serve a `packages.json` in one of these forms:

- Packages listed inline in `packages.json`, as Satis does by default.
- Packages split over `includes` files.
- Composer v2 metadata with a `metadata-url`, if the registry also lists its packages under `available-packages`.

Packagist.org itself can't be mirrored: it doesn't publish a list of its packages in this way, and mirroring all of it wouldn't be useful anyway.

## Syncing

Every mirror is synced every 4 hours by the scheduler. Open a mirror and click **Sync Now** to sync it immediately, or use the command line:

```bash
php artisan sync:mirror {uuid}
php artisan sync:mirror --organization=acme
php artisan sync:mirror --all
```

A sync adds new versions, updates changed ones and removes versions that are gone upstream. If downloading archives fails, for example because the credentials lack download access, the error is shown in the mirror's **Sync History**.

## Private networks

Pricore only connects to public IP addresses for mirrors, including for include files, metadata, archives and redirects. If the registry is on a private network, add its exact hostname or IP to `MIRROR_ALLOWED_PRIVATE_HOSTS`:

```bash
MIRROR_ALLOWED_PRIVATE_HOSTS=satis.internal,10.20.30.40
```

The exception only applies to the mirror's own host. The registry can't redirect Pricore, or point archive downloads, to another private address. Credentials are only sent to the mirror's own host as well.

::: warning
Every organization admin can reach every port and path on an allowed host through a mirror. Only allow hosts that serve a Composer registry.
:::
