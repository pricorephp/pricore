import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

interface TokenCreatedDialogProps {
    token: string;
    name: string;
    expiresAt: string | null;
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function TokenCreatedDialog({
    token,
    name,
    expiresAt,
    organizationSlug,
    isOpen,
    onClose,
}: TokenCreatedDialogProps) {
    const [copied, setCopied] = useState(false);

    const copyToClipboard = async () => {
        await navigator.clipboard.writeText(token);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const domain = window.location.host;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Access Token Created</DialogTitle>
                    <DialogDescription>
                        Make sure to copy your token now. You won't be able to
                        see it again!
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div>
                        <label className="text-sm font-medium">
                            Token Name
                        </label>
                        <p className="text-sm text-muted-foreground">{name}</p>
                    </div>

                    {expiresAt && (
                        <div>
                            <label className="text-sm font-medium">
                                Expires
                            </label>
                            <p className="text-sm text-muted-foreground">
                                {new Date(expiresAt).toLocaleDateString()}
                            </p>
                        </div>
                    )}

                    <div>
                        <label className="text-sm font-medium">Token</label>
                        <div className="mt-2 flex gap-2">
                            <code className="flex-1 rounded bg-muted p-3 font-mono text-sm">
                                {token}
                            </code>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={copyToClipboard}
                            >
                                {copied ? (
                                    <Check className="h-4 w-4" />
                                ) : (
                                    <Copy className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                        <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                            Configure Composer
                        </p>
                        <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            Use this token to authenticate with Composer:
                        </p>
                        <code className="mt-2 block rounded bg-amber-100 p-2 font-mono text-xs text-amber-900 dark:bg-amber-900 dark:text-amber-100">
                            composer config --global --auth http-basic.{domain}{' '}
                            {token} ""
                        </code>
                        <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                            Or using Bearer authentication:
                        </p>
                        <code className="mt-1 block rounded bg-amber-100 p-2 font-mono text-xs text-amber-900 dark:bg-amber-900 dark:text-amber-100">
                            composer config --global --auth bearer.{domain}{' '}
                            {token}
                        </code>
                    </div>
                </div>

                <DialogFooter>
                    <Button onClick={onClose}>Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
