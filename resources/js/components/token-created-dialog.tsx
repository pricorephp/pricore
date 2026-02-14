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
    isOpen: boolean;
    onClose: () => void;
}

export default function TokenCreatedDialog({
    token,
    name,
    expiresAt,
    isOpen,
    onClose,
}: TokenCreatedDialogProps) {
    const [copied, setCopied] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const copyToClipboard = async () => {
        try {
            // Select the input text
            if (inputRef.current) {
                inputRef.current.focus();
                inputRef.current.select();
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(token);
            } else {
                // Fallback for browsers that don't support clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = token;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
            }
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (error) {
            console.error('Failed to copy token:', error);
        }
    };

    const domain = window.location.host;

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg">
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
                                onClick={copyToClipboard}
                                className="shrink-0"
                            >
                                {copied ? (
                                    <Check className="h-4 w-4" />
                                ) : (
                                    <Copy className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-md border border-b-2 border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <p className="font-medium text-neutral-900 dark:text-neutral-100">
                            Configure Composer
                        </p>
                        <p className="mt-1 text-neutral-700 dark:text-neutral-300">
                            Use this token to authenticate with Composer:
                        </p>
                        <code className="mt-2 block rounded border border-border bg-muted p-2 font-mono text-xs break-all">
                            composer config --global --auth http-basic.{domain}{' '}
                            {token} ""
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
