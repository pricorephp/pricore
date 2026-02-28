import { type User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    return (
        <div className="grid flex-1">
            <span className="truncate text-base font-medium">{user.name}</span>
            {showEmail && (
                <span className="truncate text-sm text-muted-foreground">
                    {user.email}
                </span>
            )}
        </div>
    );
}
