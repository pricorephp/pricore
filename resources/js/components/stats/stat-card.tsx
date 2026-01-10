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

const variantIconColors = {
    success: 'text-green-600 dark:text-green-400',
    warning: 'text-yellow-600 dark:text-yellow-400',
    danger: 'text-red-600 dark:text-red-400',
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
    const statusIconColor =
        variant !== 'default' ? variantIconColors[variant] : '';

    return (
        <Card className={className}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <div className="flex items-center gap-1">
                    {StatusIcon && (
                        <StatusIcon
                            className={cn('h-4 w-4', statusIconColor)}
                        />
                    )}
                    {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                {description && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
