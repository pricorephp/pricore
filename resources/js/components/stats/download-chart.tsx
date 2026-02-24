import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import { DateTime } from 'luxon';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';

type DailyDownloadData =
    App.Domains.Organization.Contracts.Data.DailyDownloadData;

interface DownloadChartProps {
    title: string;
    data: DailyDownloadData[];
    compact?: boolean;
    className?: string;
}

const chartConfig: ChartConfig = {
    downloads: {
        label: 'Downloads',
        color: 'var(--chart-1)',
    },
};

export function DownloadChart({
    title,
    data,
    compact = false,
    className,
}: DownloadChartProps) {
    const hasDownloads = data.some((d) => d.downloads > 0);

    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex min-h-[200px] flex-1">
                {!hasDownloads ? (
                    <div className="flex min-h-[200px] flex-1 items-center justify-center text-muted-foreground">
                        No downloads yet
                    </div>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="min-h-[200px] flex-1"
                    >
                        <AreaChart
                            data={data}
                            margin={{ top: 4, right: 4, bottom: 0, left: 0 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillDownloads"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-downloads)"
                                        stopOpacity={0.3}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-downloads)"
                                        stopOpacity={0}
                                    />
                                </linearGradient>
                            </defs>
                            <CartesianGrid
                                vertical={false}
                                strokeDasharray="3 3"
                                className="stroke-border"
                            />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                tickFormatter={(value: string) =>
                                    DateTime.fromISO(value).toFormat('LLL d')
                                }
                                interval={compact ? 4 : 2}
                                className="text-xs"
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                allowDecimals={false}
                                width={40}
                                className="text-xs"
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        config={chartConfig}
                                        labelFormatter={(label: string) =>
                                            DateTime.fromISO(label).toFormat(
                                                'DDD',
                                            )
                                        }
                                    />
                                }
                            />
                            <Area
                                type="monotone"
                                dataKey="downloads"
                                stroke="var(--color-downloads)"
                                strokeWidth={2}
                                fill="url(#fillDownloads)"
                            />
                        </AreaChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
