import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface Segment {
    value: number;
    label: string;
    color: string;
}

interface DistributionBarProps {
    segments: Segment[];
    className?: string;
}

export function DistributionBar({ segments, className }: DistributionBarProps) {
    const total = segments.reduce((acc, seg) => acc + seg.value, 0);

    if (total === 0) {
        return (
            <div
                className={cn(
                    'h-2 w-full rounded-full bg-secondary',
                    className,
                )}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex h-2 w-full overflow-hidden rounded-full',
                className,
            )}
        >
            {segments.map((segment, index) => {
                const percentage = (segment.value / total) * 100;
                if (percentage === 0) return null;

                return (
                    <Tooltip key={index}>
                        <TooltipTrigger asChild>
                            <div
                                className={cn('h-full', segment.color)}
                                style={{ width: `${percentage}%` }}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {segment.label}: {segment.value} (
                            {percentage.toFixed(1)}%)
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
