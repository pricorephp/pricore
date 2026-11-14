---
layout: home

hero:
  name: Pricore
  text: Private Composer Registry
  tagline: Host your team's private PHP packages on your own server.
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started/
    - theme: alt
      text: View on GitHub
      link: https://github.com/pricorephp/pricore

features:
  - title: Packages from Git
    details: Connect GitHub, GitLab, Bitbucket or any Git server. Tags become releases, branches become dev versions, and webhooks keep them current.
  - title: Monorepos
    details: Publish several packages from one repository, released together or with their own tags.
  - title: Uploads and mirrors
    details: Upload zip archives from CI, or import packages from another Composer registry such as Satis.
  - title: Organizations and tokens
    details: Each organization is its own Composer repository with its own members and access tokens.
  - title: composer audit
    details: Advisories from Packagist are matched against your packages, and composer audit works against your registry.
  - title: REST API and MCP
    details: Automate Pricore through a REST API, or let AI assistants look up packages through the built-in MCP server.
---

## What Pricore does

Pricore is an open-source alternative to Private Packagist, Satis and Repman. You run it yourself, point it at your Git repositories, and your projects install private packages with a normal `composer require`:

```bash
composer config repositories.acme composer https://pricore.example.com/acme
composer require acme/billing
```

Pricore is a Laravel application licensed under Apache 2.0. The quickest way to run it is the [Docker image](/self-hosting/docker). If you'd rather not host it yourself, [Pricore Cloud](https://pricore.dev) runs it for you.
