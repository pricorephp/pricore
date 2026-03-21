import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

export function CopyButton({
    text,
    icon: Icon = Copy,
    tooltip = 'Copied!',
    variant = 'ghost',
}: {
    text: string;
    icon?: React.ComponentType<React.SVGProps<SVGSVGElement>>;
    tooltip?: string;
    variant?: 'ghost' | 'outline';
}) {
    const [copied, setCopied] = useState(false);
    const isOutline = variant === 'outline';

    const copyToClipboard = async () => {
        if (!navigator?.clipboard) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            } catch (err) {
                console.warn('Failed to copy text', err);
            } finally {
                document.body.removeChild(textArea);
            }
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.warn('Failed to copy text', err);
        }
    };

    return (
        <Tooltip open={copied}>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant={variant}
                    size="icon"
                    className={isOutline ? 'h-8 w-8' : 'h-6 w-6'}
                    onClick={copyToClipboard}
                >
                    {copied ? (
                        <Check
                            className={`text-green-600 dark:text-green-400 ${isOutline ? 'h-4 w-4' : 'h-3 w-3'}`}
                        />
                    ) : (
                        <Icon className={isOutline ? 'h-4 w-4' : 'h-3 w-3'} />
                    )}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}
