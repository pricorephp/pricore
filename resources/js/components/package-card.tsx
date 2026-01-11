import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowUpRight, Globe, Lock } from 'lucide-react';
import { DateTime } from 'luxon';

type PackageData = App.Domains.Package.Contracts.Data.PackageData;

interface PackageCardProps {
    package: PackageData;
    hideRepository?: boolean;
}

export default function PackageCard({ package: pkg }: PackageCardProps) {
    return (
        <Card className="group gap-4 hover:shadow-md">
            <CardHeader>
                <CardTitle className="flex items-start justify-between gap-2">
                    <span className="transition-colors group-hover:text-primary">
                        {pkg.name}
                    </span>
                    <div className="flex shrink-0 items-center gap-2">
                        <div className="flex items-center gap-1 rounded-full bg-muted/50 px-2 py-0.5">
                            {pkg.visibility === 'private' ? (
                                <>
                                    <Lock className="h-3 w-3 text-muted-foreground" />
                                    <span className="text-xs text-muted-foreground">
                                        Private
                                    </span>
                                </>
                            ) : (
                                <>
                                    <Globe className="h-3 w-3 text-muted-foreground" />
                                    <span className="text-xs text-muted-foreground">
                                        Public
                                    </span>
                                </>
                            )}
                        </div>
                        <ArrowUpRight className="h-4 w-4 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex flex-row items-center gap-2 text-xs text-muted-foreground">
                    {pkg.latestVersion && (
                        <Badge
                            variant="outline"
                            className="bg-muted/30 font-mono"
                        >
                            {pkg.latestVersion}
                        </Badge>
                    )}

                    {pkg.versionsCount > 0 && (
                        <span>
                            {pkg.versionsCount}{' '}
                            {pkg.versionsCount === 1 ? 'version' : 'versions'}
                        </span>
                    )}
                </div>

                <div className="border-t pt-3 text-xs text-muted-foreground">
                    Updated {DateTime.fromISO(pkg.updatedAt).toRelative()}
                </div>
            </CardContent>
        </Card>
    );
}
