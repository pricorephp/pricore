import type { AccessTokenData } from '@/../../resources/types/generated';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Trash2 } from 'lucide-react';
import { DateTime } from 'luxon';

interface TokenListProps {
    tokens: AccessTokenData[];
    onRevoke: (uuid: string, name: string) => void;
}

export default function TokenList({ tokens, onRevoke }: TokenListProps) {
    if (tokens.length === 0) {
        return (
            <div className="rounded-lg border border-dashed p-8 text-center">
                <p className="text-sm text-muted-foreground">
                    No access tokens yet. Create one to get started.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {tokens.map((token, index) => (
                <div key={token.uuid}>
                    {index > 0 && <Separator />}
                    <div className="flex items-start justify-between py-4">
                        <div className="flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium">{token.name}</h3>
                                {isTokenExpired(token.expiresAt) && (
                                    <Badge variant="destructive">Expired</Badge>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                <span>
                                    Last used:{' '}
                                    {token.lastUsedAt
                                        ? DateTime.fromISO(
                                              token.lastUsedAt,
                                          ).toRelative()
                                        : 'Never'}
                                </span>
                                <span>
                                    Expires:{' '}
                                    {token.expiresAt
                                        ? DateTime.fromISO(
                                              token.expiresAt,
                                          ).toLocaleString(DateTime.DATE_MED)
                                        : 'Never'}
                                </span>
                                <span>
                                    Created:{' '}
                                    {DateTime.fromISO(
                                        token.createdAt,
                                    ).toLocaleString(DateTime.DATE_MED)}
                                </span>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onRevoke(token.uuid, token.name)}
                            aria-label={`Revoke ${token.name}`}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            ))}
        </div>
    );
}

function isTokenExpired(expiresAt: string | null): boolean {
    if (!expiresAt) return false;
    return new Date(expiresAt) < new Date();
}
