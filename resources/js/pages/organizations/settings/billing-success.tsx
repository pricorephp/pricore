import { Button } from '@/components/ui/button';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Link } from '@inertiajs/react';
import { PartyPopper } from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface Props {
    organization: OrganizationData;
}

export default function BillingSuccess({ organization }: Props) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <PartyPopper className="size-8 text-green-600 dark:text-green-400" />
            </div>

            <h2 className="mt-6 text-2xl font-semibold">
                Thanks for subscribing!
            </h2>
            <p className="mt-2 max-w-sm text-muted-foreground">
                Your Business plan is being activated. You now have full access
                to all features.
            </p>

            <Button className="mt-8" asChild>
                <Link
                    href={`/organizations/${organization.slug}/settings/billing`}
                >
                    Go to billing
                </Link>
            </Button>
        </div>
    );
}

BillingSuccess.layout = withOrganizationSettingsLayout;
