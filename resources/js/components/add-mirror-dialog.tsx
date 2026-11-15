import { store } from '@/actions/App/Domains/Mirror/Http/Controllers/MirrorController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Form } from '@inertiajs/react';
import { Boxes, ChevronLeft, ChevronRight, Server } from 'lucide-react';
import { type ComponentType, type ReactNode, useState } from 'react';

type MirrorSyncMode = App.Domains.Mirror.Contracts.Enums.MirrorSyncMode;

type Step = 'source' | 'packagist' | 'custom';

export const PACKAGIST_URL = 'https://repo.packagist.org';

export function isPackagistUrl(url: string): boolean {
    try {
        const host = new URL(url).hostname;

        return host === 'repo.packagist.org' || host === 'packagist.org';
    } catch {
        return false;
    }
}

export function AddMirrorDialog({
    organizationSlug,
    hasPackagistMirror,
    isOpen,
    onClose,
}: {
    organizationSlug: string;
    hasPackagistMirror: boolean;
    isOpen: boolean;
    onClose: () => void;
}) {
    const [step, setStep] = useState<Step>('source');

    const close = () => {
        onClose();
        setStep('source');
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && close()}>
            <DialogContent>
                {step === 'source' && (
                    <SourceStep
                        hasPackagistMirror={hasPackagistMirror}
                        onSelect={setStep}
                    />
                )}
                {step === 'packagist' && (
                    <PackagistStep
                        organizationSlug={organizationSlug}
                        onBack={() => setStep('source')}
                        onDone={close}
                    />
                )}
                {step === 'custom' && (
                    <CustomRegistryStep
                        organizationSlug={organizationSlug}
                        onBack={() => setStep('source')}
                        onDone={close}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function SourceStep({
    hasPackagistMirror,
    onSelect,
}: {
    hasPackagistMirror: boolean;
    onSelect: (step: Step) => void;
}) {
    return (
        <>
            <DialogHeader>
                <DialogTitle>Add Registry Mirror</DialogTitle>
                <DialogDescription>
                    Choose where the packages come from.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <SourceOption
                    icon={Boxes}
                    title="Packagist"
                    description="Every public PHP package, imported the first time your projects need it."
                    meta="repo.packagist.org"
                    disabled={hasPackagistMirror}
                    badge={
                        hasPackagistMirror ? (
                            <Badge variant="secondary">Added</Badge>
                        ) : null
                    }
                    onSelect={() => onSelect('packagist')}
                />
                <SourceOption
                    icon={Server}
                    title="Other registry"
                    description="A Satis instance, a vendor's private registry or any other Composer repository."
                    meta="…/packages.json"
                    onSelect={() => onSelect('custom')}
                />
            </div>
        </>
    );
}

function SourceOption({
    icon: Icon,
    title,
    description,
    meta,
    badge,
    disabled = false,
    onSelect,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
    meta: string;
    badge?: ReactNode;
    disabled?: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={disabled}
            className={cn(
                'group flex w-full items-center gap-4 rounded-lg border bg-card p-4 text-left transition-colors',
                'hover:border-foreground/15 hover:bg-accent/40',
                'focus-visible:border-ring focus-visible:ring-4 focus-visible:ring-ring/20 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-60',
            )}
        >
            <div className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-surface-inset transition-colors group-hover:text-primary">
                <Icon className="size-5" />
            </div>
            <div className="min-w-0 flex-1 space-y-0.5">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{title}</span>
                    {badge}
                </div>
                <p className="text-muted-foreground">{description}</p>
                <p className="font-mono text-xs text-muted-foreground/80">
                    {meta}
                </p>
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-muted-foreground" />
        </button>
    );
}

function StepHeader({
    title,
    description,
    onBack,
}: {
    title: string;
    description: string;
    onBack: () => void;
}) {
    return (
        <DialogHeader>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="-ml-2 size-7"
                    onClick={onBack}
                    aria-label="Back to sources"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <DialogTitle>{title}</DialogTitle>
            </div>
            <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
    );
}

const packagistFacts: { term: string; detail: ReactNode }[] = [
    {
        term: 'Source',
        detail: <span className="font-mono">repo.packagist.org</span>,
    },
    {
        term: 'Packages',
        detail: 'Imported on first use',
    },
    {
        term: 'Updates',
        detail: 'Checked when used and every 4 hours',
    },
    {
        term: 'Access',
        detail: 'Public, no credentials',
    },
];

function PackagistStep({
    organizationSlug,
    onBack,
    onDone,
}: {
    organizationSlug: string;
    onBack: () => void;
    onDone: () => void;
}) {
    const [mirrorDist, setMirrorDist] = useState(true);

    return (
        <>
            <StepHeader
                title="Mirror Packagist"
                description="Serve the public packages your projects use from Pricore, next to your private ones."
                onBack={onBack}
            />

            <Form
                action={store.url(organizationSlug)}
                method="post"
                onSuccess={onDone}
                className="space-y-5"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="name" value="Packagist" />
                        <input type="hidden" name="url" value={PACKAGIST_URL} />
                        <input
                            type="hidden"
                            name="sync_mode"
                            value={'on_demand' satisfies MirrorSyncMode}
                        />
                        <input type="hidden" name="auth_type" value="none" />

                        <dl className="divide-y rounded-lg border bg-surface-inset">
                            {packagistFacts.map(({ term, detail }) => (
                                <div
                                    key={term}
                                    className="grid grid-cols-[5.5rem_1fr] gap-3 px-4 py-2.5"
                                >
                                    <dt className="text-muted-foreground">
                                        {term}
                                    </dt>
                                    <dd>{detail}</dd>
                                </div>
                            ))}
                        </dl>

                        <div className="flex items-start gap-2.5">
                            <input type="hidden" name="mirror_dist" value="0" />
                            <Checkbox
                                id="packagist_mirror_dist"
                                name="mirror_dist"
                                value="1"
                                checked={mirrorDist}
                                onCheckedChange={(checked) =>
                                    setMirrorDist(checked === true)
                                }
                                className="mt-0.5"
                            />
                            <div className="grid gap-1">
                                <Label
                                    htmlFor="packagist_mirror_dist"
                                    className="font-normal"
                                >
                                    Store archives in Pricore
                                </Label>
                                <p className="text-muted-foreground">
                                    {mirrorDist
                                        ? 'Downloaded on first install, then served from Pricore. GitHub allows 60 downloads an hour without a token.'
                                        : 'Composer downloads archives from GitHub itself.'}
                                </p>
                            </div>
                        </div>

                        <FormErrors errors={errors} />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onBack}
                            >
                                Back
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Adding...' : 'Add Packagist'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </>
    );
}

function FormErrors({ errors }: { errors: Record<string, string> }) {
    const messages = Object.values(errors);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1">
            {messages.map((message) => (
                <p key={message} className="text-destructive">
                    {message}
                </p>
            ))}
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-destructive">{message}</p> : null;
}

function CustomRegistryStep({
    organizationSlug,
    onBack,
    onDone,
}: {
    organizationSlug: string;
    onBack: () => void;
    onDone: () => void;
}) {
    const [authType, setAuthType] = useState('none');
    const [mirrorDist, setMirrorDist] = useState(true);
    const [syncMode, setSyncMode] = useState<MirrorSyncMode>('full');

    return (
        <>
            <StepHeader
                title="Add Registry Mirror"
                description="The registry must serve a packages.json file."
                onBack={onBack}
            />

            <Form
                action={store.url(organizationSlug)}
                method="post"
                onSuccess={onDone}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid space-y-2">
                            <Label htmlFor="name">
                                Name <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                placeholder="e.g., Internal Satis"
                            />
                            <FieldError message={errors.name} />
                        </div>

                        <div className="grid space-y-2">
                            <Label htmlFor="url">
                                Registry URL{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="url"
                                name="url"
                                required
                                placeholder="https://satis.example.com"
                            />
                            <FieldError message={errors.url} />
                        </div>

                        <div className="grid space-y-2">
                            <Label htmlFor="sync_mode">Packages</Label>
                            <Select
                                name="sync_mode"
                                value={syncMode}
                                onValueChange={(value) =>
                                    setSyncMode(value as MirrorSyncMode)
                                }
                            >
                                <SelectTrigger id="sync_mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="full">
                                        All packages
                                    </SelectItem>
                                    <SelectItem value="on_demand">
                                        On demand
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground">
                                {syncMode === 'on_demand'
                                    ? 'Packages are imported when Composer first asks for them.'
                                    : 'Every package the registry lists is imported on each sync.'}
                            </p>
                            <FieldError message={errors.sync_mode} />
                        </div>

                        <div className="grid space-y-2">
                            <Label htmlFor="auth_type">Authentication</Label>
                            <Select
                                name="auth_type"
                                value={authType}
                                onValueChange={setAuthType}
                            >
                                <SelectTrigger id="auth_type">
                                    <SelectValue placeholder="Select authentication type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    <SelectItem value="basic">
                                        HTTP Basic
                                    </SelectItem>
                                    <SelectItem value="bearer">
                                        Bearer Token
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FieldError message={errors.auth_type} />
                        </div>

                        {authType === 'basic' && (
                            <>
                                <div className="grid space-y-2">
                                    <Label htmlFor="username">
                                        Username{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="username"
                                        name="username"
                                        required
                                    />
                                    <FieldError message={errors.username} />
                                </div>
                                <div className="grid space-y-2">
                                    <Label htmlFor="password">
                                        Password{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        required
                                    />
                                    <FieldError message={errors.password} />
                                </div>
                            </>
                        )}

                        {authType === 'bearer' && (
                            <div className="grid space-y-2">
                                <Label htmlFor="token">
                                    Token{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="token"
                                    name="token"
                                    type="password"
                                    required
                                />
                                <FieldError message={errors.token} />
                            </div>
                        )}

                        <div className="flex items-center space-x-2">
                            <input type="hidden" name="mirror_dist" value="0" />
                            <Checkbox
                                id="mirror_dist"
                                name="mirror_dist"
                                checked={mirrorDist}
                                onCheckedChange={(checked) =>
                                    setMirrorDist(checked === true)
                                }
                                value="1"
                            />
                            <Label
                                htmlFor="mirror_dist"
                                className="font-normal"
                            >
                                Mirror dist archives
                            </Label>
                        </div>

                        {mirrorDist && syncMode === 'full' && (
                            <div className="flex items-start space-x-2 pl-6">
                                <input
                                    type="hidden"
                                    name="lazy_dist"
                                    value="0"
                                />
                                <Checkbox
                                    id="lazy_dist"
                                    name="lazy_dist"
                                    value="1"
                                    className="mt-0.5"
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="lazy_dist"
                                        className="font-normal"
                                    >
                                        Download archives on first install
                                    </Label>
                                    <p className="text-muted-foreground">
                                        Archives are fetched from the upstream
                                        registry when a version is first
                                        installed, instead of during every sync.
                                    </p>
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onBack}
                            >
                                Back
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Adding...' : 'Add Mirror'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </>
    );
}
