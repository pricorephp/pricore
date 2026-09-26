import GitProviderIcon, {
    getProviderColor,
} from '@/components/git-provider-icon';
import { splitPackageName } from '@/components/package-card';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Package } from 'lucide-react';
import { HoverCard } from 'radix-ui';
import { type ReactNode } from 'react';

export type QuickMenuKind = 'packages' | 'repositories';

interface NavQuickMenuProps {
    kind: QuickMenuKind;
    href: string;
    children: ReactNode;
}

export function NavQuickMenu({ kind, href, children }: NavQuickMenuProps) {
    const { recentlyVisited } = usePage<SharedData>().props;

    const items =
        kind === 'packages'
            ? (recentlyVisited?.packages ?? []).map((pkg) => ({
                  uuid: pkg.uuid,
                  href: `/organizations/${pkg.organizationSlug}/packages/${pkg.uuid}`,
                  icon: <Package className="size-4 text-muted-foreground" />,
                  label: <PackageLabel name={pkg.name} />,
              }))
            : (recentlyVisited?.repositories ?? []).map((repository) => ({
                  uuid: repository.uuid,
                  href: `/organizations/${repository.organizationSlug}/repositories/${repository.uuid}`,
                  icon: (
                      <GitProviderIcon
                          provider={repository.provider}
                          className={cn(
                              'size-4',
                              getProviderColor(repository.provider),
                          )}
                      />
                  ),
                  label: <span className="truncate">{repository.name}</span>,
              }));

    const noun = kind === 'packages' ? 'packages' : 'repositories';

    return (
        <HoverCard.Root openDelay={60} closeDelay={80}>
            <HoverCard.Trigger asChild>{children}</HoverCard.Trigger>
            <HoverCard.Portal>
                <HoverCard.Content
                    side="right"
                    align="start"
                    sideOffset={10}
                    className="z-50 w-64 origin-(--radix-hover-card-content-transform-origin) rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-lg duration-100 data-[side=right]:slide-in-from-left-1 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
                >
                    <div className="px-2 pt-1 pb-1.5 text-xs font-medium text-muted-foreground">
                        Recently visited
                    </div>
                    {items.length === 0 ? (
                        <p className="px-2 pb-2 text-muted-foreground">
                            {noun.charAt(0).toUpperCase() + noun.slice(1)} you
                            open will show up here.
                        </p>
                    ) : (
                        <ul>
                            {items.map((item) => (
                                <li key={item.uuid}>
                                    <Link
                                        href={item.href}
                                        className="flex items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-accent"
                                    >
                                        <span className="shrink-0">
                                            {item.icon}
                                        </span>
                                        <span className="flex min-w-0 flex-1">
                                            {item.label}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="mt-1 border-t pt-1">
                        <Link
                            href={href}
                            className="group flex items-center justify-between rounded-md px-2 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            All {noun}
                            <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    </div>
                </HoverCard.Content>
            </HoverCard.Portal>
        </HoverCard.Root>
    );
}

function PackageLabel({ name }: { name: string }) {
    const [vendor, shortName] = splitPackageName(name);

    return (
        <span className="truncate">
            {vendor && <span className="text-muted-foreground">{vendor}/</span>}
            {shortName}
        </span>
    );
}
