import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Globe, Lock } from 'lucide-react';
import { DateTime } from 'luxon';

type PackageData = App.Domains.Package.Contracts.Data.PackageData;

interface PackageCardProps {
    package: PackageData;
    hideRepository?: boolean;
}

export default function PackageCard({
    package: pkg,
    hideRepository = false,
}: PackageCardProps) {
    return (
        <Card className="gap-4 transition-colors hover:bg-accent/50">
            <CardHeader>
                <CardTitle className="flex items-start justify-between gap-2">
                    <span>{pkg.name}</span>
                    <div className="flex shrink-0 items-center gap-1.5">
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
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex flex-row items-center gap-2 text-xs text-muted-foreground">
                    {pkg.latestVersion && (
                        <Badge variant="outline" className="font-mono">
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

                <div className="pt-2 text-xs text-muted-foreground">
                    Updated {DateTime.fromISO(pkg.updatedAt).toRelative()}
                </div>
            </CardContent>
        </Card>
    );
}
