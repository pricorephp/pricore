import * as React from 'react';

import { cn } from '@/lib/utils';

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
    ({ className, type, ...props }, ref) => {
        return (
            <input
                ref={ref}
                type={type}
                data-slot="input"
                className={cn(
                    'flex h-10 w-full min-w-0 rounded-lg border border-t-2 border-input bg-gradient-to-t from-white to-stone-50 px-3 py-2 text-base shadow-xs transition-all duration-150 outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:font-medium file:text-foreground placeholder:text-muted-foreground/60 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 dark:from-white/[0.02] dark:to-white/[0.06]',
                    'focus-visible:border-ring focus-visible:ring-4 focus-visible:ring-ring/20',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                    className,
                )}
                {...props}
            />
        );
    },
);

Input.displayName = 'Input';

export { Input };
