import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface EmptyStateProps {
    icon?: LucideIcon;
    title: string;
    description: string;
    action?: {
        label: string;
        onClick?: () => void;
        href?: string;
    };
    children?: ReactNode;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    children,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 px-6 py-16 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="mb-4 rounded-full bg-muted/50 p-4">
                    <Icon className="h-8 w-8 text-muted-foreground/70" />
                </div>
            )}
            <h3 className="text-lg font-medium text-foreground">{title}</h3>
            <p className="mt-1.5 max-w-sm text-muted-foreground">
                {description}
            </p>
            {action && (
                <Button
                    className="mt-6"
                    variant="secondary"
                    asChild={!!action.href}
                >
                    {action.href ? (
                        <a href={action.href}>{action.label}</a>
                    ) : (
                        <span onClick={action.onClick}>{action.label}</span>
                    )}
                </Button>
            )}
            {children && <div className="mt-6">{children}</div>}
        </div>
    );
}
