import { bulkStore } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import GitProviderIcon from '@/components/git-provider-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type RepositorySuggestion =
    App.Domains.Repository.Contracts.Data.RepositorySuggestionData;

const gitProviders: Record<string, string> = {
    github: 'GitHub',
    gitlab: 'GitLab',
    bitbucket: 'Bitbucket',
};

interface ImportRepositoriesDialogProps {
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
    configuredProviders: string[];
}

export default function ImportRepositoriesDialog({
    organizationSlug,
    isOpen,
    onClose,
    configuredProviders,
}: ImportRepositoriesDialogProps) {
    const supportedProviders = configuredProviders.filter(
        (p) => p in gitProviders,
    );
    const defaultProvider = supportedProviders[0] ?? '';

    const [provider, setProvider] = useState(defaultProvider);
    const [owners, setOwners] = useState<string[]>([]);
    const [selectedOwner, setSelectedOwner] = useState('');
    const [workspaceInput, setWorkspaceInput] = useState('');
    const [loadingOwners, setLoadingOwners] = useState(false);
    const [repositories, setRepositories] = useState<RepositorySuggestion[]>(
        [],
    );
    const [loadingRepos, setLoadingRepos] = useState(false);
    const [selectedRepos, setSelectedRepos] = useState<Set<string>>(new Set());
    const [searchQuery, setSearchQuery] = useState('');
    const [processing, setProcessing] = useState(false);

    // Bitbucket sunset its workspace enumeration API in CHANGE-2770, so the
    // user has to type their workspace slug instead of picking from a list.
    const isManualOwnerEntry = provider === 'bitbucket';
    const workspaceStorageKey = `pricore.bitbucket.workspace.${organizationSlug}`;

    const handleClose = () => {
        onClose();
    };

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setProvider(defaultProvider);
        setOwners([]);
        setSelectedOwner('');
        setWorkspaceInput('');
        setLoadingOwners(false);
        setRepositories([]);
        setLoadingRepos(false);
        setSelectedRepos(new Set());
        setSearchQuery('');
        setProcessing(false);
    }, [isOpen, defaultProvider]);

    useEffect(() => {
        if (!isOpen || !provider) {
            return;
        }

        if (isManualOwnerEntry) {
            const remembered =
                typeof window !== 'undefined'
                    ? window.localStorage.getItem(workspaceStorageKey) ?? ''
                    : '';
            setOwners([]);
            setSelectedOwner(remembered);
            setWorkspaceInput(remembered);
            setLoadingOwners(false);
            setRepositories([]);
            return;
        }

        const controller = new AbortController();

        const fetchOwners = async () => {
            setLoadingOwners(true);
            setOwners([]);
            setSelectedOwner('');
            setRepositories([]);
            try {
                const url = `/organizations/${organizationSlug}/repositories/owners?provider=${encodeURIComponent(provider)}`;
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (response.ok) {
                    const data = await response.json();
                    const fetchedOwners = data.owners || [];
                    setOwners(fetchedOwners);
                    if (fetchedOwners.length === 1) {
                        setSelectedOwner(fetchedOwners[0]);
                    }
                }
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoadingOwners(false);
                }
            }
        };

        fetchOwners();

        return () => controller.abort();
    }, [
        provider,
        organizationSlug,
        isOpen,
        isManualOwnerEntry,
        workspaceStorageKey,
    ]);

    useEffect(() => {
        if (!isOpen || !provider || !selectedOwner) {
            return;
        }

        const controller = new AbortController();

        const fetchRepositories = async () => {
            setLoadingRepos(true);
            setRepositories([]);
            setSelectedRepos(new Set());
            try {
                const url = `/organizations/${organizationSlug}/repositories/suggest?provider=${encodeURIComponent(provider)}&owner=${encodeURIComponent(selectedOwner)}`;
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (response.ok) {
                    const data = await response.json();
                    const fetched = data.repositories || [];
                    setRepositories(fetched);
                    if (
                        isManualOwnerEntry &&
                        fetched.length > 0 &&
                        typeof window !== 'undefined'
                    ) {
                        window.localStorage.setItem(
                            workspaceStorageKey,
                            selectedOwner,
                        );
                    }
                } else {
                    setRepositories([]);
                }
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
                setRepositories([]);
            } finally {
                if (!controller.signal.aborted) {
                    setLoadingRepos(false);
                }
            }
        };

        fetchRepositories();

        return () => controller.abort();
    }, [
        selectedOwner,
        provider,
        organizationSlug,
        isOpen,
        isManualOwnerEntry,
        workspaceStorageKey,
    ]);

    const filteredRepositories = repositories.filter((repo) => {
        if (!searchQuery) return true;
        const query = searchQuery.toLowerCase();
        return (
            repo.fullName.toLowerCase().includes(query) ||
            repo.name.toLowerCase().includes(query) ||
            (repo.description && repo.description.toLowerCase().includes(query))
        );
    });

    const selectableRepos = filteredRepositories.filter((r) => !r.isConnected);
    const allSelectableSelected =
        selectableRepos.length > 0 &&
        selectableRepos.every((r) => selectedRepos.has(r.fullName));

    const toggleRepo = (fullName: string) => {
        setSelectedRepos((prev) => {
            const next = new Set(prev);
            if (next.has(fullName)) {
                next.delete(fullName);
            } else {
                next.add(fullName);
            }
            return next;
        });
    };

    const toggleAll = () => {
        if (allSelectableSelected) {
            setSelectedRepos((prev) => {
                const next = new Set(prev);
                selectableRepos.forEach((r) => next.delete(r.fullName));
                return next;
            });
        } else {
            setSelectedRepos((prev) => {
                const next = new Set(prev);
                selectableRepos.forEach((r) => next.add(r.fullName));
                return next;
            });
        }
    };

    const handleSubmit = () => {
        if (selectedRepos.size === 0) return;

        setProcessing(true);

        const repositoriesPayload = Array.from(selectedRepos).map(
            (repoIdentifier) => ({
                repo_identifier: repoIdentifier,
            }),
        );

        router.post(
            bulkStore.url(organizationSlug),
            {
                provider,
                repositories: repositoriesPayload,
            },
            {
                onSuccess: () => handleClose(),
                onError: () => setProcessing(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Import Repositories</DialogTitle>
                    <DialogDescription>
                        Select multiple repositories to import at once. Packages
                        will be discovered automatically when versions are
                        synced.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <Select value={provider} onValueChange={setProvider}>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a provider" />
                        </SelectTrigger>
                        <SelectContent>
                            {supportedProviders.map((p) => (
                                <SelectItem key={p} value={p}>
                                    <GitProviderIcon
                                        provider={p}
                                        className="size-4"
                                    />
                                    {gitProviders[p]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {loadingOwners ? (
                        <div className="flex items-center gap-2 rounded-md border px-3 py-8">
                            <Spinner className="size-4" />
                            <span className="text-muted-foreground">
                                Loading...
                            </span>
                        </div>
                    ) : isManualOwnerEntry || owners.length > 0 ? (
                        <>
                            <div className="flex gap-2">
                                {isManualOwnerEntry ? (
                                    <form
                                        className="flex w-full flex-col gap-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            const trimmed =
                                                workspaceInput.trim();
                                            if (!trimmed) return;
                                            setSearchQuery('');
                                            setSelectedOwner(trimmed);
                                        }}
                                    >
                                        <div className="flex w-full gap-2">
                                            <Input
                                                placeholder="Bitbucket workspace slug (e.g. pricorephp)"
                                                value={workspaceInput}
                                                onChange={(e) =>
                                                    setWorkspaceInput(
                                                        e.target.value,
                                                    )
                                                }
                                                className="flex-1"
                                            />
                                            <Button
                                                type="submit"
                                                variant="secondary"
                                                size="lg"
                                                className="shrink-0"
                                                disabled={
                                                    !workspaceInput.trim() ||
                                                    workspaceInput.trim() ===
                                                        selectedOwner
                                                }
                                            >
                                                Load
                                            </Button>
                                        </div>
                                        <Input
                                            placeholder="Search repositories..."
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                            disabled={!selectedOwner}
                                        />
                                    </form>
                                ) : (
                                    <>
                                        <Select
                                            value={selectedOwner}
                                            onValueChange={(value) => {
                                                setSelectedOwner(value);
                                                setSearchQuery('');
                                            }}
                                        >
                                            <SelectTrigger className="w-[180px] shrink-0">
                                                <SelectValue placeholder="Select owner" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {owners.map((owner) => (
                                                    <SelectItem
                                                        key={owner}
                                                        value={owner}
                                                    >
                                                        {owner}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            placeholder="Search repositories..."
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                            disabled={!selectedOwner}
                                        />
                                    </>
                                )}
                            </div>

                            {loadingRepos ? (
                                <div className="flex items-center gap-2 rounded-md border px-3 py-8">
                                    <Spinner className="size-4" />
                                    <span className="text-muted-foreground">
                                        Loading repositories...
                                    </span>
                                </div>
                            ) : selectedOwner && repositories.length > 0 ? (
                                <>
                                    <div className="flex items-center justify-between text-sm">
                                        <button
                                            type="button"
                                            className="text-primary hover:underline"
                                            onClick={toggleAll}
                                        >
                                            {allSelectableSelected
                                                ? 'Deselect all'
                                                : 'Select all'}
                                        </button>
                                        <span className="text-muted-foreground">
                                            {selectedRepos.size} selected
                                        </span>
                                    </div>

                                    <div className="max-h-72 space-y-1 overflow-y-auto rounded-md border p-2">
                                        {filteredRepositories.length > 0 ? (
                                            filteredRepositories.map((repo) => (
                                                <label
                                                    key={repo.fullName}
                                                    className={`flex items-start gap-3 rounded-md p-2 ${
                                                        repo.isConnected
                                                            ? 'cursor-default opacity-60'
                                                            : 'cursor-pointer hover:bg-accent'
                                                    }`}
                                                >
                                                    <Checkbox
                                                        checked={
                                                            repo.isConnected ||
                                                            selectedRepos.has(
                                                                repo.fullName,
                                                            )
                                                        }
                                                        disabled={
                                                            repo.isConnected
                                                        }
                                                        onCheckedChange={() =>
                                                            toggleRepo(
                                                                repo.fullName,
                                                            )
                                                        }
                                                        className="mt-0.5"
                                                    />
                                                    <div className="flex flex-1 flex-col">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">
                                                                {repo.fullName}
                                                            </span>
                                                            {repo.isConnected && (
                                                                <Badge variant="secondary">
                                                                    Connected
                                                                </Badge>
                                                            )}
                                                            {repo.isPrivate &&
                                                                !repo.isConnected && (
                                                                    <Badge variant="outline">
                                                                        Private
                                                                    </Badge>
                                                                )}
                                                        </div>
                                                        {repo.description && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    repo.description
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </label>
                                            ))
                                        ) : (
                                            <div className="px-2 py-4 text-center text-muted-foreground">
                                                No repositories found
                                            </div>
                                        )}
                                    </div>
                                </>
                            ) : selectedOwner && repositories.length === 0 ? (
                                <div className="px-2 py-4 text-center text-muted-foreground">
                                    No repositories found for this owner.
                                </div>
                            ) : isManualOwnerEntry && !selectedOwner ? (
                                <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                                    Enter a Bitbucket workspace slug to load
                                    its repositories. You can find it in the
                                    Bitbucket URL: bitbucket.org/{'{'}
                                    workspace{'}'}/...
                                </div>
                            ) : null}
                        </>
                    ) : (
                        <div className="rounded-md border border-destructive bg-destructive/10 p-3 text-destructive">
                            No repositories found. Please configure your Git
                            credentials in settings.
                        </div>
                    )}
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
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing || selectedRepos.size === 0}
                    >
                        {processing ? (
                            <>
                                <Spinner className="size-4" />
                                Importing...
                            </>
                        ) : selectedRepos.size > 0 ? (
                            `Import ${selectedRepos.size} ${selectedRepos.size === 1 ? 'Repository' : 'Repositories'}`
                        ) : (
                            'Import Repositories'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
