import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full px-2 py-px text-xs font-medium whitespace-nowrap tabular-nums transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3',
    {
        variants: {
            variant: {
                default:
                    'bg-primary/10 text-primary dark:bg-primary/15 [a&]:hover:bg-primary/15',
                secondary:
                    'bg-muted text-muted-foreground [a&]:hover:bg-accent',
                destructive:
                    'bg-red-500/10 text-red-600 dark:bg-red-500/15 dark:text-red-400 [a&]:hover:bg-red-500/15',
                success:
                    'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400 [a&]:hover:bg-emerald-500/15',
                warning:
                    'bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400 [a&]:hover:bg-amber-500/15',
                info: 'bg-blue-500/10 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400 [a&]:hover:bg-blue-500/15',
                outline:
                    'text-muted-foreground ring-1 ring-border ring-inset [a&]:hover:bg-accent',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Badge({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<'span'> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Badge, badgeVariants };
