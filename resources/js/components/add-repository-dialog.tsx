import { store } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Form, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface AddRepositoryDialogProps {
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
    configuredProviders?: string[];
}

const gitProviders = {
    github: 'GitHub',
    gitlab: 'GitLab',
    bitbucket: 'Bitbucket',
    git: 'Generic Git',
};

type RepositorySuggestion =
    App.Domains.Repository.Contracts.Data.RepositorySuggestionData;

export default function AddRepositoryDialog({
    organizationSlug,
    isOpen,
    onClose,
    configuredProviders = [],
}: AddRepositoryDialogProps) {
    const availableProviders =
        configuredProviders.length > 0
            ? Object.fromEntries(
                  Object.entries(gitProviders).filter(
                      ([key]) =>
                          configuredProviders.includes(key) || key === 'git',
                  ),
              )
            : { git: gitProviders.git };

    const defaultProvider =
        configuredProviders.length > 0 ? configuredProviders[0] : 'git';

    const [provider, setProvider] = useState<string>(defaultProvider);
    const [repositories, setRepositories] = useState<RepositorySuggestion[]>(
        [],
    );
    const [loadingRepos, setLoadingRepos] = useState(false);
    const [selectedRepo, setSelectedRepo] = useState<string>('');
    const [repoIdentifier, setRepoIdentifier] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const repoListContainerRef = useRef<HTMLDivElement>(null);

    const handleClose = (): void => {
        onClose();
    };

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setProvider(defaultProvider);
        setSelectedRepo('');
        setRepoIdentifier('');
        setSearchQuery('');
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen || provider === 'git') {
            return;
        }

        const fetchRepositories = async (): Promise<void> => {
            setLoadingRepos(true);
            try {
                const url = `/organizations/${organizationSlug}/repositories/suggest?provider=${encodeURIComponent(provider)}`;
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    const data = await response.json();
                    setRepositories(data.repositories || []);
                } else {
                    setRepositories([]);
                }
            } catch {
                setRepositories([]);
            } finally {
                setLoadingRepos(false);
            }
        };

        fetchRepositories();
    }, [provider, organizationSlug, isOpen]);

    const handleRepoSelect = (
        fullName: string,
        event?: React.ChangeEvent<HTMLInputElement>,
    ): void => {
        if (event) {
            event.preventDefault();
        }
        setSelectedRepo(fullName);
        setRepoIdentifier(fullName);
        // Don't clear search query to maintain scroll position
    };

    const filteredRepositories = repositories.filter((repo) => {
        if (!searchQuery) {
            return true;
        }
        const query = searchQuery.toLowerCase();
        return (
            repo.fullName.toLowerCase().includes(query) ||
            repo.name.toLowerCase().includes(query) ||
            (repo.description && repo.description.toLowerCase().includes(query))
        );
    });

    const getRepoIdentifierPlaceholder = (): string => {
        switch (provider) {
            case 'github':
                return 'owner/repo';
            case 'gitlab':
                return 'owner/repo';
            case 'bitbucket':
                return 'owner/repo';
            case 'git':
                return 'https://example.com/repo.git';
            default:
                return 'owner/repo';
        }
    };

    const getRepoIdentifierHelp = (): string => {
        switch (provider) {
            case 'github':
                return 'Enter the repository identifier in the format "owner/repo" (e.g., "laravel/laravel")';
            case 'gitlab':
                return 'Enter the repository identifier in the format "owner/repo" (e.g., "gitlab-org/gitlab")';
            case 'bitbucket':
                return 'Enter the repository identifier in the format "owner/repo" (e.g., "atlassian/bitbucket")';
            case 'git':
                return 'Enter the full Git repository URL (e.g., "https://example.com/repo.git")';
            default:
                return '';
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Repository</DialogTitle>
                    <DialogDescription>
                        Connect a Git repository to automatically discover and
                        sync Composer packages. Packages will be created
                        automatically when versions are synced.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={store.url(organizationSlug)}
                    method="post"
                    onSuccess={handleClose}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors, wasSuccessful }) => (
                        <>
                            <div className="grid space-y-2">
                                <Label htmlFor="provider">
                                    Git Provider{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    name="provider"
                                    defaultValue={provider}
                                    onValueChange={setProvider}
                                    required
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(availableProviders).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.provider &&
                                    !processing &&
                                    !wasSuccessful && (
                                        <p className="text-destructive">
                                            {errors.provider}
                                        </p>
                                    )}
                                <p className="text-sm text-muted-foreground">
                                    Need to add more providers? Configure them
                                    in{' '}
                                    <Link
                                        href="/settings/git-credentials"
                                        className="font-medium text-primary underline underline-offset-4 hover:no-underline"
                                    >
                                        your settings
                                    </Link>
                                    .
                                </p>
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="repo_identifier">
                                    Repository{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                {loadingRepos && provider === 'github' ? (
                                    <div className="flex items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2">
                                        <Spinner className="size-4" />
                                        <span className="text-muted-foreground">
                                            Loading repositories...
                                        </span>
                                    </div>
                                ) : provider === 'github' &&
                                  repositories.length > 0 ? (
                                    <>
                                        <div className="space-y-3">
                                            <Input
                                                placeholder="Search repositories..."
                                                value={searchQuery}
                                                onChange={(e) =>
                                                    setSearchQuery(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <div
                                                ref={repoListContainerRef}
                                                className="max-h-64 space-y-2 overflow-y-auto rounded-md border p-2"
                                            >
                                                {filteredRepositories.length >
                                                0 ? (
                                                    filteredRepositories.map(
                                                        (repo) => {
                                                            const handleLabelClick =
                                                                (
                                                                    e: React.MouseEvent<HTMLLabelElement>,
                                                                ): void => {
                                                                    // If clicking directly on the radio button, let it handle it
                                                                    if (
                                                                        e.target instanceof
                                                                        HTMLInputElement
                                                                    ) {
                                                                        return;
                                                                    }
                                                                    // Otherwise, programmatically trigger the radio button
                                                                    e.preventDefault();
                                                                    const scrollTop =
                                                                        repoListContainerRef
                                                                            .current
                                                                            ?.scrollTop ??
                                                                        0;
                                                                    handleRepoSelect(
                                                                        repo.fullName,
                                                                    );
                                                                    // Restore scroll position after state update
                                                                    setTimeout(
                                                                        () => {
                                                                            repoListContainerRef.current?.scrollTo(
                                                                                {
                                                                                    top: scrollTop,
                                                                                    behavior:
                                                                                        'instant',
                                                                                },
                                                                            );
                                                                        },
                                                                        0,
                                                                    );
                                                                };

                                                            return (
                                                                <label
                                                                    key={
                                                                        repo.fullName
                                                                    }
                                                                    onClick={
                                                                        handleLabelClick
                                                                    }
                                                                    className="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-accent"
                                                                >
                                                                    <input
                                                                        type="radio"
                                                                        name="selected_repo"
                                                                        value={
                                                                            repo.fullName
                                                                        }
                                                                        checked={
                                                                            selectedRepo ===
                                                                            repo.fullName
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const scrollTop =
                                                                                repoListContainerRef
                                                                                    .current
                                                                                    ?.scrollTop ??
                                                                                0;
                                                                            handleRepoSelect(
                                                                                repo.fullName,
                                                                                e,
                                                                            );
                                                                            // Restore scroll position after state update
                                                                            setTimeout(
                                                                                () => {
                                                                                    repoListContainerRef.current?.scrollTo(
                                                                                        {
                                                                                            top: scrollTop,
                                                                                            behavior:
                                                                                                'instant',
                                                                                        },
                                                                                    );
                                                                                },
                                                                                0,
                                                                            );
                                                                        }}
                                                                        className="mt-1 size-4"
                                                                        required
                                                                    />
                                                                    <div className="flex flex-1 flex-col">
                                                                        <span className="font-medium">
                                                                            {
                                                                                repo.fullName
                                                                            }
                                                                        </span>
                                                                        {repo.description && (
                                                                            <span className="text-xs text-muted-foreground">
                                                                                {
                                                                                    repo.description
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </label>
                                                            );
                                                        },
                                                    )
                                                ) : (
                                                    <div className="px-2 py-4 text-center text-muted-foreground">
                                                        No repositories found
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <input
                                            type="hidden"
                                            name="repo_identifier"
                                            value={repoIdentifier}
                                            required
                                        />
                                    </>
                                ) : provider === 'github' &&
                                  repositories.length === 0 ? (
                                    <>
                                        <div className="rounded-md border border-destructive bg-destructive/10 p-3 text-destructive">
                                            No repositories found. Please
                                            configure GitHub credentials in
                                            settings.
                                        </div>
                                        <input
                                            type="hidden"
                                            name="repo_identifier"
                                            value=""
                                            required
                                        />
                                    </>
                                ) : (
                                    <>
                                        <Input
                                            id="repo_identifier"
                                            name="repo_identifier"
                                            required
                                            placeholder={getRepoIdentifierPlaceholder()}
                                            value={repoIdentifier}
                                            onChange={(e) =>
                                                setRepoIdentifier(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {loadingRepos && provider !== 'git' && (
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Spinner className="size-3" />
                                                Loading repositories...
                                            </div>
                                        )}
                                        {!loadingRepos &&
                                            repositories.length === 0 &&
                                            provider !== 'git' && (
                                                <p className="text-xs text-muted-foreground">
                                                    {getRepoIdentifierHelp()}
                                                </p>
                                            )}
                                        {provider === 'git' && (
                                            <p className="text-xs text-muted-foreground">
                                                {getRepoIdentifierHelp()}
                                            </p>
                                        )}
                                    </>
                                )}
                                {errors.repo_identifier &&
                                    !processing &&
                                    !wasSuccessful && (
                                        <p className="text-destructive">
                                            {errors.repo_identifier}
                                        </p>
                                    )}
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="default_branch">
                                    Default Branch (optional)
                                </Label>
                                <Input
                                    id="default_branch"
                                    name="default_branch"
                                    placeholder="main"
                                />
                                {errors.default_branch &&
                                    !processing &&
                                    !wasSuccessful && (
                                        <p className="text-destructive">
                                            {errors.default_branch}
                                        </p>
                                    )}
                                <p className="text-sm text-muted-foreground">
                                    The default branch to sync from. If not
                                    specified, the repository's default branch
                                    will be used.
                                </p>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={handleClose}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (provider === 'github' &&
                                            (!selectedRepo ||
                                                repositories.length === 0))
                                    }
                                >
                                    {processing
                                        ? 'Adding...'
                                        : 'Add Repository'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
