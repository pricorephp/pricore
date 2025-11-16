declare namespace App.Domains.Organization.Contracts.Data {
    export type OrganizationData = {
        uuid: string;
        name: string;
        slug: string;
        ownerUuid: string;
    };
    export type OrganizationMemberData = {
        uuid: string;
        name: string;
        email: string;
        role: string;
        joinedAt: string;
    };
    export type OrganizationStatsData = {
        packagesCount: number;
        repositoriesCount: number;
        tokensCount: number;
    };
}
declare namespace App.Domains.Organization.Contracts.Enums {
    export type OrganizationRole = 'owner' | 'admin' | 'member';
}
declare namespace App.Domains.Package.Contracts.Data {
    export type PackageData = {
        uuid: string;
        name: string;
        description: string | null;
        type: string | null;
        visibility: string;
        isProxy: boolean;
        versionsCount: number;
        latestVersion: string | null;
        updatedAt: string;
        repositoryName: string | null;
    };
}
declare namespace App.Domains.Repository.Contracts.Data {
    export type RepositoryData = {
        uuid: string;
        name: string;
        provider: string;
        repoIdentifier: string;
        syncStatus: string | null;
        lastSyncedAt: string | null;
        packagesCount: number;
    };
}
declare namespace App.Domains.Repository.Contracts.Enums {
    export type GitProvider = 'github' | 'gitlab' | 'bitbucket' | 'git';
    export type RepositorySyncStatus = 'ok' | 'failed' | 'pending';
    export type SyncStatus = 'pending' | 'success' | 'failed';
}
declare namespace App.Domains.Token.Contracts.Data {
    export type AccessTokenData = {
        uuid: string;
        name: string;
        scopes: Array<any> | null;
        lastUsedAt: string | null;
        expiresAt: string | null;
        createdAt: string;
    };
}
