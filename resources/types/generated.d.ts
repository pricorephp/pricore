declare namespace App.Domains.Auth.Contracts.Enums {
export type GitHubOAuthIntent = 'login' | 'connect';
}
declare namespace App.Domains.Organization.Contracts.Data {
export type ActivityFeedData = {
recentReleases: Array<any>;
recentSyncs: Array<any>;
};
export type DailyDownloadData = {
date: string;
downloads: number;
};
export type GitCredentialData = {
uuid: string;
provider: string;
providerLabel: string;
isConfigured: boolean;
};
export type OnboardingChecklistData = {
hasGitProvider: boolean;
hasRepository: boolean;
hasPersonalToken: boolean;
hasOrgToken: boolean;
isDismissed: boolean;
};
export type OrganizationData = {
uuid: string;
name: string;
slug: string;
ownerUuid: string;
permissions: App.Domains.Organization.Contracts.Data.OrganizationPermissionsData | null;
};
export type OrganizationInvitationData = {
uuid: string;
email: string;
role: App.Domains.Organization.Contracts.Enums.OrganizationRole;
status: string;
invitedByName: string | null;
createdAt: string | null;
expiresAt: string | null;
};
export type OrganizationMemberData = {
uuid: string;
name: string;
email: string;
avatar: string | null;
role: App.Domains.Organization.Contracts.Enums.OrganizationRole;
joinedAt: string | null;
};
export type OrganizationPermissionsData = {
canViewSettings: boolean;
canManageMembers: boolean;
canDeleteOrganization: boolean;
canUpdateSlug: boolean;
canManageRepository: boolean;
};
export type OrganizationStatsData = {
packagesCount: number;
repositoriesCount: number;
tokensCount: number;
membersCount: number;
totalDownloads: number;
dailyDownloads: Array<any>;
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
status: App.Domains.Repository.Contracts.Enums.SyncStatus;
statusLabel: string;
startedAt: string;
completedAt: string | null;
versionsAdded: number;
versionsUpdated: number;
versionsRemoved: number;
errorMessage: string | null;
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
export type PackageDownloadStatsData = {
totalDownloads: number;
dailyDownloads: Array<any>;
versionBreakdown: Array<any>;
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
export type VersionDownloadData = {
version: string;
downloads: number;
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
syncStatus: App.Domains.Repository.Contracts.Enums.RepositorySyncStatus | null;
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
isConnected: boolean;
};
export type SyncLogData = {
uuid: string;
status: App.Domains.Repository.Contracts.Enums.SyncStatus;
statusLabel: string;
startedAt: string;
completedAt: string | null;
errorMessage: string | null;
versionsAdded: number;
versionsUpdated: number;
versionsRemoved: number;
details: { [key: string]: any } | null;
};
}
declare namespace App.Domains.Repository.Contracts.Enums {
export type GitProvider = 'github' | 'gitlab' | 'bitbucket' | 'git';
export type RepositorySyncStatus = 'ok' | 'failed' | 'pending';
export type SyncStatus = 'pending' | 'success' | 'failed';
}
declare namespace App.Domains.Search.Contracts.Data {
export type SearchPackageData = {
uuid: string;
name: string;
description: string | null;
organizationName: string;
organizationSlug: string;
};
export type SearchRepositoryData = {
uuid: string;
name: string;
provider: string;
providerLabel: string;
organizationName: string;
organizationSlug: string;
};
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
declare namespace App.Http.Data {
export type AuthData = {
user: App.Http.Data.UserData | null;
organizations: Array<any>;
};
export type FlashData = {
status: string | null;
error: string | null;
};
export type SearchData = {
packages: Array<any>;
repositories: Array<any>;
};
export type SharedData = {
name: string;
version: string | null;
auth: App.Http.Data.AuthData;
search: App.Http.Data.SearchData | null;
sidebarOpen: boolean;
flash: App.Http.Data.FlashData | null;
};
export type UserData = {
uuid: string;
name: string;
email: string;
avatar: string | null;
hasPassword: boolean;
emailVerifiedAt: string | null;
twoFactorEnabled: boolean;
createdAt: string | null;
updatedAt: string | null;
};
}
