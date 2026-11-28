import { update } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import TagFormatPreviewController from '@/actions/App/Domains/Repository/Http/Controllers/TagFormatPreviewController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type TagFormatPreviewData =
    App.Domains.Repository.Contracts.Data.TagFormatPreviewData;

const SHARED = 'shared';
const CUSTOM = 'custom';

const presets = [
    { value: SHARED, label: 'Shared tags, such as v1.2.0', template: '' },
    {
        value: '{package}@{version}',
        label: 'Package name and version, such as billing@1.2.0',
        template: '{package}@{version}',
    },
    {
        value: '{path}/{version}',
        label: 'Directory and version, such as packages/billing/v1.2.0',
        template: '{path}/{version}',
    },
    {
        value: '{package}-{version}',
        label: 'Package name and -v, such as billing-v1.2.0',
        template: '{package}-{version}',
    },
    { value: CUSTOM, label: 'Custom format', template: null },
];

function presetFor(template: string): string {
    return (
        presets.find((preset) => preset.template === template)?.value ?? CUSTOM
    );
}

interface TagFormatCardProps {
    organizationSlug: string;
    repositoryUuid: string;
    tagFormat: string | null;
}

export default function TagFormatCard({
    organizationSlug,
    repositoryUuid,
    tagFormat,
}: TagFormatCardProps) {
    const [template, setTemplate] = useState(tagFormat ?? '');
    const [preset, setPreset] = useState(presetFor(tagFormat ?? ''));
    // Kept with the format it belongs to, so a preview for an older format is never shown
    const [previewResult, setPreviewResult] = useState<{
        template: string;
        tags: TagFormatPreviewData[] | null;
        error: string | null;
    } | null>(null);
    const previewable = template !== '' && template.includes('{version}');
    const current =
        previewable && previewResult?.template === template
            ? previewResult
            : null;
    const preview = current?.tags ?? null;
    const previewError = current?.error ?? null;

    const routeArgs = {
        organization: organizationSlug,
        repository: repositoryUuid,
    };

    useEffect(() => {
        if (!previewable) {
            return;
        }

        const controller = new AbortController();

        // Debounced, so typing a custom format does not ask on every key
        const timeout = setTimeout(async () => {
            try {
                const response = await fetch(
                    TagFormatPreviewController.url(
                        {
                            organization: organizationSlug,
                            repository: repositoryUuid,
                        },
                        { query: { tag_format: template } },
                    ),
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                );
                const data = await response.json();

                setPreviewResult(
                    response.ok
                        ? { template, tags: data.tags, error: null }
                        : {
                              template,
                              tags: null,
                              error: data.error ?? 'The preview failed.',
                          },
                );
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }

                setPreviewResult({
                    template,
                    tags: null,
                    error: 'The preview failed.',
                });
            }
        }, 400);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [template, previewable, organizationSlug, repositoryUuid]);

    const choosePreset = (value: string) => {
        setPreset(value);

        const chosen = presets.find((item) => item.value === value);

        if (chosen && chosen.template !== null) {
            setTemplate(chosen.template);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tag format</CardTitle>
                <CardDescription>
                    How tags release a single package of this repository.
                    Version tags such as <code>v1.2.0</code> always release
                    every package, and branches stay shared.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action={update.url(routeArgs)}
                    method="patch"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="tag_format"
                                value={template}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="tag_format_preset">
                                    Tag style
                                </Label>
                                <Select
                                    value={preset}
                                    onValueChange={choosePreset}
                                >
                                    <SelectTrigger id="tag_format_preset">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {presets.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {preset !== SHARED && (
                                <div className="grid gap-2">
                                    <Label htmlFor="tag_format">Format</Label>
                                    <Input
                                        id="tag_format"
                                        value={template}
                                        className="font-mono"
                                        placeholder="{package}@{version}"
                                        onChange={(event) => {
                                            setTemplate(event.target.value);
                                            setPreset(
                                                presetFor(event.target.value),
                                            );
                                        }}
                                    />
                                    <p className="text-muted-foreground">
                                        Use <code>{'{version}'}</code> and one
                                        of <code>{'{package}'}</code> (the name
                                        without vendor), <code>{'{name}'}</code>{' '}
                                        (the full Composer name) or{' '}
                                        <code>{'{path}'}</code> (the package
                                        directory).
                                    </p>
                                    {errors.tag_format && (
                                        <p className="text-destructive">
                                            {errors.tag_format}
                                        </p>
                                    )}
                                </div>
                            )}

                            {previewError && (
                                <p className="text-destructive">
                                    {previewError}
                                </p>
                            )}

                            {preview && preview.length > 0 && (
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    Recent tag
                                                </TableHead>
                                                <TableHead>Releases</TableHead>
                                                <TableHead>Version</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {preview.map((row) => (
                                                <TableRow key={row.tag}>
                                                    <TableCell className="font-mono">
                                                        {row.tag}
                                                    </TableCell>
                                                    <TableCell>
                                                        <PreviewRelease
                                                            row={row}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="font-mono">
                                                        {row.version ?? '-'}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {preview && preview.length === 0 && (
                                <p className="text-muted-foreground">
                                    This repository has no tags yet.
                                </p>
                            )}

                            <p className="text-muted-foreground">
                                Saving a different format starts a full sync.
                                Versions created from tags that no longer fit
                                are removed.
                            </p>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        template === (tagFormat ?? '')
                                    }
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Save tag format'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function PreviewRelease({ row }: { row: TagFormatPreviewData }) {
    if (row.release === 'ignored') {
        return <Badge variant="secondary">Ignored</Badge>;
    }

    if (row.release === 'shared') {
        return <Badge variant="secondary">All packages</Badge>;
    }

    return (
        <span className="flex items-center gap-2">
            <span className="font-mono">{row.package}</span>
            {!row.packageKnown && (
                <span className="text-muted-foreground">(not synced yet)</span>
            )}
        </span>
    );
}
