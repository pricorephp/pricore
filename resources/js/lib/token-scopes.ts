type TokenScope = App.Domains.Token.Contracts.Enums.TokenScope;

export interface ScopeGroup {
    label: string;
    scopes: { value: TokenScope; label: string }[];
}

// Composer access is granted to every token implicitly and publishing has its
// own checkbox; these are the additional, opt-in permissions for the REST API.
const API_SCOPE_GROUPS: ScopeGroup[] = [
    {
        label: 'Organizations',
        scopes: [
            { value: 'read:organizations', label: 'Read' },
            { value: 'write:organizations', label: 'Write' },
            { value: 'delete:organizations', label: 'Delete' },
        ],
    },
    {
        label: 'Repositories',
        scopes: [
            { value: 'read:repositories', label: 'Read' },
            { value: 'write:repositories', label: 'Write' },
            { value: 'delete:repositories', label: 'Delete' },
        ],
    },
    {
        label: 'Packages',
        scopes: [
            { value: 'read:packages', label: 'Read' },
            { value: 'delete:packages', label: 'Delete' },
        ],
    },
    {
        label: 'Members',
        scopes: [
            { value: 'read:members', label: 'Read' },
            { value: 'write:members', label: 'Write' },
        ],
    },
    {
        label: 'Mirrors',
        scopes: [
            { value: 'read:mirrors', label: 'Read' },
            { value: 'write:mirrors', label: 'Write' },
            { value: 'delete:mirrors', label: 'Delete' },
        ],
    },
    {
        label: 'Tokens',
        scopes: [
            { value: 'read:tokens', label: 'Read' },
            { value: 'write:tokens', label: 'Write' },
        ],
    },
];

// Only the owner can delete an organization, which an organization token can't stand in for.
const OWNER_ONLY_SCOPES: TokenScope[] = ['delete:organizations'];

export function apiScopeGroups(isOrganizationToken: boolean): ScopeGroup[] {
    if (!isOrganizationToken) {
        return API_SCOPE_GROUPS;
    }

    return API_SCOPE_GROUPS.map((group) => ({
        ...group,
        scopes: group.scopes.filter(
            (scope) => !OWNER_ONLY_SCOPES.includes(scope.value),
        ),
    }));
}

// Read endpoints an organization token can call, in order of preference.
const ORGANIZATION_EXAMPLE_PATHS: [TokenScope, string][] = [
    ['read:organizations', ''],
    ['read:repositories', '/repositories'],
    ['read:packages', '/packages'],
    ['read:members', '/members'],
    ['read:mirrors', '/mirrors'],
    ['read:tokens', '/tokens'],
];

/**
 * A first request the token is allowed to make, or null when it only has
 * write or delete scopes. Personal tokens can always read their own profile.
 */
export function apiExamplePath(
    scopes: string[],
    organizationSlug?: string,
): string | null {
    if (!organizationSlug) {
        return '/api/v1/user';
    }

    const match = ORGANIZATION_EXAMPLE_PATHS.find(([scope]) =>
        scopes.includes(scope),
    );

    return match
        ? `/api/v1/organizations/${organizationSlug}${match[1]}`
        : null;
}
