import { bulkStore } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
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
    const [repositories, setRepositories] = useState<RepositorySuggestion[]>(
        [],
    );
    const [loadingRepos, setLoadingRepos] = useState(false);
    const [selectedRepos, setSelectedRepos] = useState<Set<string>>(new Set());
    const [searchQuery, setSearchQuery] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        onClose();
    };

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setProvider(defaultProvider);
        setSelectedRepos(new Set());
        setSearchQuery('');
        setProcessing(false);
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen || !provider) {
            return;
        }

        const fetchRepositories = async () => {
            setLoadingRepos(true);
            try {
                const url = `/organizations/${organizationSlug}/repositories/suggest?provider=${encodeURIComponent(provider)}`;
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
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
                    {supportedProviders.length > 1 && (
                        <Select value={provider} onValueChange={setProvider}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a provider" />
                            </SelectTrigger>
                            <SelectContent>
                                {supportedProviders.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {gitProviders[p]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {loadingRepos ? (
                        <div className="flex items-center gap-2 rounded-md border px-3 py-8">
                            <Spinner className="size-4" />
                            <span className="text-muted-foreground">
                                Loading repositories...
                            </span>
                        </div>
                    ) : repositories.length > 0 ? (
                        <>
                            <Input
                                placeholder="Search repositories..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />

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
                                                disabled={repo.isConnected}
                                                onCheckedChange={() =>
                                                    toggleRepo(repo.fullName)
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
                                                        {repo.description}
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
