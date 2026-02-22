import { cn } from '@/lib/utils';
import * as React from 'react';
import {
    Tooltip as RechartsTooltip,
    type TooltipProps as RechartsTooltipProps,
    ResponsiveContainer,
} from 'recharts';

// Chart config type for consistent color/label mapping
export type ChartConfig = Record<
    string,
    {
        label: string;
        color: string;
    }
>;

interface ChartContainerProps extends React.HTMLAttributes<HTMLDivElement> {
    config: ChartConfig;
    children: React.ReactElement;
}

export function ChartContainer({
    config,
    children,
    className,
    style,
    ...props
}: ChartContainerProps) {
    const cssVars = Object.entries(config).reduce(
        (acc, [key, value]) => {
            acc[`--color-${key}`] = value.color;
            return acc;
        },
        {} as Record<string, string>,
    );

    return (
        <div
            className={cn('w-full [&_svg]:outline-none', className)}
            style={{ ...cssVars, ...style }}
            {...props}
        >
            <ResponsiveContainer width="100%" height="100%">
                {children}
            </ResponsiveContainer>
        </div>
    );
}

interface ChartTooltipContentProps {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        color: string;
        dataKey: string;
    }>;
    label?: string;
    labelFormatter?: (label: string) => string;
    valueFormatter?: (value: number) => string;
    config?: ChartConfig;
}

export function ChartTooltipContent({
    active,
    payload,
    label,
    labelFormatter,
    valueFormatter,
    config,
}: ChartTooltipContentProps) {
    if (!active || !payload?.length) {
        return null;
    }

    const formattedLabel = labelFormatter
        ? labelFormatter(String(label))
        : label;

    return (
        <div className="rounded-lg border bg-background px-3 py-2 shadow-md">
            <p className="mb-1 text-xs text-muted-foreground">
                {formattedLabel}
            </p>
            {payload.map((entry) => {
                const configEntry = config?.[entry.dataKey];
                const displayLabel = configEntry?.label ?? entry.name;
                const displayValue = valueFormatter
                    ? valueFormatter(entry.value)
                    : entry.value.toLocaleString();

                return (
                    <div
                        key={entry.dataKey}
                        className="flex items-center gap-2 text-sm"
                    >
                        <div
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="text-muted-foreground">
                            {displayLabel}:
                        </span>
                        <span className="font-medium">{displayValue}</span>
                    </div>
                );
            })}
        </div>
    );
}

export function ChartTooltip(props: RechartsTooltipProps<number, string>) {
    return <RechartsTooltip {...props} />;
}
