import DismissOnboardingController from '@/actions/App/Domains/Organization/Http/Controllers/DismissOnboardingController';
import ImportRepositoriesDialog from '@/components/import-repositories-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    ExternalLink,
    GitBranch,
    Import,
    Key,
    Link2,
    X,
} from 'lucide-react';
import { useState } from 'react';

type OnboardingChecklistData =
    App.Domains.Organization.Contracts.Data.OnboardingChecklistData;

interface OnboardingChecklistProps {
    organization: { slug: string };
    onboarding: OnboardingChecklistData;
    configuredProviders?: string[];
}

interface StepProps {
    title: string;
    description: string;
    completed: boolean;
    children?: React.ReactNode;
}

function Step({ title, description, completed, children }: StepProps) {
    return (
        <div className="flex gap-3">
            <div className="mt-0.5 shrink-0">
                {completed ? (
                    <CheckCircle2 className="size-5 text-emerald-500" />
                ) : (
                    <Circle className="size-5 text-muted-foreground/40" />
                )}
            </div>
            <div className="flex-1">
                <p
                    className={
                        completed
                            ? 'font-medium text-muted-foreground line-through'
                            : 'font-medium'
                    }
                >
                    {title}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {description}
                </p>
                {!completed && children && (
                    <div className="mt-3">{children}</div>
                )}
            </div>
        </div>
    );
}

export default function OnboardingChecklist({
    organization,
    onboarding,
    configuredProviders = [],
}: OnboardingChecklistProps) {
    const [isImportOpen, setIsImportOpen] = useState(false);
    if (onboarding.isDismissed) {
        return null;
    }

    const hasToken = onboarding.hasPersonalToken || onboarding.hasOrgToken;

    function handleDismiss() {
        router.post(
            DismissOnboardingController.url(organization.slug),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle>Getting Started</CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={handleDismiss}
                        className="size-8"
                    >
                        <X className="size-4" />
                        <span className="sr-only">Dismiss</span>
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                <Step
                    title="Create organization"
                    description="Your organization is ready to host private packages."
                    completed
                />

                <Step
                    title="Register a Git provider"
                    description="Add credentials for GitHub, GitLab, or Bitbucket to sync repositories."
                    completed={onboarding.hasGitProvider}
                >
                    <Button size="sm" variant="secondary" asChild>
                        <Link href="/settings/git-credentials">
                            <Link2 className="size-4" />
                            Configure Git Credentials
                            <ExternalLink className="size-3" />
                        </Link>
                    </Button>
                </Step>

                <Step
                    title="Connect a repository"
                    description="Link a Git repository to automatically sync packages."
                    completed={onboarding.hasRepository}
                >
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" asChild>
                            <Link
                                href={`/organizations/${organization.slug}/repositories`}
                            >
                                <GitBranch className="size-4" />
                                Add Repository
                            </Link>
                        </Button>
                        {configuredProviders.length > 0 && (
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => setIsImportOpen(true)}
                            >
                                <Import className="size-4" />
                                Import Repositories
                            </Button>
                        )}
                    </div>
                </Step>

                <Step
                    title="Create a token"
                    description="Tokens authenticate Composer with your private registry. Personal tokens grant access to all your organizations; organization tokens are scoped to this organization only."
                    completed={hasToken}
                >
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" asChild>
                            <Link href="/settings/tokens">
                                <Key className="size-4" />
                                Create Personal Token
                                <ExternalLink className="size-3" />
                            </Link>
                        </Button>
                        <Button size="sm" variant="secondary" asChild>
                            <Link
                                href={`/organizations/${organization.slug}/settings/tokens`}
                            >
                                <Key className="size-4" />
                                Create Organization Token
                            </Link>
                        </Button>
                    </div>
                </Step>
            </CardContent>

            {configuredProviders.length > 0 && (
                <ImportRepositoriesDialog
                    organizationSlug={organization.slug}
                    isOpen={isImportOpen}
                    onClose={() => setIsImportOpen(false)}
                    configuredProviders={configuredProviders}
                />
            )}
        </Card>
    );
}
