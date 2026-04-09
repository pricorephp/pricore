import { useEffect, useState } from 'react';

type ReleaseData = App.Domains.Release.Contracts.Data.ReleaseData;
type ReleaseInfoData = Omit<
    App.Domains.Release.Contracts.Data.ReleaseInfoData,
    'releases'
> & {
    releases: ReleaseData[];
};

export function useReleaseInfo(): {
    info: ReleaseInfoData | null;
    loading: boolean;
} {
    const [info, setInfo] = useState<ReleaseInfoData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        fetch('/releases', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (cancelled) return;
                setInfo((data?.release_info as ReleaseInfoData | null) ?? null);
            })
            .catch(() => {})
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return { info, loading };
}
