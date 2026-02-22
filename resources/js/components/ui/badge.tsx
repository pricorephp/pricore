import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-2 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-all duration-150 overflow-hidden',
    {
        variants: {
            variant: {
                default:
                    'border border-button-primary-border bg-primary text-primary-foreground [a&]:hover:bg-primary/90',
                secondary:
                    'border border-gray-200 bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90 dark:border-gray-600',
                destructive:
                    'border border-red-200 bg-red-100 text-red-700 [a&]:hover:bg-red-200/90 dark:bg-red-900/30 dark:text-red-400 dark:border-red-900',
                success:
                    'border border-emerald-200 bg-emerald-100 text-emerald-700 [a&]:hover:bg-emerald-200/90 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-900',
                outline:
                    'text-foreground bg-muted/30 [a&]:hover:bg-accent [a&]:hover:text-accent-foreground ',
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
