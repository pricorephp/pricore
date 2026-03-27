import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useOrganizationChannel(organizationUuid: string) {
    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const channel = window.Echo.private(`organization.${organizationUuid}`);

        const reload = () => {
            router.reload({
                only: [
                    'repository',
                    'repositories',
                    'mirrors',
                    'syncLogs',
                    'activityLogs',
                ],
            });
        };

        channel.listen('.repository.sync.status-updated', reload);
        channel.listen('.mirror.sync.status-updated', reload);
        channel.listen('.activity.recorded', reload);
        channel.listen('.security.advisories-updated', reload);

        return () => {
            window.Echo.leave(`organization.${organizationUuid}`);
        };
    }, [organizationUuid]);
}
