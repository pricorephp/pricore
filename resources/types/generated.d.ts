declare namespace App.Domains.Activity.Contracts.Data {
export type ActivityLogData = {
uuid: string;
type: App.Domains.Activity.Contracts.Enums.ActivityType;
typeLabel: string;
icon: string;
category: string;
actorName: string | null;
actorAvatar: string | null;
subjectType: string | null;
subjectUuid: string | null;
properties: Array<any> | null;
createdAt: string | null;
};
}
declare namespace App.Domains.Activity.Contracts.Enums {
export type ActivityType = 'repository.added' | 'repository.removed' | 'repository.synced' | 'repository.sync_failed' | 'package.created' | 'package.removed' | 'member.added' | 'member.removed' | 'member.role_changed' | 'invitation.sent' | 'token.created' | 'token.revoked' | 'ssh_key.generated' | 'ssh_key.deleted' | 'mirror.added' | 'mirror.removed' | 'mirror.synced' | 'mirror.sync_failed' | 'security.vulnerabilities_detected';
}
declare namespace App.Domains.Auth.Contracts.Enums {
export type GitHubOAuthIntent = 'login' | 'connect';
export type GitLabOAuthIntent = 'login' | 'connect';
}
declare namespace App.Domains.Mirror.Contracts.Data {
export type MirrorData = {
uuid: string;
name: string;
url: string;
authType: App.Domains.Mirror.Contracts.Enums.MirrorAuthType;
mirrorDist: boolean;
syncStatus: App.Domains.Repository.Contracts.Enums.RepositorySyncStatus | null;
lastSyncedAt: string | null;
packagesCount: number;
createdAt: string;
};
export type MirrorSyncLogData = {
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
declare namespace App.Domains.Mirror.Contracts.Enums {
export type MirrorAuthType = 'none' | 'basic' | 'bearer';
export type SyncVersionResult = 'added' | 'updated' | 'skipped' | 'failed' | 'dist_failed';
}
declare namespace App.Domains.Organization.Contracts.Data {
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
composerRepositoryUrl: string;
permissions: App.Domains.Organization.Contracts.Data.OrganizationPermissionsData | null;
onTrial: boolean | null;
trialExpired: boolean | null;
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
export type OrganizationSshKeyData = {
uuid: string;
name: string;
publicKey: string;
fingerprint: string;
createdAt: string;
};
export type OrganizationStatsData = {
packagesCount: number;
repositoriesCount: number;
tokensCount: number;
membersCount: number;
totalDownloads: number;
dailyDownloads: Array<any>;
};
export type OrganizationWithRoleData = {
organization: App.Domains.Organization.Contracts.Data.OrganizationData;
role: App.Domains.Organization.Contracts.Enums.OrganizationRole;
isOwner: boolean;
pivotUuid: string;
};
}
declare namespace App.Domains.Organization.Contracts.Enums {
export type OrganizationRole = 'owner' | 'admin' | 'member';
}
declare namespace App.Domains.Package.Contracts.Data {
export type FrequentPackageData = {
uuid: string;
name: string;
latestVersion: string | null;
};
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
mirrorName: string | null;
mirrorUuid: string | null;
};
export type PackageDownloadStatsData = {
totalDownloads: number;
dailyDownloads: Array<App.Domains.Organization.Contracts.Data.DailyDownloadData>;
versionDailyDownloads: Array<App.Domains.Package.Contracts.Data.VersionDailyDownloadData>;
};
export type PackageVersionData = {
uuid: string;
version: string;
normalizedVersion: string;
releasedAt: string | null;
sourceUrl: string | null;
sourceReference: string | null;
commitUrl: string | null;
distSize: number | null;
vulnerabilityCount: number;
highestSeverity: App.Domains.Security.Contracts.Enums.AdvisorySeverity | null;
};
export type PackageVersionDetailData = {
uuid: string;
version: string;
normalizedVersion: string;
releasedAt: string | null;
sourceUrl: string | null;
sourceReference: string | null;
commitUrl: string | null;
description: string | null;
type: string | null;
license: string | null;
require: Array<any> | null;
requireDev: Array<any> | null;
autoload: Array<any> | null;
authors: Array<any> | null;
keywords: Array<any> | null;
isStable: boolean;
isDev: boolean;
advisoryMatches: Array<App.Domains.Security.Contracts.Data.SecurityAdvisoryMatchData> | null;
};
export type VersionDailyDownloadData = {
version: string;
dailyDownloads: Array<App.Domains.Organization.Contracts.Data.DailyDownloadData>;
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
supportsWebhooks: boolean;
supportsAutomaticWebhooks: boolean;
webhookActive: boolean;
webhookUrl: string | null;
webhookSecret: string | null;
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
declare namespace App.Domains.Security.Contracts.Data {
export type PackageSecuritySummaryData = {
packageUuid: string;
packageName: string;
affectedVersionCount: number;
criticalCount: number;
highCount: number;
mediumCount: number;
lowCount: number;
totalCount: number;
};
export type SecurityAdvisoryData = {
uuid: string;
advisoryId: string;
packageName: string;
title: string;
link: string | null;
cve: string | null;
affectedVersions: string;
severity: App.Domains.Security.Contracts.Enums.AdvisorySeverity;
reportedAt: string | null;
};
export type SecurityAdvisoryMatchData = {
uuid: string;
advisory: App.Domains.Security.Contracts.Data.SecurityAdvisoryData;
matchType: App.Domains.Security.Contracts.Enums.AdvisoryMatchType;
dependencyName: string | null;
};
}
declare namespace App.Domains.Security.Contracts.Enums {
export type AdvisoryMatchType = 'direct' | 'dependency';
export type AdvisorySeverity = 'critical' | 'high' | 'medium' | 'low' | 'unknown';
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
