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
import { Check, Copy } from 'lucide-react';
import { useRef, useState } from 'react';

interface TokenCreatedDialogProps {
    token: string;
    name: string;
    expiresAt: string | null;
    scopes: string[];
    isOpen: boolean;
    onClose: () => void;
}

export default function TokenCreatedDialog({
    token,
    name,
    expiresAt,
    scopes,
    isOpen,
    onClose,
}: TokenCreatedDialogProps) {
    const [copied, setCopied] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const copyToClipboard = async (text: string, key: string) => {
        try {
            if (key === 'token' && inputRef.current) {
                inputRef.current.focus();
                inputRef.current.select();
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
            }

            setCopied(key);
            setTimeout(() => setCopied(null), 2000);
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    };

    const domain = window.location.host;

    // A legacy token with no recorded scopes can do everything.
    const grantsEverything = scopes.length === 0;
    const hasComposer = grantsEverything || scopes.includes('composer');
    const hasApi =
        grantsEverything || scopes.some((scope) => scope !== 'composer');

    const composerCommand = `composer config --global --auth http-basic.${domain} token ${token}`;
    const apiCommand = `curl -H "Authorization: Bearer ${token}" \\\n     -H "Accept: application/json" \\\n     https://${domain}/api/v1/user`;

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Access Token Created</DialogTitle>
                    <DialogDescription>
                        Make sure to copy your token now. You won't be able to
                        see it again!
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div>
                        <label className="font-medium">Token Name</label>
                        <p className="text-muted-foreground">{name}</p>
                    </div>

                    {expiresAt && (
                        <div>
                            <label className="font-medium">Expires</label>
                            <p className="text-muted-foreground">
                                {new Date(expiresAt).toLocaleDateString()}
                            </p>
                        </div>
                    )}

                    <div>
                        <label className="font-medium">Token</label>
                        <div className="mt-2 flex gap-2">
                            <Input
                                ref={inputRef}
                                type="text"
                                value={token}
                                readOnly
                                className="font-mono"
                                onFocus={(e) => e.target.select()}
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                onClick={() => copyToClipboard(token, 'token')}
                                className="shrink-0"
                            >
                                {copied === 'token' ? (
                                    <Check className="h-4 w-4" />
                                ) : (
                                    <Copy className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    {hasComposer && (
                        <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                            <p className="font-medium text-neutral-900 dark:text-neutral-100">
                                Configure Composer
                            </p>
                            <p className="mt-1 text-neutral-700 dark:text-neutral-300">
                                Use this token to authenticate with Composer:
                            </p>
                            <div className="mt-2 flex items-start gap-2">
                                <code className="min-w-0 flex-1 rounded border border-border bg-muted p-2 font-mono text-xs break-all">
                                    {composerCommand}
                                </code>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    onClick={() =>
                                        copyToClipboard(
                                            composerCommand,
                                            'composer',
                                        )
                                    }
                                    className="shrink-0"
                                >
                                    {copied === 'composer' ? (
                                        <Check className="h-4 w-4" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}

                    {hasApi && (
                        <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                            <p className="font-medium text-neutral-900 dark:text-neutral-100">
                                Use the API
                            </p>
                            <p className="mt-1 text-neutral-700 dark:text-neutral-300">
                                Send the token as a Bearer header. See the{' '}
                                <a
                                    href="/docs/api"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline underline-offset-2"
                                >
                                    API reference
                                </a>
                                .
                            </p>
                            <div className="mt-2 flex items-start gap-2">
                                <code className="min-w-0 flex-1 rounded border border-border bg-muted p-2 font-mono text-xs break-all whitespace-pre-wrap">
                                    {apiCommand}
                                </code>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    onClick={() =>
                                        copyToClipboard(apiCommand, 'api')
                                    }
                                    className="shrink-0"
                                >
                                    {copied === 'api' ? (
                                        <Check className="h-4 w-4" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button onClick={onClose}>Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
