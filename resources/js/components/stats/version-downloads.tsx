import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { Bar, BarChart, XAxis, YAxis } from 'recharts';

type VersionDownloadData =
    App.Domains.Package.Contracts.Data.VersionDownloadData;

interface VersionDownloadsProps {
    data: VersionDownloadData[];
}

const chartConfig: ChartConfig = {
    downloads: {
        label: 'Downloads',
        color: 'var(--chart-1)',
    },
};

export function VersionDownloads({ data }: VersionDownloadsProps) {
    if (data.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Downloads by Version</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex h-[200px] items-center justify-center text-muted-foreground">
                        No version data available
                    </div>
                </CardContent>
            </Card>
        );
    }

    const chartHeight = Math.max(200, data.length * 36);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Downloads by Version</CardTitle>
            </CardHeader>
            <CardContent>
                <ChartContainer
                    config={chartConfig}
                    className={`h-[${chartHeight}px]`}
                    style={{ height: chartHeight }}
                >
                    <BarChart data={data} layout="vertical">
                        <XAxis
                            type="number"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            allowDecimals={false}
                            className="text-xs"
                        />
                        <YAxis
                            type="category"
                            dataKey="version"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            width={100}
                            className="text-xs"
                        />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent config={chartConfig} />
                            }
                        />
                        <Bar
                            dataKey="downloads"
                            fill="var(--color-downloads)"
                            radius={[0, 4, 4, 0]}
                        />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
