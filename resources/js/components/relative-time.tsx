import { DateTime } from 'luxon';
import { useEffect, useState } from 'react';

interface RelativeTimeProps {
    datetime: string;
    className?: string;
}

export function RelativeTime({ datetime, className }: RelativeTimeProps) {
    const [relative, setRelative] = useState(() =>
        DateTime.fromISO(datetime).toRelative() ?? '',
    );

    useEffect(() => {
        const id = setInterval(() => {
            setRelative(DateTime.fromISO(datetime).toRelative() ?? '');
        }, 1_000);

        return () => clearInterval(id);
    }, [datetime]);

    return (
        <span
            className={className}
            title={DateTime.fromISO(datetime).toLocaleString(
                DateTime.DATETIME_FULL,
            )}
        >
            {relative}
        </span>
    );
}
