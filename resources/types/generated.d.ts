declare namespace App.Domains.Organization.Contracts.Data {
export type ActivityFeedData = {
recentReleases: Array<any>;
recentSyncs: Array<any>;
};
export type GitCredentialData = {
uuid: string;
provider: string;
providerLabel: string;
isConfigured: boolean;
};
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
role: App.Domains.Organization.Contracts.Enums.OrganizationRole;
joinedAt: string | null;
};
export type OrganizationStatsData = {
packagesCount: number;
repositoriesCount: number;
tokensCount: number;
membersCount: number;
activityFeed: App.Domains.Organization.Contracts.Data.ActivityFeedData;
};
export type OrganizationWithRoleData = {
organization: App.Domains.Organization.Contracts.Data.OrganizationData;
role: App.Domains.Organization.Contracts.Enums.OrganizationRole;
isOwner: boolean;
pivotUuid: string;
};
export type RecentReleaseData = {
packageName: string;
packageUuid: string;
version: string;
isStable: boolean;
releasedAt: string | null;
};
export type RecentSyncData = {
repositoryName: string;
repositoryUuid: string;
status: string;
statusLabel: string;
startedAt: string;
versionsAdded: number;
versionsUpdated: number;
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
repositoryIdentifier: string | null;
repositoryUuid: string | null;
};
export type PackageVersionData = {
uuid: string;
version: string;
normalizedVersion: string;
releasedAt: string | null;
sourceUrl: string | null;
sourceReference: string | null;
commitUrl: string | null;
};
}
declare namespace App.Domains.Repository.Contracts.Data {
export type RepositoryData = {
uuid: string;
name: string;
provider: string;
providerLabel: string;
repoIdentifier: string;
url: string | null;
syncStatus: string | null;
syncStatusLabel: string | null;
lastSyncedAt: string | null;
packagesCount: number;
webhookActive: boolean;
};
export type RepositorySuggestionData = {
name: string;
fullName: string;
isPrivate: boolean;
description: string | null;
};
export type SyncLogData = {
uuid: string;
status: string;
statusLabel: string;
startedAt: string;
completedAt: string | null;
errorMessage: string | null;
versionsAdded: number;
versionsUpdated: number;
details: { [key: string]: any } | null;
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
lastUsedAt: string | null;
expiresAt: string | null;
createdAt: string;
};
export type TokenCreatedData = {
plainToken: string;
name: string;
expiresAt: string | null;
organizationUuid: string | null;
};
}
