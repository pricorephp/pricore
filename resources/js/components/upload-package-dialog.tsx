import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FileDrop } from '@/components/ui/file-drop';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/react';

interface UploadPackageDialogProps {
    action: string;
    title: string;
    description: string;
    maxUploadSize: number;
    isOpen: boolean;
    onClose: () => void;
}

export default function UploadPackageDialog({
    action,
    title,
    description,
    maxUploadSize,
    isOpen,
    onClose,
}: UploadPackageDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <Form
                    action={action}
                    method="post"
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, progress, errors }) => (
                        <>
                            <div className="grid space-y-2">
                                <Label htmlFor="archive">
                                    Archive{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <FileDrop
                                    id="archive"
                                    name="archive"
                                    accept=".zip,application/zip"
                                    hint={`A .zip with composer.json at its root, up to ${maxUploadSize} MB`}
                                    invalid={!!errors.archive}
                                    disabled={processing}
                                />
                                {errors.archive && (
                                    <p className="text-destructive">
                                        {errors.archive}
                                    </p>
                                )}
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="version">
                                    Version (optional)
                                </Label>
                                <Input
                                    id="version"
                                    name="version"
                                    placeholder="1.0.0"
                                    disabled={processing}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Leave empty to use the version in
                                    composer.json. Released versions can't be
                                    replaced; dev versions can.
                                </p>
                                {errors.version && (
                                    <p className="text-destructive">
                                        {errors.version}
                                    </p>
                                )}
                            </div>

                            {processing && progress?.percentage != null && (
                                <div
                                    className="h-1.5 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    aria-valuenow={progress.percentage}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                >
                                    <div
                                        className="h-full bg-primary transition-[width]"
                                        style={{
                                            width: `${progress.percentage}%`,
                                        }}
                                    />
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
                                    {processing ? 'Uploading...' : 'Upload'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
