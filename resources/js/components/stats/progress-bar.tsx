import { cn } from '@/lib/utils';

interface ProgressBarProps {
    value: number;
    max?: number;
    className?: string;
    variant?: 'default' | 'success' | 'warning' | 'danger';
}

const variantStyles = {
    default: 'bg-primary',
    success: 'bg-green-600 dark:bg-green-500',
    warning: 'bg-yellow-600 dark:bg-yellow-500',
    danger: 'bg-red-600 dark:bg-red-500',
};

export function ProgressBar({
    value,
    max = 100,
    className,
    variant = 'default',
}: ProgressBarProps) {
    const percentage = Math.min((value / max) * 100, 100);

    return (
        <div
            className={cn(
                'h-2 w-full overflow-hidden rounded-full bg-secondary',
                className,
            )}
        >
            <div
                className={cn('h-full transition-all', variantStyles[variant])}
                style={{ width: `${percentage}%` }}
            />
        </div>
    );
}
