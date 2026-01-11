import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    CheckCircle,
    type LucideIcon,
    XCircle,
} from 'lucide-react';

interface StatCardProps {
    title: string;
    value: number | string;
    description?: string;
    icon?: LucideIcon;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    className?: string;
}

const variantIcons = {
    success: CheckCircle,
    warning: AlertTriangle,
    danger: XCircle,
};

const variantStyles = {
    default: {
        icon: 'text-muted-foreground',
        bg: '',
    },
    success: {
        icon: 'text-emerald-600 dark:text-emerald-400',
        bg: '',
    },
    warning: {
        icon: 'text-amber-600 dark:text-amber-400',
        bg: '',
    },
    danger: {
        icon: 'text-red-600 dark:text-red-400',
        bg: '',
    },
};

export function StatCard({
    title,
    value,
    description,
    icon: Icon,
    variant = 'default',
    className,
}: StatCardProps) {
    const StatusIcon = variant !== 'default' ? variantIcons[variant] : null;
    const styles = variantStyles[variant];

    return (
        <Card
            className={cn(
                'group cursor-pointer hover:shadow-md',
                styles.bg,
                className
            )}
        >
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <div className="flex items-center gap-1.5">
                    {StatusIcon && (
                        <StatusIcon className={cn('h-4 w-4', styles.icon)} />
                    )}
                    {Icon && (
                        <div className="rounded-md bg-muted/50 p-1.5 transition-colors group-hover:bg-muted">
                            <Icon className="h-4 w-4 text-muted-foreground" />
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="pt-1">
                <div className="text-3xl font-semibold tracking-tight">
                    {value}
                </div>
                {description && (
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
