import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Settings/UserGitCredentialController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Form } from '@inertiajs/react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialDialogProps {
    credential?: GitCredentialData | null;
    provider: string;
    providers: Record<string, string>;
    githubConnectUrl?: string;
    gitlabConnectUrl?: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function GitCredentialDialog({
    credential,
    provider,
    providers,
    githubConnectUrl,
    gitlabConnectUrl,
    isOpen,
    onClose,
}: GitCredentialDialogProps) {
    const isEditing = !!credential;
    const providerLabel = providers[provider] || provider;
    const showGitHubOAuth =
        provider === 'github' && !!githubConnectUrl && !isEditing;
    const showGitLabOAuth =
        provider === 'gitlab' && !!gitlabConnectUrl && !isEditing;

    const getGitHubTokenUrl = (): string => {
        const description = 'Pricore';
        const encodedDescription = encodeURIComponent(description);
        const scopes = 'repo,read:org';
        return `https://github.com/settings/tokens/new?description=${encodedDescription}&scopes=${scopes}`;
    };

    const getFormFields = () => {
        switch (provider) {
            case 'github':
                return (
                    <>
                        {showGitHubOAuth && (
                            <>
                                <div className="grid space-y-2">
                                    <Button asChild className="w-full">
                                        <a href={githubConnectUrl}>
                                            <svg
                                                className="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                            >
                                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                                            </svg>
                                            Use my GitHub account
                                        </a>
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        Authorizes Pricore to access your GitHub
                                        repositories. The token will be
                                        automatically refreshed when you sign in
                                        via GitHub.
                                    </p>
                                </div>

                                <div className="relative">
                                    <div className="absolute inset-0 flex items-center">
                                        <Separator />
                                    </div>
                                    <div className="relative flex justify-center text-xs uppercase">
                                        <span className="bg-background px-2 text-muted-foreground">
                                            or enter a token manually
                                        </span>
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="grid space-y-2">
                            <Label htmlFor="token">
                                Personal Access Token{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="token"
                                name="credentials[token]"
                                type="password"
                                required
                                placeholder="ghp_xxxxxxxxxxxx"
                                autoFocus
                            />
                            <p className="text-sm text-muted-foreground">
                                Create a personal access token in{' '}
                                <a
                                    href={getGitHubTokenUrl()}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    GitHub Settings → Developer settings →
                                    Personal access tokens
                                </a>
                                . Make sure to select the <strong>repo</strong>{' '}
                                scope (and <strong>read:org</strong> if
                                accessing organization repositories).
                            </p>
                        </div>
                    </>
                );
            case 'gitlab':
                return (
                    <>
                        {showGitLabOAuth && (
                            <>
                                <div className="grid space-y-2">
                                    <Button asChild className="w-full">
                                        <a href={gitlabConnectUrl}>
                                            <svg
                                                className="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                            >
                                                <path d="M23.955 13.587l-1.342-4.135-2.664-8.189a.455.455 0 00-.867 0L16.418 9.45H7.582L4.918 1.263a.455.455 0 00-.867 0L1.386 9.45.044 13.587a.924.924 0 00.331 1.023L12 23.054l11.625-8.443a.92.92 0 00.33-1.024" />
                                            </svg>
                                            Use my GitLab account
                                        </a>
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        Authorizes Pricore to access your GitLab
                                        repositories. The token will be
                                        automatically refreshed when you sign in
                                        via GitLab.
                                    </p>
                                </div>

                                <div className="relative">
                                    <div className="absolute inset-0 flex items-center">
                                        <Separator />
                                    </div>
                                    <div className="relative flex justify-center text-xs uppercase">
                                        <span className="bg-background px-2 text-muted-foreground">
                                            or enter a token manually
                                        </span>
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="grid space-y-2">
                            <Label htmlFor="token">
                                Personal Access Token{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="token"
                                name="credentials[token]"
                                type="password"
                                required
                                placeholder="glpat-xxxxxxxxxxxx"
                                autoFocus
                            />
                            <p className="text-sm text-muted-foreground">
                                Create a personal access token in{' '}
                                <a
                                    href="https://gitlab.com/-/profile/personal_access_tokens"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    GitLab Settings → Access Tokens
                                </a>
                                . Make sure to select the <strong>api</strong>{' '}
                                scope for full repository and webhook access.
                            </p>
                        </div>
                        <div className="grid space-y-2">
                            <Label htmlFor="url">GitLab URL (optional)</Label>
                            <Input
                                id="url"
                                name="credentials[url]"
                                type="url"
                                placeholder="https://gitlab.com"
                            />
                            <p className="text-sm text-muted-foreground">
                                Leave empty for GitLab.com, or enter your
                                self-hosted GitLab instance URL
                            </p>
                        </div>
                    </>
                );
            case 'bitbucket':
                return (
                    <>
                        <div className="grid space-y-2">
                            <Label htmlFor="email">
                                Atlassian Account Email{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="email"
                                name="credentials[email]"
                                type="email"
                                required
                                placeholder="you@example.com"
                                autoFocus
                            />
                            <p className="text-sm text-muted-foreground">
                                The email address associated with your
                                Atlassian account.
                            </p>
                        </div>
                        <div className="grid space-y-2">
                            <Label htmlFor="api_token">
                                API Token{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="api_token"
                                name="credentials[api_token]"
                                type="password"
                                required
                                placeholder="ATATxxxxxxxxxxxxxxxx"
                            />
                            <p className="text-sm text-muted-foreground">
                                Create an API token in{' '}
                                <a
                                    href="https://id.atlassian.com/manage-profile/security/api-tokens"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    Atlassian Account Settings &rarr; API
                                    tokens
                                </a>
                                . Select <strong>Bitbucket</strong> as the
                                app and assign{' '}
                                <strong>Repositories: Read</strong> and{' '}
                                <strong>Webhooks: Read and Write</strong>{' '}
                                permissions.
                            </p>
                        </div>
                    </>
                );
            default:
                return null;
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? `Edit ${providerLabel} Credentials`
                            : `Add ${providerLabel} Credentials`}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the credentials for this Git provider.'
                            : `Configure authentication for ${providerLabel} to enable repository syncing.`}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={
                        isEditing ? update.url(credential.uuid) : store.url()
                    }
                    method={isEditing ? 'patch' : 'post'}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {!isEditing && (
                                <input
                                    type="hidden"
                                    name="provider"
                                    value={provider}
                                />
                            )}

                            {getFormFields()}

                            {(errors.provider ||
                                errors['credentials.token'] ||
                                errors['credentials.email'] ||
                                errors['credentials.api_token'] ||
                                errors['credentials.url']) && (
                                <div className="rounded-md border border-destructive bg-destructive/10 p-3">
                                    <p className="font-medium text-destructive">
                                        Please fix the following errors:
                                    </p>
                                    <ul className="mt-1 list-inside list-disc text-destructive">
                                        {errors.provider && (
                                            <li>{errors.provider}</li>
                                        )}
                                        {errors['credentials.token'] && (
                                            <li>
                                                Token:{' '}
                                                {errors['credentials.token']}
                                            </li>
                                        )}
                                        {errors['credentials.email'] && (
                                            <li>
                                                Email:{' '}
                                                {errors['credentials.email']}
                                            </li>
                                        )}
                                        {errors['credentials.api_token'] && (
                                            <li>
                                                API Token:{' '}
                                                {
                                                    errors[
                                                        'credentials.api_token'
                                                    ]
                                                }
                                            </li>
                                        )}
                                        {errors['credentials.url'] && (
                                            <li>
                                                URL: {errors['credentials.url']}
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            )}

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? isEditing
                                            ? 'Updating...'
                                            : 'Adding...'
                                        : isEditing
                                          ? 'Update Credentials'
                                          : 'Add Credentials'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
