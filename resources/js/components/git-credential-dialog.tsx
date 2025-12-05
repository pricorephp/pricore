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
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialDialogProps {
    organizationSlug: string;
    organizationName?: string;
    credential?: GitCredentialData | null;
    provider: string;
    providers: Record<string, string>;
    isOpen: boolean;
    onClose: () => void;
}

export default function GitCredentialDialog({
    organizationSlug,
    organizationName,
    credential,
    provider,
    providers,
    isOpen,
    onClose,
}: GitCredentialDialogProps) {
    const isEditing = !!credential;
    const providerLabel = providers[provider] || provider;

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
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                                    <p className="text-sm font-medium text-destructive">
                                        Please fix the following errors:
                                    </p>
                                    <ul className="mt-1 list-inside list-disc text-sm text-destructive">
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
                                    variant="outline"
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
