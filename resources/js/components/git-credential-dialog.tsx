import {
    store,
    update,
} from '@/actions/App/Domains/Organization/Http/Controllers/GitCredentialController';
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
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import { useState } from 'react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialDialogProps {
    organizationSlug: string;
    organizationName?: string;
    credential?: GitCredentialData | null;
    provider: string;
    providers: Record<string, string>;
    hasGitHubConnected?: boolean;
    isOpen: boolean;
    onClose: () => void;
}

export default function GitCredentialDialog({
    organizationSlug,
    organizationName,
    credential,
    provider,
    providers,
    hasGitHubConnected,
    isOpen,
    onClose,
}: GitCredentialDialogProps) {
    const isEditing = !!credential;
    const providerLabel = providers[provider] || provider;
    const [useOAuth, setUseOAuth] = useState(false);
    const showOAuthOption =
        provider === 'github' && hasGitHubConnected && !isEditing;

    const getGitHubTokenUrl = (): string => {
        const description = organizationName
            ? `Pricore (${organizationName})`
            : 'Pricore';
        const encodedDescription = encodeURIComponent(description);
        const scopes = 'repo,read:org';
        return `https://github.com/settings/tokens/new?description=${encodedDescription}&scopes=${scopes}`;
    };

    const getFormFields = () => {
        switch (provider) {
            case 'github':
                return (
                    <>
                        {showOAuthOption && (
                            <>
                                <div className="grid space-y-2">
                                    <Button
                                        type={useOAuth ? 'button' : 'button'}
                                        variant={
                                            useOAuth ? 'default' : 'secondary'
                                        }
                                        className="w-full"
                                        onClick={() => setUseOAuth(true)}
                                    >
                                        <svg
                                            className="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                        >
                                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                                        </svg>
                                        Use my GitHub account
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Uses the token from your connected
                                        GitHub account. The token will be
                                        automatically refreshed when you sign in
                                        via GitHub.
                                    </p>
                                </div>

                                {useOAuth && (
                                    <input
                                        type="hidden"
                                        name="source"
                                        value="oauth"
                                    />
                                )}

                                {!useOAuth && (
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
                                )}
                            </>
                        )}

                        {!useOAuth && (
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
                                <p className="text-xs text-muted-foreground">
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
                                    . Make sure to select the{' '}
                                    <strong>repo</strong> scope (and{' '}
                                    <strong>read:org</strong> if accessing
                                    organization repositories).
                                </p>
                            </div>
                        )}
                    </>
                );
            case 'gitlab':
                return (
                    <>
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
                            <p className="text-xs text-muted-foreground">
                                Create a personal access token in{' '}
                                <a
                                    href="https://gitlab.com/-/profile/personal_access_tokens"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    GitLab Settings → Access Tokens
                                </a>
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
                            <p className="text-xs text-muted-foreground">
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
                            <Label htmlFor="username">
                                Username <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="username"
                                name="credentials[username]"
                                required
                                placeholder="your-username"
                                autoFocus
                            />
                        </div>
                        <div className="grid space-y-2">
                            <Label htmlFor="app_password">
                                App Password{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="app_password"
                                name="credentials[app_password]"
                                type="password"
                                required
                                placeholder="xxxxxxxxxxxx"
                            />
                            <p className="text-xs text-muted-foreground">
                                Create an app password in{' '}
                                <a
                                    href="https://bitbucket.org/account/settings/app-passwords/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    Bitbucket Settings → Personal settings → App
                                    passwords
                                </a>
                            </p>
                        </div>
                    </>
                );
            case 'git':
                return (
                    <>
                        <div className="grid space-y-2">
                            <Label htmlFor="ssh_key">
                                SSH Private Key{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Textarea
                                id="ssh_key"
                                name="credentials[ssh_key]"
                                required
                                rows={8}
                                placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"
                                autoFocus
                            />
                            <p className="text-xs text-muted-foreground">
                                Paste your SSH private key. Make sure the
                                corresponding public key is added to your Git
                                server. Learn how to{' '}
                                <a
                                    href="https://docs.github.com/en/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    generate an SSH key
                                </a>
                                .
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
                        isEditing
                            ? update.url([organizationSlug, credential.uuid])
                            : store.url(organizationSlug)
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
                                errors['credentials.username'] ||
                                errors['credentials.app_password'] ||
                                errors['credentials.ssh_key'] ||
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
                                        {errors['credentials.username'] && (
                                            <li>
                                                Username:{' '}
                                                {errors['credentials.username']}
                                            </li>
                                        )}
                                        {errors['credentials.app_password'] && (
                                            <li>
                                                App Password:{' '}
                                                {
                                                    errors[
                                                        'credentials.app_password'
                                                    ]
                                                }
                                            </li>
                                        )}
                                        {errors['credentials.ssh_key'] && (
                                            <li>
                                                SSH Key:{' '}
                                                {errors['credentials.ssh_key']}
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
