import CheckoutController from '@/actions/PricoreCloud/Http/Controllers/CheckoutController';
import {
    cancel,
    paymentMethod,
    resume,
} from '@/actions/PricoreCloud/Http/Controllers/SubscriptionController';
import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { router } from '@inertiajs/react';
import { AlertCircle, Check } from 'lucide-react';
import { useState } from 'react';

declare global {
    interface Window {
        Paddle: {
            Checkout: {
                open: (options: Record<string, unknown>) => void;
            };
        };
    }
}

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface PlanData {
    plan: 'free' | 'business';
    maxPackages: number | null;
    currentPackages: number;
}

interface Props {
    organization: OrganizationData;
    plan: PlanData;
    subscribed: boolean;
    onGracePeriod: boolean;
    endsAt: string | null;
    plans: { business: { monthly: string; yearly: string } };
}

function getCookie(name: string): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return decodeURIComponent(parts.pop()!.split(';').shift()!);
    }
    return null;
}

export default function Billing({
    organization,
    plan,
    subscribed,
    onGracePeriod,
    endsAt,
    plans,
}: Props) {
    const isFree = plan.plan === 'free';
    const isUnlimited = plan.maxPackages === null;
    const usagePercentage = isUnlimited
        ? 0
        : Math.round((plan.currentPackages / (plan.maxPackages ?? 1)) * 100);

    const [billingInterval, setBillingInterval] = useState<
        'monthly' | 'yearly'
    >('monthly');
    const [checkoutLoading, setCheckoutLoading] = useState(false);

    const handleCheckout = async (priceId: string) => {
        setCheckoutLoading(true);
        try {
            const response = await fetch(
                CheckoutController.url(organization.slug),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
                    },
                    body: JSON.stringify({ price_id: priceId }),
                },
            );

            if (!response.ok) {
                return;
            }

            const options = await response.json();
            options.settings = {
                ...options.settings,
                displayMode: 'overlay',
            };
            delete options.settings.frameStyle;

            window.Paddle.Checkout.open(options);
        } finally {
            setCheckoutLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            <HeadingSmall
                title="Billing"
                description="Manage your organization's subscription and usage"
            />

            {onGracePeriod && endsAt && (
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Subscription ending</AlertTitle>
                    <AlertDescription>
                        Your subscription has been cancelled and will end on{' '}
                        <strong>
                            {new Date(endsAt).toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                        </strong>
                        . You can resume your subscription to keep your Business
                        plan.
                        <div className="mt-3">
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(resume.url(organization.slug))
                                }
                            >
                                Resume subscription
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>
            )}

            <div className="rounded-lg border bg-card p-6">
                <div className="flex items-center gap-3">
                    <h3 className="text-lg font-medium">Current Plan</h3>
                    <Badge variant={isFree ? 'secondary' : 'default'}>
                        {isFree ? 'Free' : 'Business'}
                    </Badge>
                </div>

                <div className="mt-4 space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Packages</span>
                        <span>
                            {plan.currentPackages}
                            {isUnlimited ? '' : ` / ${plan.maxPackages}`}
                        </span>
                    </div>
                    {!isUnlimited && (
                        <div className="h-2 w-full overflow-hidden rounded-full bg-secondary">
                            <div
                                className="h-full rounded-full bg-primary transition-all"
                                style={{ width: `${usagePercentage}%` }}
                            />
                        </div>
                    )}
                </div>
            </div>

            {!subscribed && (
                <div className="space-y-4">
                    <HeadingSmall
                        title="Upgrade to Business"
                        description="Remove package limits and unlock all features"
                    />

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setBillingInterval('monthly')}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                billingInterval === 'monthly'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Monthly
                        </button>
                        <button
                            type="button"
                            onClick={() => setBillingInterval('yearly')}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                billingInterval === 'yearly'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Yearly
                        </button>
                    </div>

                    <div className="rounded-lg border bg-card p-6">
                        <div className="flex items-start justify-between">
                            <div>
                                <h4 className="font-medium">Business</h4>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Everything in Free, plus:
                                </p>
                                <ul className="mt-3 space-y-2 text-sm">
                                    <li className="flex items-center gap-2">
                                        <Check className="h-4 w-4 text-primary" />
                                        Unlimited packages
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <Check className="h-4 w-4 text-primary" />
                                        Priority support
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="mt-6">
                            <Button
                                onClick={() =>
                                    handleCheckout(
                                        plans.business[billingInterval],
                                    )
                                }
                                disabled={checkoutLoading}
                            >
                                {checkoutLoading ? 'Loading...' : 'Subscribe'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {subscribed && !onGracePeriod && (
                <>
                    <div className="space-y-4">
                        <HeadingSmall
                            title="Payment method"
                            description="Update your payment method on Paddle"
                        />
                        <div>
                            <Button variant="outline" asChild>
                                <a
                                    href={paymentMethod.url(organization.slug)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Manage payment method
                                </a>
                            </Button>
                        </div>
                    </div>

                    <CancelSubscription organization={organization} />
                </>
            )}
        </div>
    );
}

function CancelSubscription({
    organization,
}: {
    organization: OrganizationData;
}) {
    return (
        <div className="space-y-4">
            <HeadingSmall
                title="Cancel subscription"
                description="Cancel your Business subscription"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Warning</p>
                    <p>
                        If you cancel, your subscription will remain active
                        until the end of the current billing period. After that,
                        your organization will be downgraded to the Free plan.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="destructive">
                            Cancel subscription
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Cancel your subscription?</DialogTitle>
                        <DialogDescription>
                            Your subscription will remain active until the end
                            of the current billing period. You can resume at any
                            time before it ends.
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">
                                    Keep subscription
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.post(cancel.url(organization.slug))
                                }
                            >
                                Cancel subscription
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}

Billing.layout = withOrganizationSettingsLayout;
