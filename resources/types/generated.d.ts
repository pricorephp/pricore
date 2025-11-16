declare namespace App.Domains.Organization.Contracts.Data {
export type OrganizationData = {
uuid: string;
name: string;
slug: string;
ownerUuid: string;
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
