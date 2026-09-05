declare namespace App {
namespace Domains {
namespace Activity {
namespace Contracts {
namespace Data {
export type ActivityLogData = {
uuid: string,
type: App.Domains.Activity.Contracts.Enums.ActivityType,
typeLabel: string,
icon: string,
category: string,
actorName: string | null,
actorAvatar: string | null,
subjectType: string | null,
subjectUuid: string | null,
properties: Record<string, any> | null,
createdAt: string | null,
};
}
namespace Enums {
export type ActivityType = 'repository.added' | 'repository.removed' | 'repository.synced' | 'repository.sync_failed' | 'package.created' | 'package.removed' | 'member.added' | 'member.removed' | 'member.role_changed' | 'invitation.sent' | 'token.created' | 'token.revoked' | 'ssh_key.generated' | 'ssh_key.deleted' | 'mirror.added' | 'mirror.removed' | 'mirror.synced' | 'mirror.sync_failed' | 'security.vulnerabilities_detected';
}
}
}
namespace Auth {
namespace Contracts {
namespace Enums {
export type GitHubOAuthIntent = 'login' | 'connect';
export type GitLabOAuthIntent = 'login' | 'connect';
}
}
}
namespace Composer {
namespace Contracts {
namespace Data {
export type DownloadNotificationData = {
name: string,
version: string,
};
export type SourceData = {
type: string,
url: string,
reference: string | null,
shasum: string | null,
};
export type VersionMetadataData = {
name: string,
version: string,
versionNormalized: string,
composerJson: Record<string, any>,
source: App.Domains.Composer.Contracts.Data.SourceData | null,
dist: App.Domains.Composer.Contracts.Data.SourceData | null,
time: string | null,
};
}
}
}
namespace Mirror {
namespace Contracts {
namespace Data {
export type MirrorData = {
uuid: string,
name: string,
url: string,
authType: App.Domains.Mirror.Contracts.Enums.MirrorAuthType,
mirrorDist: boolean,
syncStatus: App.Domains.Repository.Contracts.Enums.RepositorySyncStatus | null,
lastSyncedAt: string | null,
packagesCount: number,
createdAt: string,
};
export type MirrorSyncLogData = {
uuid: string,
status: App.Domains.Repository.Contracts.Enums.SyncStatus,
statusLabel: string,
startedAt: string,
completedAt: string | null,
errorMessage: string | null,
versionsAdded: number,
versionsUpdated: number,
versionsRemoved: number,
details: Record<string, any> | null,
};
}
namespace Enums {
export type MirrorAuthType = 'none' | 'basic' | 'bearer';
export type SyncVersionResult = 'added' | 'updated' | 'skipped' | 'failed' | 'dist_failed';
}
}
}
namespace Organization {
namespace Contracts {
namespace Data {
export type DailyDownloadData = {
date: string,
downloads: number,
};
export type GitCredentialData = {
uuid: string,
provider: string,
providerLabel: string,
isConfigured: boolean,
};
export type OnboardingChecklistData = {
hasGitProvider: boolean,
hasRepository: boolean,
hasPersonalToken: boolean,
hasOrgToken: boolean,
isDismissed: boolean,
};
export type OrganizationData = {
uuid: string,
name: string,
slug: string,
ownerUuid: string,
composerRepositoryUrl: string,
permissions: App.Domains.Organization.Contracts.Data.OrganizationPermissionsData | null,
onTrial: boolean | null,
trialExpired: boolean | null,
securityAuditsEnabled: boolean,
securityNotificationsEnabled: boolean,
};
export type OrganizationInvitationData = {
uuid: string,
email: string,
role: App.Domains.Organization.Contracts.Enums.OrganizationRole,
status: string,
invitedByName: string | null,
createdAt: string | null,
expiresAt: string | null,
};
export type OrganizationMemberData = {
uuid: string,
name: string,
email: string,
avatar: string | null,
role: App.Domains.Organization.Contracts.Enums.OrganizationRole,
joinedAt: string | null,
};
export type OrganizationPermissionsData = {
canViewSettings: boolean,
canManageMembers: boolean,
canDeleteOrganization: boolean,
canUpdateSlug: boolean,
canManageRepository: boolean,
};
export type OrganizationSshKeyData = {
uuid: string,
name: string,
publicKey: string,
fingerprint: string,
createdAt: string,
};
export type OrganizationStatsData = {
packagesCount: number,
repositoriesCount: number,
tokensCount: number,
membersCount: number,
totalDownloads: number,
dailyDownloads: App.Domains.Organization.Contracts.Data.DailyDownloadData[],
};
export type OrganizationWithRoleData = {
organization: App.Domains.Organization.Contracts.Data.OrganizationData,
role: App.Domains.Organization.Contracts.Enums.OrganizationRole,
isOwner: boolean,
pivotUuid: string,
};
}
namespace Enums {
export type OrganizationRole = 'owner' | 'admin' | 'member';
}
}
}
namespace Package {
namespace Contracts {
namespace Data {
export type FrequentPackageData = {
uuid: string,
name: string,
latestVersion: string | null,
};
export type PackageData = {
uuid: string,
name: string,
description: string | null,
type: string | null,
visibility: string,
isProxy: boolean,
versionsCount: number,
latestVersion: string | null,
updatedAt: string,
repositoryName: string | null,
repositoryIdentifier: string | null,
repositoryUuid: string | null,
sourcePath: string | null,
mirrorName: string | null,
mirrorUuid: string | null,
};
export type PackageDownloadStatsData = {
totalDownloads: number,
currentPeriodDownloads: number,
previousPeriodDownloads: number,
dailyDownloads: App.Domains.Organization.Contracts.Data.DailyDownloadData[],
versionDailyDownloads: App.Domains.Package.Contracts.Data.VersionDailyDownloadData[],
};
export type PackageVersionData = {
uuid: string,
version: string,
normalizedVersion: string,
releasedAt: string | null,
sourceUrl: string | null,
sourceReference: string | null,
sourceTag: string | null,
sourcePath: string | null,
commitUrl: string | null,
tagUrl: string | null,
distSize: number | null,
vulnerabilityCount: number,
highestSeverity: App.Domains.Security.Contracts.Enums.AdvisorySeverity | null,
};
export type PackageVersionDetailData = {
uuid: string,
version: string,
normalizedVersion: string,
releasedAt: string | null,
sourceUrl: string | null,
sourceReference: string | null,
sourceTag: string | null,
sourcePath: string | null,
commitUrl: string | null,
tagUrl: string | null,
description: string | null,
type: string | null,
license: string | null,
require: Record<string, string> | null,
requireDev: Record<string, string> | null,
conflict: Record<string, string> | null,
provide: Record<string, string> | null,
replace: Record<string, string> | null,
suggest: Record<string, string> | null,
autoload: Record<string, string> | null,
authors: {
name?: string,
email?: string,
homepage?: string,
}[] | null,
keywords: string[] | null,
isStable: boolean,
isDev: boolean,
readmeHtml: string | null,
advisoryMatches: App.Domains.Security.Contracts.Data.SecurityAdvisoryMatchData[] | null,
};
export type VersionDailyDownloadData = {
version: string,
dailyDownloads: App.Domains.Organization.Contracts.Data.DailyDownloadData[],
};
}
}
}
namespace Release {
namespace Contracts {
namespace Data {
export type ReleaseData = {
name: string,
tagName: string,
version: string,
htmlUrl: string,
publishedAt: string | null,
bodyHtml: string,
};
export type ReleaseInfoData = {
currentVersion: string | null,
latestVersion: string | null,
isOutdated: boolean,
releases: App.Domains.Release.Contracts.Data.ReleaseData[],
};
}
}
}
namespace Repository {
namespace Contracts {
namespace Data {
export type BulkImportResultData = {
created: number,
skipped: number,
webhooksFailed: number,
};
export type ComposerMetadataData = {
name: string,
version: string,
normalizedVersion: string,
composerJson: Record<string, any>,
type: string,
description: string | null,
};
export type DistArchiveData = {
path: string,
shasum: string,
size: number,
};
export type ExistingVersionData = {
version: string,
sourceReference: string,
};
export type RefData = {
name: string,
commit: string,
};
export type RefsCollectionData = {
tags: App.Domains.Repository.Contracts.Data.RefData[],
branches: App.Domains.Repository.Contracts.Data.RefData[],
all: App.Domains.Repository.Contracts.Data.RefData[],
};
export type RepositoryData = {
uuid: string,
name: string,
provider: string,
providerLabel: string,
repoIdentifier: string,
url: string | null,
syncStatus: App.Domains.Repository.Contracts.Enums.RepositorySyncStatus | null,
syncStatusLabel: string | null,
lastSyncedAt: string | null,
packagesCount: number,
packagePaths: string[] | null,
supportsWebhooks: boolean,
supportsAutomaticWebhooks: boolean,
webhookActive: boolean,
webhookUrl: string | null,
webhookSecret: string | null,
};
export type RepositorySuggestionData = {
name: string,
fullName: string,
isPrivate: boolean,
description: string | null,
isConnected: boolean,
};
export type SyncLogData = {
uuid: string,
status: App.Domains.Repository.Contracts.Enums.SyncStatus,
statusLabel: string,
startedAt: string,
completedAt: string | null,
errorMessage: string | null,
versionsAdded: number,
versionsUpdated: number,
versionsRemoved: number,
details: Record<string, any> | null,
};
export type SyncResultData = {
added: number,
updated: number,
skipped: number,
};
}
namespace Enums {
export type GitProvider = 'github' | 'gitlab' | 'bitbucket' | 'git';
export type RepositorySyncStatus = 'ok' | 'failed' | 'pending';
export type SyncStatus = 'pending' | 'success' | 'failed';
}
}
}
namespace Search {
namespace Contracts {
namespace Data {
export type SearchPackageData = {
uuid: string,
name: string,
description: string | null,
organizationName: string,
organizationSlug: string,
};
export type SearchRepositoryData = {
uuid: string,
name: string,
provider: string,
providerLabel: string,
organizationName: string,
organizationSlug: string,
};
}
}
}
namespace Security {
namespace Contracts {
namespace Data {
export type AdvisorySyncResultData = {
advisoriesAdded: number,
advisoriesUpdated: number,
};
export type ComposerAdvisoryData = {
advisoryId: string,
packageName: string,
title: string,
link: string | null,
cve: string | null,
affectedVersions: string,
sources: {
name: string,
remoteId: string,
}[],
reportedAt: string | null,
composerRepository: string | null,
severity: string,
};
export type PackageSecuritySummaryData = {
packageUuid: string,
packageName: string,
affectedVersionCount: number,
criticalCount: number,
highCount: number,
mediumCount: number,
lowCount: number,
totalCount: number,
};
export type SecurityAdvisoryData = {
uuid: string,
advisoryId: string,
packageName: string,
title: string,
link: string | null,
cve: string | null,
affectedVersions: string,
severity: App.Domains.Security.Contracts.Enums.AdvisorySeverity,
reportedAt: string | null,
};
export type SecurityAdvisoryMatchData = {
uuid: string,
advisory: App.Domains.Security.Contracts.Data.SecurityAdvisoryData,
matchType: App.Domains.Security.Contracts.Enums.AdvisoryMatchType,
dependencyName: string | null,
};
}
namespace Enums {
export type AdvisoryMatchType = 'direct' | 'dependency';
export type AdvisorySeverity = 'critical' | 'high' | 'medium' | 'low' | 'unknown';
}
}
}
namespace Token {
namespace Contracts {
namespace Data {
export type AccessTokenData = {
uuid: string,
name: string,
lastUsedAt: string | null,
expiresAt: string | null,
createdAt: string,
};
export type TokenCreatedData = {
plainToken: string,
name: string,
expiresAt: string | null,
organizationUuid: string | null,
};
}
}
}
}
namespace Http {
namespace Data {
export type AuthData = {
user: App.Http.Data.UserData | null,
organizations: App.Domains.Organization.Contracts.Data.OrganizationData[],
};
export type FlashData = {
status: string | null,
error: string | null,
};
export type SearchData = {
packages: App.Domains.Search.Contracts.Data.SearchPackageData[],
repositories: App.Domains.Search.Contracts.Data.SearchRepositoryData[],
};
export type SharedData = {
name: string,
version: string | null,
auth: App.Http.Data.AuthData,
search: App.Http.Data.SearchData | null,
sidebarOpen: boolean,
flash: App.Http.Data.FlashData | null,
};
export type UserData = {
uuid: string,
name: string,
email: string,
avatar: string | null,
hasPassword: boolean,
emailVerifiedAt: string | null,
twoFactorEnabled: boolean,
createdAt: string | null,
updatedAt: string | null,
};
}
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
