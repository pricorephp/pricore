import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import { DateTime } from 'luxon';
import { useMemo } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

type VersionDailyDownloadData =
    App.Domains.Package.Contracts.Data.VersionDailyDownloadData;
type DailyDownloadData =
    App.Domains.Organization.Contracts.Data.DailyDownloadData;

interface VersionDownloadChartProps {
    title: string;
    versionData: VersionDailyDownloadData[];
    fallbackData: DailyDownloadData[];
    compact?: boolean;
    className?: string;
}

const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--muted-foreground)',
] as const;

export function VersionDownloadChart({
    title,
    versionData,
    fallbackData,
    compact = false,
    className,
}: VersionDownloadChartProps) {
    const versions = versionData.map((v) => v.version);

    const { chartData, chartConfig, maxDownloads } = useMemo(() => {
        if (versions.length === 0) {
            return {
                chartData: [],
                chartConfig: {} as ChartConfig,
                maxDownloads: 0,
            };
        }

        const config: ChartConfig = {};
        versions.forEach((version, index) => {
            config[version] = {
                label: version,
                color: CHART_COLORS[index % CHART_COLORS.length],
            };
        });

        const dateCount = versionData[0].dailyDownloads.length;
        const data: Record<string, string | number>[] = [];

        for (let i = 0; i < dateCount; i++) {
            const entry: Record<string, string | number> = {
                date: versionData[0].dailyDownloads[i].date,
            };
            let dayTotal = 0;
            for (const vd of versionData) {
                entry[vd.version] = vd.dailyDownloads[i].downloads;
                dayTotal += vd.dailyDownloads[i].downloads;
            }
            entry._total = dayTotal;
            data.push(entry);
        }

        const max = Math.max(...data.map((d) => d._total as number));

        return { chartData: data, chartConfig: config, maxDownloads: max };
    }, [versionData, versions]);

    const hasDownloads =
        versions.length > 0
            ? chartData.some((d) => (d._total as number) > 0)
            : fallbackData.some((d) => d.downloads > 0);

    const yAxisWidth =
        maxDownloads >= 10000 ? 55 : maxDownloads >= 1000 ? 48 : 40;

    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex min-h-64 flex-1">
                {!hasDownloads ? (
                    <div className="flex min-h-64 flex-1 items-center justify-center text-muted-foreground">
                        No downloads yet
                    </div>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="min-h-64 flex-1"
                    >
                        <BarChart
                            data={chartData}
                            margin={{ top: 4, right: 4, bottom: 0, left: 0 }}
                            barCategoryGap="25%"
                        >
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
                                width={yAxisWidth}
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
                            {versions.map((version, index) => (
                                <Bar
                                    key={version}
                                    dataKey={version}
                                    stackId="1"
                                    fill={
                                        CHART_COLORS[
                                            index % CHART_COLORS.length
                                        ]
                                    }
                                    radius={
                                        index === versions.length - 1
                                            ? [3, 3, 0, 0]
                                            : [0, 0, 0, 0]
                                    }
                                />
                            ))}
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
