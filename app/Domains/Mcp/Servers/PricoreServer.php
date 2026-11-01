<?php

namespace App\Domains\Mcp\Servers;

use App\Domains\Mcp\Tools\AddRepositoryTool;
use App\Domains\Mcp\Tools\CreateAccessTokenTool;
use App\Domains\Mcp\Tools\GetPackageTool;
use App\Domains\Mcp\Tools\ListOrganizationsTool;
use App\Domains\Mcp\Tools\ListRepositoriesTool;
use App\Domains\Mcp\Tools\ListVulnerabilitiesTool;
use App\Domains\Mcp\Tools\SearchPackagesTool;
use App\Domains\Mcp\Tools\SyncRepositoryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pricore')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    Pricore is a private Composer registry. Packages, repositories and tokens belong to organizations,
    and every tool except list-organizations takes an organization slug. Call list-organizations first
    to find the slugs you can use.

    You act with the permissions of the signed-in user: members can read everything in their
    organizations, while adding or syncing repositories and creating Composer tokens require the
    admin or owner role.
    TEXT)]
class PricoreServer extends Server
{
    protected array $tools = [
        ListOrganizationsTool::class,
        SearchPackagesTool::class,
        GetPackageTool::class,
        ListVulnerabilitiesTool::class,
        ListRepositoriesTool::class,
        SyncRepositoryTool::class,
        AddRepositoryTool::class,
        CreateAccessTokenTool::class,
    ];
}
