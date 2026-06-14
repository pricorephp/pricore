type TokenScope = App.Domains.Token.Contracts.Enums.TokenScope;

export interface ScopeGroup {
    label: string;
    scopes: { value: TokenScope; label: string }[];
}

// Composer access is granted to every token implicitly; these are the
// additional, opt-in permissions for the management API.
export const API_SCOPE_GROUPS: ScopeGroup[] = [
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
            { value: 'write:packages', label: 'Write' },
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
