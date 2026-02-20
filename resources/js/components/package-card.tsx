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
        <Card className="group gap-4">
            <CardHeader>
                <CardTitle className="flex items-start justify-between gap-2">
                    <span className="transition-colors group-hover:text-primary">
                        {pkg.name}
                    </span>
                    <div className="flex shrink-0 items-center gap-2">
                        <Badge variant="outline">
                            {pkg.visibility === 'private' ? (
                                <>
                                    <Lock className="h-3 w-3" />
                                    <span className="text-xs">Private</span>
                                </>
                            ) : (
                                <>
                                    <Globe className="h-3 w-3" />
                                    <span className="text-xs">Public</span>
                                </>
                            )}
                        </Badge>
                        <ArrowUpRight className="h-4 w-4 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex flex-row items-center justify-between gap-2 text-sm text-muted-foreground">
                    {pkg.latestVersion && (
                        <span className="font-mono">{pkg.latestVersion}</span>
                    )}

                    {pkg.versionsCount > 0 && (
                        <span>
                            {pkg.versionsCount}{' '}
                            {pkg.versionsCount === 1 ? 'version' : 'versions'}
                        </span>
                    )}
                </div>

                <div className="pt-3 text-sm text-muted-foreground">
                    Updated {DateTime.fromISO(pkg.updatedAt).toRelative()}
                </div>
            </CardContent>
        </Card>
    );
}
