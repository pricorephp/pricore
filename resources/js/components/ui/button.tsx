import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg text-sm font-medium transition-all duration-150 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-ring/50 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
    {
        variants: {
            variant: {
                default:
                    'bg-gradient-to-b from-button-primary-from to-button-primary-to border border-button-primary-border text-primary-foreground shadow-sm hover:brightness-110 active:scale-[0.98] active:brightness-95',
                destructive:
                    'bg-gradient-to-b from-button-destructive-from to-button-destructive-to border border-button-destructive-border text-white shadow-sm hover:brightness-110 active:scale-[0.98] active:brightness-95 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40',
                outline:
                    'border border-input bg-background shadow-sm hover:bg-accent hover:text-accent-foreground active:scale-[0.98]',
                secondary:
                    'bg-gradient-to-b from-button-secondary-from to-button-secondary-to border border-button-secondary-border text-secondary-foreground shadow-sm hover:brightness-[1.02] dark:hover:brightness-110 active:scale-[0.98] active:brightness-95',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline'
            },
            size: {
                default: 'h-9 px-4 py-2 has-[>svg]:px-3',
                sm: 'h-8 px-3 has-[>svg]:px-2.5',
                lg: 'h-10 px-6 has-[>svg]:px-4',
                icon: 'size-9'
            }
        },
        defaultVariants: {
            variant: 'default',
            size: 'default'
        }
    }
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
    asChild?: boolean
}) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
