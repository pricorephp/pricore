import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Settings/UserGitCredentialController';
import GitProviderIcon from '@/components/git-provider-icon';
import InputError from '@/components/input-error';
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
import { type ReactNode } from 'react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialDialogProps {
    credential?: GitCredentialData | null;
    provider: string;
    providers: Record<string, string>;
    githubConnectUrl?: string;
    gitlabConnectUrl?: string;
    gitlabInstanceHost?: string;
    isOpen: boolean;
    onClose: () => void;
}

const githubTokenUrl =
    'https://github.com/settings/tokens/new?description=Pricore&scopes=repo,read:org';

function ExternalLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary hover:underline"
        >
            {children}
        </a>
    );
}

function Field({
    id,
    label,
    required = false,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: ReactNode;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {!required && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (optional)
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function GitCredentialDialog({
    credential,
    provider,
    providers,
    githubConnectUrl,
    gitlabConnectUrl,
    gitlabInstanceHost,
    isOpen,
    onClose,
}: GitCredentialDialogProps) {
    const isEditing = !!credential;
    const providerLabel = providers[provider] || provider;
    const oauthUrls: Record<string, string | undefined> = {
        github: githubConnectUrl,
        gitlab: gitlabConnectUrl,
    };
    const oauthUrl = isEditing ? undefined : oauthUrls[provider];

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? `Edit ${credential.label}`
                            : `Connect ${providerLabel}`}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Enter the token again to save changes. Repositories using this account keep using it.'
                            : `Pricore uses this account to read your ${providerLabel} repositories and register webhooks.`}
                    </DialogDescription>
                </DialogHeader>

                {oauthUrl && (
                    <>
                        <div className="grid gap-2">
                            <Button asChild className="w-full">
                                <a href={oauthUrl}>
                                    <GitProviderIcon
                                        provider={provider}
                                        className="size-4"
                                    />
                                    Continue with {providerLabel}
                                </a>
                            </Button>
                            <p className="text-center text-sm text-muted-foreground">
                                Sign in to{' '}
                                {provider === 'gitlab' && gitlabInstanceHost
                                    ? gitlabInstanceHost
                                    : providerLabel}{' '}
                                and authorize Pricore. Connecting another
                                account adds it next to the ones you have.
                            </p>
                        </div>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <Separator />
                            </div>
                            <div className="relative flex justify-center text-xs uppercase">
                                <span className="bg-background px-2 text-muted-foreground">
                                    or use an access token
                                </span>
                            </div>
                        </div>
                    </>
                )}

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

                            {provider === 'github' && (
                                <Field
                                    id="token"
                                    label="Personal access token"
                                    required
                                    error={errors['credentials.token']}
                                    hint={
                                        <>
                                            Create a{' '}
                                            <ExternalLink href={githubTokenUrl}>
                                                classic token
                                            </ExternalLink>{' '}
                                            with the <strong>repo</strong> and{' '}
                                            <strong>read:org</strong> scopes, or
                                            a fine-grained token with read
                                            access to Contents and write access
                                            to Webhooks.
                                        </>
                                    }
                                >
                                    <Input
                                        id="token"
                                        name="credentials[token]"
                                        type="password"
                                        required
                                        placeholder="ghp_…"
                                        autoFocus={!oauthUrl}
                                    />
                                </Field>
                            )}

                            {provider === 'gitlab' && (
                                <>
                                    <Field
                                        id="url"
                                        label="Instance URL"
                                        error={errors['credentials.url']}
                                        hint="Leave empty for GitLab.com."
                                    >
                                        <Input
                                            id="url"
                                            name="credentials[url]"
                                            type="url"
                                            defaultValue={
                                                credential?.baseUrl ?? ''
                                            }
                                            placeholder="https://gitlab.com"
                                        />
                                    </Field>
                                    <Field
                                        id="token"
                                        label="Personal access token"
                                        required
                                        error={errors['credentials.token']}
                                        hint={
                                            <>
                                                Create one under{' '}
                                                <strong>
                                                    Preferences → Access tokens
                                                </strong>{' '}
                                                on your instance with the{' '}
                                                <strong>api</strong> scope.
                                            </>
                                        }
                                    >
                                        <Input
                                            id="token"
                                            name="credentials[token]"
                                            type="password"
                                            required
                                            placeholder="glpat-…"
                                        />
                                    </Field>
                                </>
                            )}

                            {provider === 'bitbucket' && (
                                <>
                                    <Field
                                        id="email"
                                        label="Atlassian account email"
                                        required
                                        error={errors['credentials.email']}
                                    >
                                        <Input
                                            id="email"
                                            name="credentials[email]"
                                            type="email"
                                            required
                                            placeholder="you@example.com"
                                            autoFocus
                                        />
                                    </Field>
                                    <Field
                                        id="api_token"
                                        label="API token"
                                        required
                                        error={errors['credentials.api_token']}
                                        hint={
                                            <>
                                                In{' '}
                                                <ExternalLink href="https://id.atlassian.com/manage-profile/security/api-tokens">
                                                    Atlassian account settings
                                                </ExternalLink>
                                                , choose{' '}
                                                <strong>
                                                    Create API token with scopes
                                                </strong>
                                                , pick Bitbucket and enable{' '}
                                                <strong>
                                                    read:user:bitbucket,
                                                    read:repository:bitbucket,
                                                    read:workspace:bitbucket,
                                                    read:webhook:bitbucket
                                                </strong>{' '}
                                                and{' '}
                                                <strong>
                                                    write:webhook:bitbucket
                                                </strong>
                                                .
                                            </>
                                        }
                                    >
                                        <Input
                                            id="api_token"
                                            name="credentials[api_token]"
                                            type="password"
                                            required
                                            placeholder="ATATT…"
                                        />
                                    </Field>
                                </>
                            )}

                            <Field
                                id="name"
                                label="Name"
                                error={errors.name}
                                hint="Shown when you pick an account for a repository."
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    maxLength={255}
                                    defaultValue={credential?.name ?? ''}
                                    placeholder={`e.g. Work ${providerLabel}`}
                                />
                            </Field>

                            <InputError message={errors.provider} />

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {isEditing ? 'Save' : 'Add account'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
